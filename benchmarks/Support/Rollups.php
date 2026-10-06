<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * These are the day and month rollups of the seeded dataset, folded up to its
 * anchor so every view lies in the rollups and the views table answers
 * nothing but the hand-over. They are folded once per dataset: the seed they
 * were folded from is noted in the state table, so seeding again folds again.
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
        $connection->table('view_retention_state')->delete();

        Container::getInstance()->make(FoldViews::class)->handle();

        $state->put(self::FoldedFrom, $marker);
    }

    /**
     * The rollups migration also creates the state table that retention and
     * the counter columns keep their marks in.
     */
    public static function install(ConnectionInterface $connection): void
    {
        if ($connection->getSchemaBuilder()->hasTable('view_rollups')) {
            return;
        }

        require_once __DIR__.'/../../database/migrations/create_view_rollups_table.php.stub';

        new \CreateViewRollupsTable()->up();
    }
}
