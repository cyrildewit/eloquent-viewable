<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\State\ViewState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));
});

function stateReads(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $reads = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], ViewState::Table));
    DB::disableQueryLog();

    return count($reads);
}

it('is one instance per scope', function (): void {
    $state = app(StateStore::class);

    expect(app(StateStore::class))->toBe($state);

    app()->forgetScopedInstances();

    expect(app(StateStore::class))->not->toBe($state);
});

it('reads the table once for many reads', function (): void {
    $state = app(StateStore::class);
    $state->put(StateStore::Pruned, '2026-01-01 00:00:00');
    $state->put('other', 'value');

    $reads = stateReads(function () use ($state): void {
        expect($state->many([StateStore::Pruned, StateStore::Anonymised]))->toBe([StateStore::Pruned => '2026-01-01 00:00:00'])
            ->and($state->get('other'))->toBe('value')
            ->and($state->get(StateStore::Anonymised))->toBeNull();

        app(RollupState::class)->snapshot(RollupPolicy::BuiltIn);
        app(RollupState::class)->snapshot(RollupPolicy::BuiltIn);
    });

    expect($reads)->toBe(1);
});

it('reads its own writes', function (): void {
    $state = app(StateStore::class);

    expect($state->get('name'))->toBeNull();

    $state->put('name', 'first');

    expect($state->get('name'))->toBe('first');

    $state->put('name', 'second');

    expect($state->get('name'))->toBe('second');

    $state->forget('name');

    expect($state->get('name'))->toBeNull();
});

it('sees the mark a prune moved in the same process', function (): void {
    $snapshot = app(RollupState::class)->snapshot(RollupPolicy::BuiltIn);

    expect($snapshot->pruned)->toBeNull();

    app(PruneViews::class)->handle(Carbon::parse('2026-03-01 00:00:00'), 100);

    expect(app(RollupState::class)->snapshot(RollupPolicy::BuiltIn)->pruned?->toDateTimeString())->toBe('2026-03-01 00:00:00');
});

it('reads the table again once its values are too old', function (): void {
    $state = app(StateStore::class);

    expect($state->get('name'))->toBeNull();

    DB::table(ViewState::Table)->insert(['name' => 'name', 'value' => 'from another process']);

    $this->travel(4)->seconds();

    expect($state->get('name'))->toBeNull();

    $this->travel(1)->seconds();

    expect($state->get('name'))->toBe('from another process');
});

it('reads the table again when the clock moved back', function (): void {
    $state = app(StateStore::class);

    expect($state->get('name'))->toBeNull();

    DB::table(ViewState::Table)->insert(['name' => 'name', 'value' => 'from another process']);

    $this->travel(-1)->seconds();

    expect($state->get('name'))->toBe('from another process');
});
