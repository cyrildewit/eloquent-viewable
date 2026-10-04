<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->post = Post::factory()->create();

    foreach (['2025-01-15 10:00:00', '2026-03-15 10:00:00', '2026-03-16 10:00:00'] as $viewedAt) {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();
    }
});

it('is registered', function (): void {
    expect(Artisan::all())->toHaveKey('views:rollup');
});

it('says so when no tier is configured', function (): void {
    $this->artisan('views:rollup')
        ->expectsOutputToContain('Nothing to roll up, neither `retention.rollups.tiers` nor `retention.rollups.custom` is set.')
        ->assertSuccessful();
});

it('folds every tier and expires what is past its keep', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1y', 'month' => null]);

    $this->artisan('views:rollup')
        ->expectsOutputToContain('Folded 1 bucket of the month tier, up to 2026-03-01 00:00:00.')
        ->expectsOutputToContain('Folded 3 buckets of the day tier, up to 2026-03-31 00:00:00.')
        ->expectsOutputToContain('Dropped 3 expired rows of the day tier.')
        ->assertSuccessful();

    expect(ViewRollup::query()->where('tier', 'day')->where('grouping', 'type')->count())->toBe(2);
});

it('folds one tier', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    $this->artisan('views:rollup', ['--tier' => 'day'])
        ->expectsOutputToContain('Folded 3 buckets of the day tier')
        ->doesntExpectOutputToContain('month tier')
        ->assertSuccessful();
});

it('folds again from a date', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->artisan('views:rollup')->assertSuccessful();

    $this->artisan('views:rollup', ['--from' => '2026-03-16'])
        ->expectsOutputToContain('Folded 1 bucket of the day tier')
        ->assertSuccessful();
});

it('reports what a dry run would fold', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1y', 'month' => null]);

    $this->artisan('views:rollup', ['--dry-run' => true])
        ->expectsOutputToContain('Would have folded 3 buckets of the day tier')
        ->assertSuccessful();

    expect(ViewRollup::query()->count())->toBe(0);
});

it('reports what a dry run would expire', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->artisan('views:rollup')->assertSuccessful();

    $rows = ViewRollup::query()->count();

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1y']);

    $this->artisan('views:rollup', ['--dry-run' => true])
        ->expectsOutputToContain('Would have dropped 3 expired rows of the day tier.')
        ->assertSuccessful();

    expect(ViewRollup::query()->count())->toBe($rows);
});

it('rejects options it cannot use', function (array $options, string $message): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    $this->artisan('views:rollup', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'unknown tier' => [['--tier' => 'week'], 'The --tier option must name a configured tier: `month`, `day`.'],
    'tier not configured' => [['--tier' => 'hour'], 'The --tier option must name a configured tier'],
    'from' => [['--from' => 'the beginning'], 'The --from option must be a date such as `2025-01-01`.'],
    'chunk' => [['--chunk' => '0'], 'The --chunk option must be a positive integer.'],
    'chunk not a number' => [['--chunk' => 'many'], 'The --chunk option must be a positive integer.'],
]);

it('skips the run while another one holds the lock', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        $this->artisan('views:rollup')
            ->expectsOutputToContain('Another run is in progress, so this one was skipped.')
            ->assertSuccessful();
    } finally {
        $lock->release();
    }

    expect(ViewRollup::query()->count())->toBe(0);
});

it('rolls up before it anonymises and prunes', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.retention.prune.after', '7d');

    $this->artisan('views:maintain')
        ->expectsOutputToContain('Folded 3 buckets of the day tier')
        ->expectsOutputToContain('Deleted 3 views viewed before 2026-03-24 12:00:00.')
        ->assertSuccessful();
});

it('only rolls up when no retention is configured', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->artisan('views:maintain', ['--dry-run' => true])
        ->expectsOutputToContain('Would have folded 3 buckets of the day tier')
        ->doesntExpectOutputToContain('Nothing to maintain')
        ->assertSuccessful();
});

it('stops when rolling up fails', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.retention.prune.after', '7d');

    Artisan::command('views:rollup {--chunk=} {--dry-run}', fn (): int => 1);

    $this->artisan('views:maintain')->assertFailed();

    expect(View::query()->count())->toBe(3);
});

it('checks the rollup config at boot', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => '1y']);

    app()->getProvider(EloquentViewableServiceProvider::class)?->boot();
})->throws(InvalidConfiguration::class, 'The `month` tier of the `views` rollup');

it('publishes the rollups migration under a tag of its own', function (): void {
    $rollups = array_keys(ServiceProvider::pathsToPublish(EloquentViewableServiceProvider::class, 'eloquent-viewable-rollups'));

    expect($rollups)->toHaveCount(1)
        ->and($rollups[0])->toEndWith('create_view_rollups_table.php.stub');
});
