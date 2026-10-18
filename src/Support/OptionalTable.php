<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * The tables a feature needs beyond the views table, each published under a
 * tag of its own, so the shared `migrations` tag keeps publishing only the
 * views table. The doctor and the exceptions use the methods below, so they
 * give one wording for a missing table.
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

    public function tag(): string
    {
        return "eloquent-viewable-{$this->value}";
    }

    /** The migration stub, without its extension. */
    public function migration(): string
    {
        return match ($this) {
            self::Retention => 'create_view_retention_state_table',
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
        return "Publish its migration with `php artisan vendor:publish --tag={$this->tag()}` and run `php artisan migrate`.";
    }

    public function advice(): string
    {
        return 'Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="'.$this->tag().'"` and run `php artisan migrate`.';
    }
}
