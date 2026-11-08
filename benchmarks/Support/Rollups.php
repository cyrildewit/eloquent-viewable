<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
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

    private const string FoldedUntil = 'bench:folded_until';

    /** @var list<Tier> */
    private const array Tiers = [Tier::Day, Tier::Month];

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
            self::restoreMarks($state);

            return;
        }

        $connection->table('view_rollups')->delete();
        $connection->table(ViewState::Table)->delete();

        Container::getInstance()->make(FoldViews::class)->handle();

        // Without fresh statistics, Postgres reads the rollups of the scopes
        // through the fold index and scans every viewable's buckets per row.
        Statistics::refresh($connection, 'view_rollups');

        $state->put(self::FoldedUntil, json_encode(self::marks(), JSON_THROW_ON_ERROR));
        $state->put(self::FoldedFrom, $marker);
    }

    /**
     * Marks the rollups folded only up to the start of the last day before
     * the anchor, as a run on that morning would have, so the rollup source
     * reads that day from the views table. The rows of that day stay, and
     * the next `fold()` marks them folded again, so a run that stops halfway
     * leaves no other benchmark reading a shortened history.
     */
    public static function leaveLastDayUnfolded(): void
    {
        $rollups = Container::getInstance()->make(RollupState::class);
        $policy = Container::getInstance()->make(RollupPolicy::class);
        $from = Dataset::anchor()->subDay();

        foreach (self::Tiers as $tier) {
            $rollups->putFolded(RollupPolicy::BuiltIn, $tier, $tier->floor($from, $policy->timezone));
        }
    }

    /** @return array<string, string> */
    private static function marks(): array
    {
        $snapshot = Container::getInstance()->make(RollupState::class)->snapshot(RollupPolicy::BuiltIn);
        $marks = [];

        foreach (self::Tiers as $tier) {
            $folded = $snapshot->folded($tier);

            if ($folded instanceof CarbonImmutable) {
                $marks[$tier->value] = $folded->format(StateStore::Format);
            }
        }

        return $marks;
    }

    /**
     * A dataset folded before the marks were saved has them saved now, while
     * nothing can have moved them yet.
     */
    private static function restoreMarks(StateStore $state): void
    {
        $saved = $state->get(self::FoldedUntil);

        if ($saved === null) {
            $state->put(self::FoldedUntil, json_encode(self::marks(), JSON_THROW_ON_ERROR));

            return;
        }

        /** @var array<string, string> $marks */
        $marks = json_decode($saved, true, flags: JSON_THROW_ON_ERROR);
        $rollups = Container::getInstance()->make(RollupState::class);

        foreach ($marks as $tier => $until) {
            $rollups->putFolded(RollupPolicy::BuiltIn, Tier::from($tier), CarbonImmutable::parse($until));
        }
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
