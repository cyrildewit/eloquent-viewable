<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\UnknownRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\Rollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups\NewsletterViews;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups\SearchViews;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.retention.rollups.custom', [NewsletterViews::class]);

    $this->post = Post::factory()->create();

    foreach ([
        ['2026-01-10 10:00:00', 'visitor-1', ['source' => 'newsletter', 'campaign' => 'spring']],
        ['2026-01-10 11:00:00', 'visitor-1', ['source' => 'newsletter', 'campaign' => 'winter']],
        ['2026-01-12 11:00:00', 'visitor-2', ['source' => 'newsletter', 'campaign' => 'spring']],
        ['2026-02-02 11:00:00', 'visitor-3', ['source' => 'newsletter']],
        ['2026-02-03 11:00:00', 'visitor-1', ['source' => 'search']],
        ['2026-02-04 11:00:00', 'visitor-4', null],
        ['2026-03-30 11:00:00', 'visitor-5', ['source' => 'newsletter', 'campaign' => 'spring']],
    ] as [$viewedAt, $visitor, $context]) {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->withContext($context)->create();
    }
});

/** @return array<string, mixed> */
function newsletterReads(Post $post): array
{
    return [
        'count' => views($post)->rollup('newsletter')->count(),
        'january' => views($post)->rollup('newsletter')->period(Period::create('2026-01-01', '2026-02-01'))->count(),
        'unique in january' => views($post)->rollup('newsletter')->unique()->period(Period::create('2026-01-01', '2026-02-01'))->count(),
        'monthly' => views($post)->rollup('newsletter')->period(Period::create('2026-01-01', '2026-04-01'))->countByInterval(Granularity::Month)->values(),
        'type' => views(new Post)->rollup('newsletter')->count(),
        'by campaign' => views($post)->rollup('newsletter')->countByDimension(),
        'everything' => views($post)->count(),
    ];
}

it('counts only the views the filter keeps from the views table', function (): void {
    expect(newsletterReads($this->post))->toBe([
        'count' => 5,
        'january' => 3,
        'unique in january' => 2,
        'monthly' => [3, 1, 1],
        'type' => 5,
        'by campaign' => ['spring' => 3, '' => 1, 'winter' => 1],
        'everything' => 7,
    ]);
});

it('folds its views under its own name, in totals and per value of its dimension', function (): void {
    app(FoldViews::class)->handle();

    $row = fn (string $grouping, ?string $dimension = null): ?array => ViewRollup::query()
        ->where('rollup', 'newsletter')->where('tier', 'month')->where('bucket_start', Carbon::parse('2026-01-01'))
        ->where('grouping', $grouping)->where('dimension', $dimension)
        ->first(['views', 'unique_visitors'])?->only(['views', 'unique_visitors']);

    expect($row('viewable'))->toBe(['views' => 3, 'unique_visitors' => 2])
        ->and($row('viewable:dimension', 'spring'))->toBe(['views' => 2, 'unique_visitors' => 2])
        ->and($row('viewable:dimension', 'winter'))->toBe(['views' => 1, 'unique_visitors' => 1])
        ->and($row('type'))->toBe(['views' => 3, 'unique_visitors' => 2])
        ->and($row('viewable_collection'))->toBeNull()
        ->and(ViewRollup::query()->where('rollup', 'views')->count())->toBe(0);
});

it('answers from its rollups like the views table once the views are folded and deleted', function (): void {
    $expected = newsletterReads($this->post);

    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    expect(newsletterReads($this->post))->toBe([...$expected, 'everything' => 1]);
});

it('counts a dimension from the views table through a source without rollups for it', function (): void {
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    expect(views($this->post)->rollup('newsletter')->countByDimension())->toBe(['spring' => 3, '' => 1, 'winter' => 1]);
});

it('keeps the counts of a rollup apart in the cache', function (): void {
    expect(views($this->post)->remember(3600)->count())->toBe(7)
        ->and(views($this->post)->rollup('newsletter')->remember(3600)->count())->toBe(5);
});

it('reads through the built-in rollup again once the rollup is cleared', function (): void {
    expect(views($this->post)->rollup('newsletter')->rollup(null)->count())->toBe(7);
});

it('waits for every custom rollup before anything is anonymised or pruned', function (): void {
    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-01'))->getTimestamp())->toBe(0);

    app(FoldViews::class)->handle();

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2026-03-01 00:00:00');
});

it('removes the rows per value of a model whose views are destroyed', function (): void {
    app(FoldViews::class)->handle();

    views($this->post)->destroy();

    expect(ViewRollup::query()->where('grouping', 'like', 'viewable%')->count())->toBe(0)
        ->and(ViewRollup::query()->where('grouping', 'type')->count())->toBeGreaterThan(0);
});

it('folds one rollup when named', function (): void {
    config()->set('eloquent-viewable.retention.rollups.custom', [NewsletterViews::class, SearchViews::class]);
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->artisan('views:rollup', ['--rollup' => 'search'])
        ->expectsOutputToContain('Folded 2 buckets of the month tier of `search`')
        ->doesntExpectOutputToContain('newsletter')
        ->assertSuccessful();

    $this->artisan('views:rollup', ['--rollup' => 'views', '--tier' => 'day'])
        ->expectsOutputToContain('buckets of the day tier,')
        ->assertSuccessful();
});

it('rejects a rollup or a tier it does not know', function (array $options, string $message): void {
    $this->artisan('views:rollup', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'rollup' => [['--rollup' => 'search'], 'The --rollup option must name a configured rollup: `newsletter`.'],
    'tier of the rollup' => [['--rollup' => 'newsletter', '--tier' => 'hour'], 'The --tier option must name a configured tier: `month`, `day`.'],
]);

it('keeps the defaults of a rollup that only names its tiers', function (): void {
    $rollup = new SearchViews;
    $views = View::query();
    $rollup->filter($views);

    expect($rollup->groupings())->toBe(['viewable', 'type'])
        ->and($rollup->dimension())->toBeNull()
        ->and($rollup->name())->toBe('search')
        ->and($views->toBase()->wheres)->toBeEmpty();
});

it('refuses a rollup it does not know', function (): void {
    views($this->post)->rollup('campaigns');
})->throws(UnknownRollup::class, 'No custom rollup is named `campaigns`. List its class under `eloquent-viewable.retention.rollups.custom`.');

it('refuses to count by dimension without one', function (?string $rollup, string $message): void {
    config()->set('eloquent-viewable.retention.rollups.custom', [NewsletterViews::class, SearchViews::class]);

    expect(fn (): array => views($this->post)->rollup($rollup)->countByDimension())->toThrow(UnknownRollup::class, $message);
})->with([
    'no rollup' => [null, 'Counting by dimension needs a custom rollup with a dimension. Call `rollup()` with its name first.'],
    'no dimension' => ['search', 'The `search` rollup has no dimension to count by.'],
]);

it('refuses to count by dimension through a source that cannot', function (): void {
    app()->instance(ViewSource::class, new readonly class(app(DatabaseSource::class)) implements ViewSource
    {
        public function __construct(private DatabaseSource $source) {}

        public function count(Viewable $viewable, ViewsQuery $query): int
        {
            return $this->source->count($viewable, $query);
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

        public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
        {
            return $this->source->countSubquery($viewable, $query);
        }

        public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
        {
            return [];
        }
    });

    views($this->post)->rollup('newsletter')->countByDimension();
})->throws(UnsupportedBySource::class, 'cannot count by dimension, so countByDimension() cannot read from it.');

it('refuses a rollup in the fake', function (Closure $read, string $message): void {
    ViewsFacade::fake();

    expect(fn () => $read($this->post))->toThrow(UnsupportedBySource::class, $message);
})->with([
    'count' => [fn (Post $post): int => views($post)->rollup('newsletter')->count(), 'cannot apply the filter of a rollup'],
    'by dimension' => [fn (Post $post): array => views($post)->rollup('newsletter')->countByDimension(), 'cannot count by dimension'],
    'remembered by dimension' => [fn (Post $post): array => views($post)->rollup('newsletter')->remember(3600)->countByDimension(), 'cannot count by dimension'],
]);

describe('config', function (): void {
    it('refuses a custom rollup that is not valid', function (string|Rollup $rollup, string $problem): void {
        $class = is_string($rollup) ? $rollup : $rollup::class;

        if (! is_string($rollup)) {
            app()->instance($class, $rollup);
        }

        config()->set('eloquent-viewable.retention.rollups.custom', [$class]);

        expect(fn (): RollupPolicy => app(RollupPolicy::class))
            ->toThrow(InvalidConfiguration::class, "The `{$class}` rollup in `eloquent-viewable.retention.rollups.custom` {$problem}.");
    })->with([
        'not a rollup' => [Post::class, 'must extend `'.Rollup::class.'`'],
        'no name' => [fn (): Rollup => new class extends Rollup
        {
            public function tiers(): array
            {
                return ['day' => null];
            }
        }, 'must have a `name` of letters, digits, `-` and `_` other than `views`'],
        'the built-in name' => [fn (): Rollup => new class extends Rollup
        {
            public string $name = 'views';

            public function tiers(): array
            {
                return ['day' => null];
            }
        }, 'must have a `name` of letters, digits, `-` and `_` other than `views`'],
        'a week' => [fn (): Rollup => new class extends Rollup
        {
            public string $name = 'weekly';

            public function tiers(): array
            {
                return ['week' => null];
            }
        }, 'must map `hour`, `day`, `month` or `year` to a duration such as `2y`, or null, in `tiers()`'],
        'a bad duration' => [fn (): Rollup => new class extends Rollup
        {
            public string $name = 'forever';

            public function tiers(): array
            {
                return ['day' => 'forever'];
            }
        }, 'must map `hour`, `day`, `month` or `year` to a duration such as `2y`, or null, in `tiers()`'],
        'no tiers' => [fn (): Rollup => new class extends Rollup
        {
            public string $name = 'empty';

            public function tiers(): array
            {
                return [];
            }
        }, 'must keep at least one tier, and at least one grouping of `viewable`, `viewable_collection`, `type`, `type_collection`'],
        'an unknown grouping' => [fn (): Rollup => new class extends Rollup
        {
            public string $name = 'viewers';

            public function tiers(): array
            {
                return ['day' => null];
            }

            public function groupings(): array
            {
                return ['viewer'];
            }
        }, 'must keep at least one tier, and at least one grouping of `viewable`, `viewable_collection`, `type`, `type_collection`'],
    ]);

    it('refuses two rollups of one name', function (): void {
        config()->set('eloquent-viewable.retention.rollups.custom', [NewsletterViews::class, NewsletterViews::class]);

        app(RollupPolicy::class);
    })->throws(InvalidConfiguration::class, 'is named `newsletter`, which another rollup is named already');

    it('refuses a custom rollup tier kept shorter than a finer one', function (): void {
        app()->instance('short-months', new class extends Rollup
        {
            public string $name = 'short';

            public function tiers(): array
            {
                return ['day' => '1y', 'month' => '30d'];
            }
        });
        config()->set('eloquent-viewable.retention.rollups.custom', [app('short-months')::class]);

        app()->bind(app('short-months')::class, fn () => app('short-months'));

        app(RollupPolicy::class);
    })->throws(InvalidConfiguration::class, 'The `month` tier of the `short` rollup');

    it('refuses custom rollups that are not classes', function (mixed $value): void {
        config()->set('eloquent-viewable.retention.rollups.custom', $value);

        app(RollupPolicy::class);
    })->throws(InvalidConfiguration::class, 'The `eloquent-viewable.retention.rollups.custom` config value must be a list of class names')
        ->with(['string' => [NewsletterViews::class], 'missing class' => [['App\\Rollups\\Missing']]]);

    it('checks custom rollups at boot', function (): void {
        config()->set('eloquent-viewable.retention.rollups.custom', [Post::class]);

        app()->getProvider(EloquentViewableServiceProvider::class)?->boot();
    })->throws(InvalidConfiguration::class, 'must extend');
});
