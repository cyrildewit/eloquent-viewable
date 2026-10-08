<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Spikes\Actions\DetectSpikes;
use CyrildeWit\EloquentViewable\Spikes\Direction;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsDropped;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSettled;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSpiked;
use CyrildeWit\EloquentViewable\Spikes\Exceptions\SpikesNotInstalled;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * It is Thursday 8 October, half past noon, so the window is 11 to 12. The
 * breaking story gets 60 views where it got 5 on past Thursdays, and the
 * landing page 2 where it got 40.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-08 12:30:00'));

    config()->set('eloquent-viewable.spikes.types', [Post::class => ['drops' => true, 'cooldown' => '2h']]);

    $this->breaking = Post::factory()->create();
    $this->landing = Post::factory()->create();

    foreach (['2026-10-01', '2026-09-24', '2026-09-17', '2026-09-10'] as $day) {
        spikeViews($this->breaking, "{$day} 11:15:00", 5);
        spikeViews($this->landing, "{$day} 11:15:00", 40);
    }

    spikeViews($this->breaking, '2026-10-08 11:15:00', 60);
    spikeViews($this->landing, '2026-10-08 11:15:00', 2);

    Event::fake([ViewsSpiked::class, ViewsDropped::class, ViewsSettled::class]);
});

function spikeViews(Model&Viewable $viewable, string $viewedAt, int $count): void
{
    View::factory()->count($count)->for($viewable, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();
}

/** @return list<array<string, mixed>> */
function episodes(): array
{
    return DB::table('view_spikes')
        ->orderBy('direction')
        ->get(['viewable_id', 'direction', 'since', 'peak_count', 'quiet_since'])
        ->map(fn (stdClass $row): array => [(int) $row->viewable_id, $row->direction, (string) $row->since, (int) $row->peak_count, $row->quiet_since === null ? null : (string) $row->quiet_since])
        ->all();
}

it('dispatches once when a model spikes or drops', function (): void {
    $this->artisan('views:detect-spikes')
        ->expectsOutputToContain('Watched Posts: 1 spiked, 1 dropped, 0 settled.')
        ->assertSuccessful();

    Event::assertDispatchedTimes(ViewsSpiked::class, 1);
    Event::assertDispatched(ViewsSpiked::class, fn (ViewsSpiked $event): bool => $event->is($this->breaking)
        && $event->viewable()?->is($this->breaking) === true
        && $event->baseline->current === 60
        && $event->baseline->references === [5, 5, 5, 5]);
    Event::assertDispatched(ViewsDropped::class, fn (ViewsDropped $event): bool => $event->is($this->landing)
        && $event->baseline->current === 2);

    expect(episodes())->toBe([
        [$this->landing->getKey(), 'drop', '2026-10-08 12:00:00', 2, null],
        [$this->breaking->getKey(), 'spike', '2026-10-08 12:00:00', 60, null],
    ]);

    app(DetectSpikes::class)->handle();

    Event::assertDispatchedTimes(ViewsSpiked::class, 1);
    Event::assertDispatchedTimes(ViewsDropped::class, 1);
});

it('keeps the peak while a model stays out, and settles once it has been normal for the cooldown', function (): void {
    app(DetectSpikes::class)->handle();

    foreach (['2026-10-01', '2026-09-24', '2026-09-17', '2026-09-10'] as $day) {
        spikeViews($this->landing, "{$day} 12:15:00", 40);
    }

    spikeViews($this->breaking, '2026-10-08 12:15:00', 80);
    spikeViews($this->landing, '2026-10-08 12:15:00', 1);
    $this->travelTo(Carbon::parse('2026-10-08 13:30:00'));
    app(DetectSpikes::class)->handle();

    expect(episodes())->toBe([
        [$this->landing->getKey(), 'drop', '2026-10-08 12:00:00', 1, null],
        [$this->breaking->getKey(), 'spike', '2026-10-08 12:00:00', 80, null],
    ]);

    $this->travelTo(Carbon::parse('2026-10-08 14:30:00'));
    app(DetectSpikes::class)->handle();

    expect(episodes()[1][4])->toBe('2026-10-08 14:00:00');

    $this->travelTo(Carbon::parse('2026-10-08 15:30:00'));
    app(DetectSpikes::class)->handle();

    Event::assertNotDispatched(ViewsSettled::class);

    $this->travelTo(Carbon::parse('2026-10-08 16:30:00'));
    $runs = app(DetectSpikes::class)->handle();

    expect($runs[0])
        ->spiked->toBe(0)
        ->settled->toBe(2)
        ->and(episodes())->toBeEmpty();

    Event::assertDispatched(ViewsSettled::class, fn (ViewsSettled $event): bool => $event->is($this->breaking)
        && $event->direction === Direction::Spike
        && $event->peakCount === 80
        && $event->peakScore > 30
        && $event->since->format('Y-m-d H:i') === '2026-10-08 12:00');
    Event::assertDispatched(ViewsSettled::class, fn (ViewsSettled $event): bool => $event->is($this->landing)
        && $event->direction === Direction::Drop
        && $event->peakCount === 1);
});

it('is no longer quiet once it leaves its baseline again', function (): void {
    app(DetectSpikes::class)->handle();

    $this->travelTo(Carbon::parse('2026-10-08 13:30:00'));
    app(DetectSpikes::class)->handle();

    spikeViews($this->breaking, '2026-10-08 13:15:00', 50);
    $this->travelTo(Carbon::parse('2026-10-08 14:30:00'));
    app(DetectSpikes::class)->handle();

    expect(episodes()[1])->toBe([$this->breaking->getKey(), 'spike', '2026-10-08 12:00:00', 50, null]);
});

it('watches drops only when asked', function (): void {
    config()->set('eloquent-viewable.spikes.types', [Post::class => []]);

    $this->artisan('views:detect-spikes')
        ->expectsOutputToContain('Watched Posts: 1 spiked, 0 dropped, 0 settled.')
        ->assertSuccessful();

    Event::assertNotDispatched(ViewsDropped::class);
});

it('says when there is nothing to watch', function (): void {
    config()->set('eloquent-viewable.spikes.types', []);

    expect(app(DetectSpikes::class)->handle())->toBeEmpty();

    $this->artisan('views:detect-spikes')
        ->expectsOutputToContain('Nothing to watch, `spikes.types` is empty.')
        ->assertSuccessful();
});

it('is registered', function (): void {
    expect(Artisan::all())->toHaveKey('views:detect-spikes');
});

it('fails when the table is missing', function (): void {
    config()->set('eloquent-viewable.spikes.table', 'missing_spikes');

    app(DetectSpikes::class)->handle();
})->throws(SpikesNotInstalled::class, 'Spikes are configured, but the `missing_spikes` table does not exist.');

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

    app(DetectSpikes::class)->handle();
})->throws(UnsupportedBySource::class, 'cannot count by window');
