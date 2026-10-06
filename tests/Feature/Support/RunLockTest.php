<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\Lock as LockContract;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

const MaintenanceLock = 'cyrildewit.eloquent-viewable.cache:maintenance';

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));
});

/**
 * Binds a cache store whose locks are the given one.
 */
function storeLocking(LockContract $lock): void
{
    $store = Mockery::mock(Store::class, LockProvider::class);
    $store->allows('lock')->andReturn($lock);

    Cache::extend('locking', fn () => Cache::repository($store));
    config()->set('cache.stores.locking', ['driver' => 'locking']);
    config()->set('eloquent-viewable.querying.cache.store', 'locking');
}

it('hands the run its deadline and returns what the run returns', function (): void {
    $result = app(RunLock::class)->run(fn (Deadline $deadline): object => (object) ['passed' => $deadline->passed()], Deadline::in(0));

    expect($result)->toEqual((object) ['passed' => true]);
});

it('keeps the lock of a run that is still working past the hour', function (): void {
    $taken = app(RunLock::class)->run(function (Deadline $deadline): int {
        $deadline->passed();
        $this->travel(50)->minutes();
        $deadline->passed();
        $this->travel(50)->minutes();

        return Cache::lock(MaintenanceLock, 10)->get() ? 1 : 0;
    });

    expect($taken)->toBe(0);
});

it('lets the lock of a run that stopped asking expire after the hour', function (): void {
    $taken = app(RunLock::class)->run(function (): int {
        $this->travel(61)->minutes();

        return Cache::lock(MaintenanceLock, 10)->get() ? 1 : 0;
    });

    expect($taken)->toBe(1);
});

it('keeps the hour it was given on a driver that cannot refresh a lock', function (): void {
    $lock = new class(MaintenanceLock, 3600) extends Lock
    {
        public function acquire(): bool
        {
            return true;
        }

        public function release(): bool
        {
            return true;
        }

        public function forceRelease(): void {}

        protected function getCurrentOwner(): string
        {
            return $this->owner;
        }
    };

    storeLocking($lock);

    $result = app(RunLock::class)->run(function (Deadline $deadline): int {
        $this->travel(2)->minutes();

        return $deadline->passed() ? 1 : 0;
    });

    expect($result)->toBe(0);
});

it('keeps the hour it was given on a lock of its own kind', function (): void {
    $lock = Mockery::mock(LockContract::class);
    $lock->allows('get')->andReturnUsing(fn (Closure $callback): mixed => $callback());

    storeLocking($lock);

    $result = app(RunLock::class)->run(function (Deadline $deadline): int {
        $this->travel(2)->minutes();

        return $deadline->passed() ? 1 : 0;
    });

    expect($result)->toBe(0);
});
