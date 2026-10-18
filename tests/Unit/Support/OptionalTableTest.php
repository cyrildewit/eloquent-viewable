<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\OptionalTable;

it('publishes {0} under its own tag', function (OptionalTable $table, string $migration): void {
    expect($table->tag())->toBe("eloquent-viewable-{$table->value}")
        ->and($table->migration())->toBe($migration);
})->with([
    'retention' => [OptionalTable::Retention, 'create_view_retention_state_table'],
    'rollups' => [OptionalTable::Rollups, 'create_view_rollups_table'],
    'pairs' => [OptionalTable::Pairs, 'create_view_pairs_table'],
    'milestones' => [OptionalTable::Milestones, 'create_view_milestones_table'],
    'spikes' => [OptionalTable::Spikes, 'create_view_spikes_table'],
]);

it('says which feature misses {0}', function (OptionalTable $table, string $message): void {
    expect($table->missing('a_table'))->toBe("{$message}, but the `a_table` table does not exist.")
        ->and($table->notInstalled('a_table'))->toBe("{$message}, but the `a_table` table does not exist. Publish its migration with `php artisan vendor:publish --tag={$table->tag()}` and run `php artisan migrate`.")
        ->and($table->advice())->toBe('Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="'.$table->tag().'"` and run `php artisan migrate`.');
})->with([
    'retention' => [OptionalTable::Retention, 'Retention is configured'],
    'rollups' => [OptionalTable::Rollups, 'Rollups are configured'],
    'pairs' => [OptionalTable::Pairs, 'Pairs are configured'],
    'milestones' => [OptionalTable::Milestones, 'Milestones are configured'],
    'spikes' => [OptionalTable::Spikes, 'Spikes are configured'],
]);
