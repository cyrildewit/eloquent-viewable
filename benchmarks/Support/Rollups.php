<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Support\State\ViewState;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * The day and month rollups of the seeded dataset, folded up to its anchor.
 * Every view is then in a rollup, so a read through the rollup source only
 * reads the views table for the hand-over after the last bucket. They are
 * folded once per dataset. The seed they were folded from is noted in the
 * state table, so seeding again folds again.
 */
final class Rollups
{
    private const string FoldedFrom = 'bench:folded_from';

    public static function configure(): void
    {
        $config = Container::getInstance()->make(Repository::class);

        $config->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
        $config->set('eloquent-viewable.retention.rollups.settle');

        self::pinClockToAnchor();
    }

    /**
     * The views end at the anchor, so folding up to it folds them all, on
     * every run, whenever it happens.
     */
    private static function pinClockToAnchor(): void
    {
        CarbonImmutable::setTestNow(Dataset::anchor());
        Carbon::setTestNow(Dataset::anchor());
    }

    public static function fold(ConnectionInterface $connection, Dataset $dataset): void
    {
        self::configure();
        self::install($connection);

        $state = Container::getInstance()->make(StateStore::class);
        $marker = "{$dataset->size->value}:{$dataset->seed}:{$dataset->seededAt}";

        if ($state->get(self::FoldedFrom) === $marker) {
            return;
        }

        $connection->table('view_rollups')->delete();
        $connection->table(ViewState::Table)->delete();

        Container::getInstance()->make(FoldViews::class)->handle();

        $state->put(self::FoldedFrom, $marker);
    }

    /**
     * The rollups table and the state table that rollups keep their marks in.
     */
    public static function install(ConnectionInterface $connection): void
    {
        $stubs = [
            'view_rollups' => 'create_view_rollups_table',
            ViewState::Table => 'create_view_state_table',
        ];

        foreach ($stubs as $table => $stub) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                (require __DIR__."/../../database/migrations/{$stub}.php.stub")->up();
            }
        }
    }
}
