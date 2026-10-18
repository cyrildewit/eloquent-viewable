<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Listeners;

use CyrildeWit\EloquentViewable\Erasure\Events\CountsChanged;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ForgetRollups;
use CyrildeWit\EloquentViewable\Recording\Events\ViewsDestroyed;

/**
 * Keeps the remembered counts, the rollups and the counter columns in step
 * with views that were destroyed, erased or changed by a scheduled run. It
 * is resolved for every event, so under Octane the view source it builds
 * does not stay behind in the worker.
 *
 * @internal
 */
final readonly class ForgetChangedCounts
{
    public function __construct(
        private CacheVersions $versions,
        private ForgetRollups $rollups,
        private RecountChangedViews $recount,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function viewsDestroyed(ViewsDestroyed $event): void
    {
        $this->rollups->handle($event->viewable);
        $this->versions->forgetCache($event->viewable);
        $this->recount->destroyed($event->viewable);
    }

    /**
     * Erasure names the models whose counts it changed, so their remembered
     * counts are forgotten and their counter columns recounted. When it names
     * none, it touched too many, so every count is forgotten and every model
     * is recounted on the next run.
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function countsChanged(CountsChanged $event): void
    {
        if ($event->viewables === null) {
            $this->versions->flushCache();
            $this->recount->recountEveryModelNextRun();

            return;
        }

        foreach ($event->viewables as $type => $keys) {
            foreach ($keys as $key) {
                $this->versions->forgetModel($type, $key);
            }

            $this->recount->erased($type, $keys);
        }
    }

    /**
     * A scheduled run changes counts across the table, so every remembered
     * count is forgotten.
     */
    public function viewsChanged(): void
    {
        $this->versions->flushCache();
    }

    /**
     * Purging bot views deletes views without saying whose they were, so the
     * next run recounts every model.
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function botViewsPurged(): void
    {
        $this->versions->flushCache();
        $this->recount->recountEveryModelNextRun();
    }
}
