<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Services;

use Request;
use Carbon\Carbon;
use CyrildeWit\EloquentVisitable\Helpers\Serializer;
use CyrildeWit\EloquentVisitable\Jobs\StoreVisitJob;
use CyrildeWit\EloquentVisitable\Helpers\DateTransformer;
use CyrildeWit\EloquentVisitable\Cache\VisitCounterCacheRepository;
use CyrildeWit\EloquentVisitable\Contracts\Models\Visit as VisitContract;

/**
 * This is the visit service manager.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class VisitService
{
    /**
     * Visit counter cache repository instance.
     *
     * @var \CyrildeWit\EloquentVisitable\Cache\VisitCounterCacheRepository
     */
    protected $visitCounterCache;

    /**
     * Date transformer helper instance.
     *
     * @var CyrildeWit\EloquentVisitable\Helpers\DateTransformer;
     */
    protected $dateTransformer;

    /**
     * Serializer instance.
     *
     * @var \CyrildeWit\EloquentVisitable\Helpers\Serializer
     */
    protected $serializer;

    /**
     * Create a new VisitService instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->visitCounterCache = app(VisitCounterCacheRepository::class);
        $this->dateTransformer = app(DateTransformer::class);
        $this->serializer = app(Serializer::class);
    }

    /**
     * @return int
     */
    public function getVisitsCount($model, $sinceDate = null, $uptoDate = null, bool $unique = false): int
    {
        // # 1 >> Convert the given dates
        $sinceDate = $sinceDate ? $this->dateTransformer->transform($sinceDate) : null;
        $uptoDate = $uptoDate ? $this->dateTransformer->transform($uptoDate) : null;

        // # 2 >> Prepare some date
        // # 2.1 >> Convert the given options to serialized keys
        $typeKey = $this->serializer->createType($unique);
        $periodKey = $this->serializer->createPeriod($sinceDate, $uptoDate, Carbon::now());

        // # 3 >> Get the visits count from the cache, otherwise recount it
        // # 3.1 >> Search in cache and return it, if it's not expired
        $visitsCountCache = $this->visitCounterCache->getVisitCounter($model, $typeKey, $periodKey);

        if ($visitsCountCache) {
            return $visitsCountCache;
        }

        // # 3.2 >> Recount the visits, cache it and return the count
        $visitsCount = $this->countVisits($model, $sinceDate, $uptoDate, $unique);

        $this->visitCounterCache->putVisitCounter($model, $visitsCount, $typeKey, $periodKey);

        return $visitsCount;
    }

    public function getUniqueVisitsCount($model, $sinceDate = null, $uptoDate = null)
    {
        return $this->getVisitsCount($model, $sinceDate, $uptoDate, true);
    }

    public function countVisits($model, $sinceDate = null, $uptoDate = null, bool $unique = false)
    {
        // Create new Query Builder instance of the visits relationship
        $query = $model->visits();

        // Apply the following date filters
        if ($sinceDate && ! $uptoDate) {
            $query->where('created_at', '>=', $sinceDate);
        } elseif (! $sinceDate && $uptoDate) {
            $query->where('created_at', '=<', $uptoDate);
        } elseif ($sinceDate && $uptoDate) {
            $query->whereBetween('created_at', [$sinceDate, $uptoDate]);
        }

        // Apply the following if page views should be unique
        if ($unique) {
            $query->select('ip_address')->groupBy('ip_address');
        }

        // If the unique option is false then just use the SQL count method,
        // otherwise get the results and count them
        $visitsCount = ! $unique ? $query->count() : $query->get()->count();

        return $visitsCount;
    }

    /**
     * @return bool
     */
    public function storeModelVisit($model): bool
    {
        // # 1 >> Create a new Visit model instance with data
        $visit = app(VisitContract::class)->create([
            'visitable_id' => $model->getKey(),
            'visitable_type' => get_class($model),
            'ip_address' => Request::ip(),
        ]);

        // # 2 >> Check if the user enabled queuing
        // # 2.1 >> Queuing is enabled: dispatch the job
        if (config('eloquent-visitable.jobs.store-new-visit.queue', false)) {
            StoreVisitJob::dispatch($visit)
                ->delay(Carbon::now()->addSeconds(config('eloquent-visitable.jobs.store-new-visit.delay_in_seconds', 20)));

            return true;
        }

        // # @.2 >> Queuing is disabled: Save the visit in the database
        $visit->save();

        return true;
    }
}
