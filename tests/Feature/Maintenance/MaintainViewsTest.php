<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Maintenance\Actions\MaintainViews;
use CyrildeWit\EloquentViewable\Maintenance\Data\MaintenanceRun;
use CyrildeWit\EloquentViewable\Maintenance\Jobs\MaintainViewsJob;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->post = Post::factory()->create();

    foreach (['2025-01-15 10:00:00', '2025-02-15 10:00:00', '2025-03-15 10:00:00', '2026-03-15 10:00:00'] as $viewedAt) {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();
    }
});

function maintain(int $chunk = 1, ?Deadline $deadline = null, bool $dryRun = false): ?MaintenanceRun
{
    return app(MaintainViews::class)->handle($chunk, $deadline, $dryRun);
}

it('is configured once any step is', function (string $key, mixed $value): void {
    expect(app(MaintainViews::class)->isConfigured())->toBeFalse();

    config()->set("eloquent-viewable.{$key}", $value);

    expect(app(MaintainViews::class)->isConfigured())->toBeTrue();
})->with([
    'rollups' => ['retention.rollups.tiers', ['day' => null]],
    'anonymising' => ['retention.anonymise.after', '30d'],
    'pruning' => ['retention.prune.after', '30d'],
    'counters' => ['querying.counters', [Post::class => ['cached_views']]],
]);

it('runs every configured step in order', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    config()->set('eloquent-viewable.retention.prune.after', '1y');
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    $run = maintain(100);

    expect($run)->toBeInstanceOf(MaintenanceRun::class)
        ->and($run?->stopped)->toBeFalse()
        ->and($run?->folded)->toHaveCount(2)
        ->and($run?->expired[0]['rows'])->toBe(3)
        ->and($run?->anonymised?->views)->toBe(3)
        ->and($run?->pruned?->views)->toBe(3)
        ->and($run?->recounted?->models)->toBe([Post::class => 1]);
});

it('stops while folding and leaves every later step for the next run', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $run = maintain(deadline: deadlineAfter(0));

    expect($run?->stopped)->toBeTrue()
        ->and($run?->folded[0]->stopped)->toBeTrue()
        ->and($run?->expired)->toBe([])
        ->and($run?->pruned)->toBeNull()
        ->and(View::query()->count())->toBe(4);
});

it('stops while expiring tiers', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);

    $run = maintain(deadline: deadlineAfter(7));

    expect($run?->stopped)->toBeTrue()
        ->and($run?->folded)->toHaveCount(2)
        ->and($run?->expired)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 0, 'stopped' => true]]);
});

it('stops while anonymising and leaves pruning for the next run', function (): void {
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    $run = maintain(deadline: deadlineAfter(0));

    expect($run?->stopped)->toBeTrue()
        ->and($run?->anonymised?->stopped)->toBeTrue()
        ->and($run?->pruned)->toBeNull()
        ->and(View::query()->count())->toBe(4);
});

it('stops while pruning and leaves recounting for the next run', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    $run = maintain(deadline: deadlineAfter(1));

    expect($run?->stopped)->toBeTrue()
        ->and($run?->pruned?->views)->toBe(1)
        ->and($run?->recounted)->toBeNull();
});

it('stops while recounting', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    $run = maintain(deadline: deadlineAfter(0));

    expect($run?->stopped)->toBeTrue()
        ->and($run?->recounted?->stopped)->toBeTrue();
});

it('returns nothing while another run holds the lock', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        expect(maintain())->toBeNull();
    } finally {
        $lock->release();
    }
});

it('reports where a command stopped at its time limit', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    travelOnFirst('insert', 'view_rollups');

    $this->artisan('views:maintain', ['--max-seconds' => '60'])
        ->expectsOutputToContain('Folded 1 bucket of the day tier, up to 2025-01-16 00:00:00.')
        ->expectsOutputToContain('Stopped at the time limit. The next run carries on from here.')
        ->assertSuccessful();
});

it('reports a stop while pruning once', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');
    travelOnFirst('delete', 'views');

    $this->artisan('views:maintain', ['--max-seconds' => '60', '--chunk' => '1'])
        ->expectsOutputToContain('Deleted 1 view viewed before 2026-03-01 12:00:00.')
        ->expectsOutputToContain('Stopped at the time limit. The next run carries on from here.')
        ->assertSuccessful();
});

it('skips the command while another run holds the lock', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        $this->artisan('views:maintain')
            ->expectsOutputToContain('Another run is in progress, so this one was skipped.')
            ->assertSuccessful();
    } finally {
        $lock->release();
    }
});

it('rejects a time limit that is not a positive integer', function (string $command, string $seconds): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->artisan($command, ['--max-seconds' => $seconds])
        ->expectsOutputToContain('The --max-seconds option must be a positive integer.')
        ->assertFailed();
})->with(['views:maintain', 'views:rollup', 'views:anonymise', 'views:prune', 'views:recount'])->with(['0', 'soon']);

it('queues itself again when its run stopped at the time limit', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');
    travelOnFirst('delete', 'views');
    Bus::fake();

    app()->call([new MaintainViewsJob(maxSeconds: 60, chunk: 1)->onConnection('redis')->onQueue('maintenance'), 'handle']);

    expect(View::query()->count())->toBe(3);

    Bus::assertDispatched(MaintainViewsJob::class, fn (MaintainViewsJob $job): bool => $job->maxSeconds === 60
        && $job->chunk === 1
        && $job->connection === 'redis'
        && $job->queue === 'maintenance');
});

it('does not queue itself again once the work is done', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');
    Bus::fake();

    app()->call([new MaintainViewsJob, 'handle']);

    expect(View::query()->count())->toBe(1);

    Bus::assertNotDispatched(MaintainViewsJob::class);
});

it('does nothing when nothing is configured', function (): void {
    Bus::fake();

    app()->call([new MaintainViewsJob, 'handle']);

    expect(View::query()->count())->toBe(4);

    Bus::assertNotDispatched(MaintainViewsJob::class);
});

it('leaves the work to the run that holds the lock', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');
    Bus::fake();

    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        app()->call([new MaintainViewsJob, 'handle']);
    } finally {
        $lock->release();
    }

    expect(View::query()->count())->toBe(4);

    Bus::assertNotDispatched(MaintainViewsJob::class);
});

it('stays unique in the queue until it starts', function (): void {
    expect(new MaintainViewsJob)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});

it('reports every step it ran', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);
    config()->set('eloquent-viewable.retention.prune.after', '1y');
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    $this->artisan('views:maintain')
        ->expectsOutputToContain('Folded 3 buckets of the month tier')
        ->expectsOutputToContain('Dropped 3 expired rows of the day tier.')
        ->expectsOutputToContain('Deleted 3 views')
        ->expectsOutputToContain('Recounted 1 Post.')
        ->doesntExpectOutputToContain('Stopped at the time limit')
        ->assertSuccessful();
});

it('reports a stop while anonymising once', function (): void {
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    travelOnFirst('update', 'views');

    $this->artisan('views:maintain', ['--max-seconds' => '60', '--chunk' => '1'])
        ->expectsOutputToContain('Anonymised 1 view')
        ->expectsOutputToContain('Stopped at the time limit. The next run carries on from here.')
        ->assertSuccessful();
});
