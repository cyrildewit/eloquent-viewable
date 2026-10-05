<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Actions;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Maintenance\Data\MaintenanceRun;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\RollupsNotInstalled;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use JsonException;

/**
 * This action runs every configured step in order under one lock: it folds
 * the rollups and expires their old tiers, anonymises and prunes the views
 * the rollups have captured, and recounts the counter columns. The order is
 * what keeps it safe, because anonymising and pruning never go past what the
 * rollups have folded.
 *
 * All steps share one deadline. Once it passes, the run stops before the next
 * unit of work and leaves the rest, in the same order, to the next run.
 */
final readonly class MaintainViews
{
    public function __construct(
        private FoldViews $fold,
        private ExpireTiers $expire,
        private RecountChangedViews $recount,
        private RetentionPolicy $retention,
        private RollupPolicy $rollups,
        private Config $config,
        private Watermarks $watermarks,
        private RunLock $lock,
        private Container $container,
    ) {}

    /** @throws InvalidConfiguration */
    public function isConfigured(): bool
    {
        return $this->rollups->isEnabled() || $this->retains() || $this->config->counters() !== [];
    }

    /**
     * It returns null when another run holds the lock. Whatever a step throws
     * is thrown from here, such as `RollupsNotInstalled` or
     * `RetentionNotInstalled` when a migration has not run.
     *
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    public function handle(int $chunk, ?Deadline $deadline = null, bool $dryRun = false): ?MaintenanceRun
    {
        /** @var ?MaintenanceRun */
        return $this->lock->run(fn (Deadline $deadline): MaintenanceRun => $this->run($chunk, $deadline, $dryRun), $deadline);
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws InvalidTimezone
     * @throws JsonException
     * @throws RetentionNotInstalled
     * @throws RollupsNotInstalled
     * @throws UnsupportedBySource
     */
    private function run(int $chunk, Deadline $deadline, bool $dryRun): MaintenanceRun
    {
        $folded = [];
        $expired = [];

        if ($this->rollups->isEnabled()) {
            $folded = $this->fold->handle(dryRun: $dryRun, deadline: $deadline);

            if (array_any($folded, fn (ViewsRolledUp $run): bool => $run->stopped)) {
                return new MaintenanceRun($folded, stopped: true);
            }

            $expired = $this->expire->handle($chunk, $dryRun, $deadline);

            if (array_any($expired, fn (array $tier): bool => $tier['stopped'])) {
                return new MaintenanceRun($folded, $expired, stopped: true);
            }
        }

        [$anonymise, $prune] = $this->retentionActions($dryRun);
        $now = Carbon::now();
        $anonymised = null;
        $pruned = null;

        if ($this->retention->anonymiseAfter instanceof Duration) {
            $anonymised = $anonymise->handle($this->retention->anonymiseAfter->before($now), $this->retention->anonymiseColumns, $chunk, $dryRun, $deadline);

            if ($anonymised->stopped) {
                return new MaintenanceRun($folded, $expired, $anonymised, stopped: true);
            }
        }

        if ($this->retention->pruneAfter instanceof Duration) {
            $pruned = $prune->handle($this->retention->pruneAfter->before($now), $chunk, $dryRun, $deadline);

            if ($pruned->stopped) {
                return new MaintenanceRun($folded, $expired, $anonymised, $pruned, stopped: true);
            }
        }

        if ($dryRun) {
            return new MaintenanceRun($folded, $expired, $anonymised, $pruned);
        }

        if ($this->config->counters() === []) {
            return new MaintenanceRun($folded, $expired, $anonymised, $pruned);
        }

        $recounted = $this->recount->handle($chunk, $deadline);

        return new MaintenanceRun($folded, $expired, $anonymised, $pruned, $recounted, $recounted->stopped);
    }

    private function retains(): bool
    {
        return $this->retention->anonymiseAfter instanceof Duration || $this->retention->pruneAfter instanceof Duration;
    }

    /**
     * A dry run folds nothing, so anonymising and pruning are held back where
     * the rollups will stand once the real run has folded them.
     *
     * @return array{AnonymiseViews, PruneViews}
     */
    private function retentionActions(bool $dryRun): array
    {
        if (! $dryRun) {
            return [$this->container->make(AnonymiseViews::class), $this->container->make(PruneViews::class)];
        }

        $watermarks = $this->watermarks->afterFolding();

        return [
            $this->container->make(AnonymiseViews::class, ['watermarks' => $watermarks]),
            $this->container->make(PruneViews::class, ['watermarks' => $watermarks]),
        ];
    }
}
