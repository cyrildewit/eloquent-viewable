<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->post = Post::factory()->create();

    foreach (['2025-01-01 10:00:00', '2026-01-01 10:00:00', '2026-03-30 10:00:00'] as $viewedAt) {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create(['visitor' => 'visitor-1']);
    }
});

it('registers {0}', function (string $command): void {
    expect(Artisan::all())->toHaveKey($command);
})->with(['views:anonymise', 'views:prune', 'views:maintain']);

it('says so when nothing is configured', function (string $command, string $message): void {
    $this->artisan($command)
        ->expectsOutputToContain($message)
        ->assertSuccessful();

    expect(View::query()->count())->toBe(3);
})->with([
    ['views:anonymise', 'Nothing to anonymise, `retention.anonymise.after` is not set.'],
    ['views:prune', 'Nothing to prune, `retention.prune.after` is not set.'],
    ['views:maintain', 'Nothing to maintain, neither `retention.rollups`, `retention.anonymise.after`, `retention.prune.after` nor `querying.counters` is set.'],
]);

it('prunes after the configured duration', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $this->artisan('views:prune')
        ->expectsOutputToContain('Deleted 2 views viewed before 2026-03-01 12:00:00.')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(1);
});

it('prunes after the duration given on the command line', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $this->artisan('views:prune', ['--older-than' => '1y'])
        ->expectsOutputToContain('Deleted 1 view viewed before 2025-03-31 12:00:00.')
        ->assertSuccessful();
});

it('anonymises after the duration given on the command line', function (): void {
    $this->artisan('views:anonymise', ['--older-than' => '30d'])
        ->expectsOutputToContain('Anonymised 2 views viewed before 2026-03-01 00:00:00.')
        ->assertSuccessful();

    expect(View::query()->where('visitor', 'like', 'a:%')->count())->toBe(2);
});

it('anonymises the configured columns after the configured duration', function (): void {
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    config()->set('eloquent-viewable.retention.anonymise.columns', ['context']);

    $this->artisan('views:anonymise')->assertSuccessful();

    expect(View::query()->where('visitor', 'visitor-1')->count())->toBe(3);
});

it('reports what a dry run would change', function (): void {
    $this->artisan('views:prune', ['--older-than' => '30d', '--dry-run' => true])
        ->expectsOutputToContain('Would have deleted 2 views viewed before 2026-03-01 12:00:00.')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(3);
});

it('takes the chunk size from the command line', function (): void {
    $this->artisan('views:prune', ['--older-than' => '30d', '--chunk' => '1'])
        ->expectsOutputToContain('Deleted 2 views')
        ->assertSuccessful();
});

it('rejects a chunk size that is not a positive integer', function (string $command, string $chunk): void {
    $this->artisan($command, ['--chunk' => $chunk])
        ->expectsOutputToContain('The --chunk option must be a positive integer.')
        ->assertFailed();
})->with(['views:anonymise', 'views:prune', 'views:maintain'])->with(['0', 'many']);

it('rejects an age that is not a duration', function (string $command): void {
    $this->artisan($command, ['--older-than' => 'last year'])
        ->expectsOutputToContain('The --older-than option must be a duration such as `30d` or `2y`.')
        ->assertFailed();
})->with(['views:anonymise', 'views:prune']);

it('warns when the rollups held the cutoff back', function (): void {
    app()->instance(Watermarks::class, new class implements Watermarks
    {
        public function clamp(CarbonInterface $cutoff): CarbonInterface
        {
            return Carbon::parse('2025-06-01 00:00:00');
        }

        public function afterFolding(): Watermarks
        {
            return $this;
        }
    });

    $this->artisan('views:prune', ['--older-than' => '90d'])
        ->expectsOutputToContain('Deleted 1 view viewed before 2025-06-01 00:00:00.')
        ->expectsOutputToContain('Stopped at 2025-06-01 00:00:00, because the rollups have not captured the views after it yet.')
        ->assertSuccessful();
});

it('skips the run while another one holds the lock', function (): void {
    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        $this->artisan('views:prune', ['--older-than' => '90d'])
            ->expectsOutputToContain('Another run is in progress, so this one was skipped.')
            ->assertSuccessful();
    } finally {
        $lock->release();
    }

    expect(View::query()->count())->toBe(3);
});

it('refuses to run on a cache store that cannot lock', function (): void {
    Cache::extend('unlockable', fn () => Cache::repository(Mockery::mock(Store::class)));
    config()->set('cache.stores.unlockable', ['driver' => 'unlockable']);
    config()->set('eloquent-viewable.querying.cache.store', 'unlockable');

    $this->withoutMockingConsoleOutput()->artisan('views:prune', ['--older-than' => '90d']);
})->throws(LockUnavailable::class, 'The `unlockable` cache store cannot hold an atomic lock');

it('names the default store when the cache store is not set', function (): void {
    Cache::extend('unlockable', fn () => Cache::repository(Mockery::mock(Store::class)));
    config()->set('cache.stores.unlockable', ['driver' => 'unlockable']);
    config()->set('cache.default', 'unlockable');

    $this->withoutMockingConsoleOutput()->artisan('views:prune', ['--older-than' => '90d']);
})->throws(LockUnavailable::class, 'The `default` cache store cannot hold an atomic lock');

it('anonymises and then prunes as configured', function (): void {
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    $this->artisan('views:maintain')
        ->expectsOutputToContain('Anonymised 2 views viewed before 2026-03-01 00:00:00.')
        ->expectsOutputToContain('Deleted 1 view viewed before 2025-03-31 12:00:00.')
        ->assertSuccessful();

    expect(View::query()->orderBy('viewed_at')->pluck('visitor')->all())
        ->toHaveCount(2)
        ->sequence(
            fn ($visitor) => $visitor->toStartWith('a:'),
            fn ($visitor) => $visitor->toBe('visitor-1'),
        );
});

it('keeps every count and counter column through a full maintenance run', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    config()->set('eloquent-viewable.retention.anonymise.after', '30d');
    config()->set('eloquent-viewable.retention.prune.after', '60d');
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    $anonymised = View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-15 10:00:00'))->create(['visitor' => 'visitor-1']);

    $this->artisan('views:maintain')->assertSuccessful();

    expect(View::query()->count())->toBe(2)
        ->and($anonymised->refresh()->visitor)->toStartWith('a:')
        ->and(views($this->post)->count())->toBe(4)
        ->and(views($this->post)->period(Period::create('2025-01-01', '2025-02-01'))->count())->toBe(1)
        ->and((int) Post::query()->whereKey($this->post->getKey())->value('cached_views'))->toBe(4);
});

it('reports on a dry run what the real run would delete once it rolled up', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    config()->set('eloquent-viewable.retention.prune.after', '60d');

    $this->artisan('views:maintain', ['--dry-run' => true])
        ->expectsOutputToContain('Would have deleted 2 views viewed before 2026-01-30 12:00:00.')
        ->doesntExpectOutputToContain('Stopped at')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(3)
        ->and(ViewRollup::query()->count())->toBe(0)
        ->and(app(RollupState::class)->snapshot('views')->folded(Tier::Day))->toBeNull()
        ->and(app(RetentionState::class)->get('pruned'))->toBeNull();

    $this->artisan('views:maintain')
        ->expectsOutputToContain('Deleted 2 views viewed before 2026-01-30 12:00:00.')
        ->assertSuccessful();
});

it('warns on a dry run where the real run would stop for the rollups too', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);
    config()->set('eloquent-viewable.retention.anonymise.after', '7d');

    $this->artisan('views:maintain', ['--dry-run' => true])
        ->expectsOutputToContain('Would have anonymised 2 views viewed before 2026-03-01 00:00:00.')
        ->expectsOutputToContain('Stopped at 2026-03-01 00:00:00, because the rollups have not captured the views after it yet.')
        ->assertSuccessful();
});

it('maintains only what is configured', function (string $key, string $value, string $message): void {
    config()->set("eloquent-viewable.retention.{$key}", $value);

    $this->artisan('views:maintain', ['--dry-run' => true])
        ->expectsOutputToContain($message)
        ->assertSuccessful();
})->with([
    ['anonymise.after', '30d', 'Would have anonymised 2 views'],
    ['prune.after', '30d', 'Would have deleted 2 views'],
]);

it('checks the retention config at boot', function (): void {
    config()->set('eloquent-viewable.retention.anonymise.after', '1y');
    config()->set('eloquent-viewable.retention.prune.after', '90d');

    app()->getProvider(EloquentViewableServiceProvider::class)?->boot();
})->throws(InvalidConfiguration::class, 'must not be longer than `retention.prune.after`');

it('publishes the retention migration under a tag of its own', function (): void {
    $retention = array_keys(ServiceProvider::pathsToPublish(EloquentViewableServiceProvider::class, 'eloquent-viewable-retention'));
    $migrations = array_keys(ServiceProvider::pathsToPublish(EloquentViewableServiceProvider::class, 'migrations'));

    expect($retention)->toHaveCount(1)
        ->and($retention[0])->toEndWith('create_view_retention_state_table.php.stub')
        ->and($migrations)->each->not->toContain('retention');
});

it('prunes before a date given on the command line', function (): void {
    $this->artisan('views:prune', ['--before' => '2026-01-01'])
        ->expectsOutputToContain('Deleted 1 view viewed before 2026-01-01 00:00:00.')
        ->assertSuccessful();

    expect(app(RetentionState::class)->get('pruned'))->toBe('2026-01-01 00:00:00');
});

it('rejects a date it cannot read, or a date and an age together', function (array $options, string $message): void {
    $this->artisan('views:prune', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'not a date' => [['--before' => 'the beginning'], 'The --before option must be a date such as `2026-01-01`.'],
    'both' => [['--before' => '2026-01-01', '--older-than' => '30d'], 'Pass either --before or --older-than, not both.'],
]);

it('stops at its time limit', function (string $command, string $verb): void {
    travelOnFirst($verb === 'Deleted' ? 'delete' : 'update', 'views');

    $this->artisan($command, ['--older-than' => '30d', '--chunk' => '1', '--max-seconds' => '60'])
        ->expectsOutputToContain("{$verb} 1 view")
        ->expectsOutputToContain('Stopped at the time limit. The next run carries on from here.')
        ->doesntExpectOutputToContain('because the rollups have not captured')
        ->assertSuccessful();
})->with([
    ['views:prune', 'Deleted'],
    ['views:anonymise', 'Anonymised'],
]);
