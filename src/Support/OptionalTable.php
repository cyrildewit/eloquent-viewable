<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * The tables a feature needs beyond the views table. Every one is published
 * with the views table, but an application may delete the migration of a
 * feature it does not use. The doctor and the exceptions use the methods
 * below, so they give one wording for a missing table.
 *
 * @internal
 */
enum OptionalTable: string
{
    case Retention = 'retention';

    case Rollups = 'rollups';

    case Pairs = 'pairs';

    case Milestones = 'milestones';

    case Spikes = 'spikes';

    /** The migration stub, without its extension. */
    public function migration(): string
    {
        return match ($this) {
            self::Retention => 'create_view_state_table',
            self::Rollups => 'create_view_rollups_table',
            self::Pairs => 'create_view_pairs_table',
            self::Milestones => 'create_view_milestones_table',
            self::Spikes => 'create_view_spikes_table',
        };
    }

    public function missing(string $table): string
    {
        $configured = match ($this) {
            self::Retention => 'Retention is configured',
            self::Rollups => 'Rollups are configured',
            self::Pairs => 'Pairs are configured',
            self::Milestones => 'Milestones are configured',
            self::Spikes => 'Spikes are configured',
        };

        return "{$configured}, but the `{$table}` table does not exist.";
    }

    public function notInstalled(string $table): string
    {
        return "{$this->missing($table)} {$this->hint()}";
    }

    public function hint(): string
    {
        return 'Publish the migrations with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"` and run `php artisan migrate`.';
    }
}
