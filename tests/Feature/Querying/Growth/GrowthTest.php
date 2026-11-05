<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * It is Thursday 8 October, half past noon. The hour that just closed, 11 to
 * 12, is compared with the same hour on the four Thursdays before, and with
 * the hour before it. The breaking story takes off, the evergreen guide holds
 * steady, the landing page collapses and the draft is too small to count.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-08 12:30:00'));

    $this->breaking = Post::factory()->create();
    $this->evergreen = Post::factory()->create();
    $this->landing = Post::factory()->create();
    $this->draft = Post::factory()->create();

    foreach (['2026-10-01', '2026-09-24', '2026-09-17', '2026-09-10'] as $day) {
        growthViews($this->breaking, "{$day} 11:15:00", 5);
        growthViews($this->evergreen, "{$day} 11:15:00", 20);
        growthViews($this->landing, "{$day} 11:15:00", 40);
        growthViews($this->draft, "{$day} 11:15:00", 1);
    }

    growthViews($this->breaking, '2026-10-08 10:15:00', 6);
    growthViews($this->evergreen, '2026-10-08 10:15:00', 20);
    growthViews($this->landing, '2026-10-08 10:15:00', 40);
    growthViews($this->draft, '2026-10-08 10:15:00', 1);

    growthViews($this->breaking, '2026-10-08 11:15:00', 60);
    growthViews($this->evergreen, '2026-10-08 11:15:00', 22);
    growthViews($this->landing, '2026-10-08 11:15:00', 2);
    growthViews($this->draft, '2026-10-08 11:15:00', 4);

    $this->hour = Period::create('2026-10-08 11:00:00', '2026-10-08 12:00:00');
});

function growthViews(Model&Viewable $viewable, string $viewedAt, int $count, ?string $visitor = null): void
{
    for ($view = 0; $view < $count; $view++) {
        View::factory()
            ->for($viewable, 'viewable')
            ->viewedAt(Carbon::parse($viewedAt))
            ->fromVisitor($visitor ?? "visitor-{$view}")
            ->create();
    }
}

/** @return list<array{int|string, int, float}> */
function growthEntries(Ranking $ranking): array
{
    return array_values($ranking->entries->map(fn (Entry $entry): array => [$entry->viewable->getKey(), $entry->count, round((float) $entry->score, 2)])->all());
}

describe('rising', function (): void {
    it('ranks what grew against the period before, highest first', function (): void {
        $ranking = views(Post::class)->period($this->hour)->rising();

        expect(growthEntries($ranking))->toBe([
            [$this->breaking->getKey(), 60, 10.0],
            [$this->evergreen->getKey(), 22, 1.1],
        ])
            ->and($ranking->entries->first()?->baseline)
            ->current->toBe(60)
            ->references->toBe([6]);
    });

    it('leaves out what stayed below the minimum in both periods', function (): void {
        expect(growthEntries(views(Post::class)->period($this->hour)->rising(minimum: 1)))->toContain([$this->draft->getKey(), 4, 4.0])
            ->and(growthEntries(views(Post::class)->period($this->hour)->rising(minimum: 30)))->toBe([[$this->breaking->getKey(), 60, 10.0]]);
    });

    it('ranks across every type and limits the ranking', function (): void {
        growthViews(Apartment::factory()->create(), '2026-10-08 11:15:00', 300);

        expect(Views::period($this->hour)->rising(1)->entries->first()?->viewable)->toBeInstanceOf(Apartment::class);
    });

    it('counts unique visitors', function (): void {
        growthViews($this->evergreen, '2026-10-08 11:20:00', 40, visitor: 'regular');

        expect(growthEntries(views(Post::class)->period($this->hour)->unique()->rising()))->toBe([
            [$this->breaking->getKey(), 60, 10.0],
            [$this->evergreen->getKey(), 23, 1.15],
        ]);
    });

    it('needs a period', function (): void {
        views(Post::class)->rising();
    })->throws(InvalidPeriod::class, 'Comparing needs a period.');
});

describe('anomalies', function (): void {
    it('ranks what lies far above the same hour on past weeks, highest first', function (): void {
        $ranking = views(Post::class)->period($this->hour)->anomalies();

        expect(growthEntries($ranking))->toBe([[$this->breaking->getKey(), 60, 24.6]])
            ->and($ranking->entries->first()?->baseline)
            ->references->toBe([5, 5, 5, 5])
            ->mean->toBe(5.0)
            ->stddev->toBe(0.0);
    });

    it('ranks drops with a negative threshold, lowest first', function (): void {
        expect(growthEntries(views(Post::class)->period($this->hour)->anomalies(threshold: -3)))->toBe([[$this->landing->getKey(), 2, -6.01]]);
    });

    it('compares with past days', function (): void {
        growthViews($this->evergreen, '2026-10-07 11:15:00', 22);

        expect(growthEntries(views(Post::class)->period($this->hour)->anomalies(threshold: 0.1, seasonality: Seasonality::Day, samples: 1)))->toBe([
            [$this->breaking->getKey(), 60, 60.0],
        ]);
    });

    it('leaves out what is too small to count', function (): void {
        expect(growthEntries(views(Post::class)->period($this->hour)->anomalies(threshold: 1, minimum: 1)))->toContain([$this->draft->getKey(), 4, 3.0]);
    });

    it('needs a threshold other than zero', function (): void {
        views(Post::class)->period($this->hour)->anomalies(threshold: 0);
    })->throws(InvalidBaseline::class, 'anomalies() needs a threshold above 0 for spikes or below 0 for drops, 0 given.');

    it('needs a period with a start', function (): void {
        views(Post::class)->anomalies();
    })->throws(InvalidBaseline::class, 'A baseline needs a period with a start.');
});

describe('againstBaseline', function (): void {
    it('compares one model with the same hour on past weeks', function (): void {
        expect(views($this->breaking)->period($this->hour)->againstBaseline())
            ->current->toBe(60)
            ->references->toBe([5, 5, 5, 5])
            ->and(views($this->landing)->period($this->hour)->againstBaseline(Seasonality::Day, 1))
            ->current->toBe(2)
            ->references->toBe([0]);
    });

    it('remembers every count under its own key', function (): void {
        $first = views($this->breaking)->period(Period::subHours(1))->remember(600)->againstBaseline();

        growthViews($this->breaking, '2026-10-08 12:00:00', 5);

        expect(views($this->breaking)->period(Period::subHours(1))->remember(600)->againstBaseline())->toEqual($first);
    });

    it('needs a period with a start', function (): void {
        views($this->breaking)->againstBaseline();
    })->throws(InvalidBaseline::class, 'A baseline needs a period with a start.');
});

it('refuses to rank one model', function (string $method): void {
    views($this->breaking)->period($this->hour)->{$method}();
})->with(['rising', 'anomalies'])->throws(InvalidViewable::class);

it('refuses a limit below one', function (): void {
    views(Post::class)->period($this->hour)->rising(0);
})->throws(InvalidLimit::class, 'rising() needs a limit of at least one, 0 given.');

it('refuses a minimum below one', function (): void {
    views(Post::class)->period($this->hour)->anomalies(minimum: 0);
})->throws(InvalidBaseline::class, 'anomalies() needs a minimum of at least one view, 0 given.');

it('remembers a ranking until the moment remember() names', function (): void {
    $first = growthEntries(views(Post::class)->period(Period::subHours(1))->remember(600)->rising(minimum: 1));

    growthViews($this->draft, '2026-10-08 12:10:00', 100);

    expect(growthEntries(views(Post::class)->period(Period::subHours(1))->remember(600)->rising(minimum: 1)))->toBe($first);
});

it('reads the rollups for windows whose views are gone', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => null]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-10-02'), 1_000);

    expect(View::query()->where('viewed_at', '<', '2026-10-02')->exists())->toBeFalse()
        ->and(growthEntries(views(Post::class)->period($this->hour)->anomalies()))->toBe([[$this->breaking->getKey(), 60, 24.6]])
        ->and(growthEntries(views(Post::class)->period($this->hour)->anomalies(threshold: -3)))->toBe([[$this->landing->getKey(), 2, -6.01]]);
});

it('counts like the database in the fake', function (): void {
    $records = View::query()->get()->map(static fn (View $view): ViewRecord => new ViewRecord(
        $view->viewable_id,
        $view->viewable_type,
        $view->visitor,
        $view->collection,
        Carbon::parse($view->getRawOriginal('viewed_at')),
    ));

    $database = [
        growthEntries(views(Post::class)->period($this->hour)->rising()),
        growthEntries(views(Post::class)->period($this->hour)->unique()->anomalies(threshold: -1)),
    ];

    Views::fake()->storeMany($records);

    expect([
        growthEntries(views(Post::class)->period($this->hour)->rising()),
        growthEntries(views(Post::class)->period($this->hour)->unique()->anomalies(threshold: -1)),
    ])->toBe($database);
});

it('needs a source that counts by window', function (): void {
    app()->instance(ViewSource::class, new class implements ViewSource
    {
        public function count(Viewable $viewable, ViewsQuery $query): int
        {
            return 0;
        }

        public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
        {
            return [];
        }

        public function countByCollection(Viewable $viewable, ViewsQuery $query): array
        {
            return [];
        }

        public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
        {
            return [];
        }

        public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
        {
            return [];
        }
    });

    expect(fn (): Ranking => views(Post::class)->period($this->hour)->rising())->toThrow(UnsupportedBySource::class, 'cannot count by window')
        ->and(fn (): Ranking => views(Post::class)->period($this->hour)->remember(60)->anomalies())->toThrow(UnsupportedBySource::class, 'cannot count by window');
});
