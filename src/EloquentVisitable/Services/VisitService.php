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
use CyrildeWit\EloquentVisitable\Jobs\ProcessVisit;
use CyrildeWit\EloquentVisitable\Helpers\Serializer;
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
    protected $visitCounterCacheRepository;

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
        $this->visitCounterCacheRepository = app(VisitCounterCacheRepository::class);
        $this->dateTransformer = app(DateTransformer::class);
        $this->serializer = app(Serializer::class);
    }

    /**
     * Get the visits count based upon the inserted arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon|null  $sinceDate
     * @param  \Carbon\Carbon|null  $uptoDate
     * @param  bool  $unique
     * @return int
     */
    public function getVisitsCount($model, $sinceDate = null, $uptoDate = null, bool $unique = false): int
    {
        // First we have to transform the since date and upto date, if they are
        // given, with the date transformer.
        $sinceDate = $sinceDate ? $this->dateTransformer->transform($sinceDate) : null;
        $uptoDate = $uptoDate ? $this->dateTransformer->transform($uptoDate) : null;

        // Create a type and period based upon the given arguments. This data
        // is required to be able to retrieve the cached counts and cache
        // new counts.
        $typeKey = $this->serializer->createType($unique);
        $periodKey = $this->serializer->createPeriod($sinceDate, $uptoDate, Carbon::now());

        // If caching is enabled, try to find a cached value, otherwise continue
        // and count again
        if (config('eloquent-visitable.cache.cache_visits_count.enabled', true)) {
            if (! is_null($cachedVisitsCount = $this->visitCounterCacheRepository->get($model, $typeKey, $periodKey))) {
                return $cachedVisitsCount;
            }
        }

        // Count the visits again
        $visitsCount = $this->countVisits($model, $sinceDate, $uptoDate, $unique);

        // Cache the counted visits
        $this->visitCounterCacheRepository->put($model, $typeKey, $periodKey, $visitsCount);

        return $visitsCount;
    }

    /**
     * Get the unique visits count based upon the inserted arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon|null  $sinceDate
     * @param  \Carbon\Carbon|null  $uptoDate
     * @param  bool  $unique
     * @return int
     */
    public function getUniqueVisitsCount($model, $sinceDate = null, $uptoDate = null): int
    {
        return $this->getVisitsCount($model, $sinceDate, $uptoDate, true);
    }

    /**
     * Count the visits based upon the inserted arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Carbon\Carbon  $sinceDate
     * @param  \Carbon\Carbon  $uptoDate
     * @param  bool  $unique
     * @return int
     */
    public function countVisits($model, $sinceDate = null, $uptoDate  = null, bool $unique = false): int
    {
        // Create new Query Builder instance of the visits relationship
        $query = $model->visits();

        // Apply the following date filters
        if ($sinceDate && ! $uptoDate) {
            $query->where('created_at', '>=', $sinceDate);
        } elseif (! $sinceDate && $uptoDate) {
            $query->where('created_at', '<=', $uptoDate);
        } elseif ($sinceDate && $uptoDate) {
            $query->whereBetween('created_at', [$sinceDate, $uptoDate]);
        }

        // Retrieve a collection of all the ip addresses and group them by
        // ip address
        if ($unique) {
            $query->select('ip_address')->groupBy('ip_address');
        }

        // If the unique option is false then just use the SQL count method,
        // otherwise get the results and count them
        $visitsCount = ! $unique ? $query->count() : $query->get()->count();

        return $visitsCount;
    }

    /**
     * Store a new visit.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function storeModelVisit($model): bool
    {
        // Create a new Visit model instance
        $visit = app(VisitContract::class)->create([
            'visitable_id' => $model->getKey(),
            'visitable_type' => get_class($model),
            'ip_address' => Request::ip(),
        ]);

        // If queuing is enabled, dispatch the job
        $configStoreNewVisit = config('eloquent-visitable.jobs.store_new_visit');

        if ($configStoreNewVisit['enabled'] ?? false) {
            $delayInSeconds = $configStoreNewVisit['delay_in_seconds'] ?? 60 * 2;
            $onQueue = $configStoreNewVisit['onQueue'] ?? null;
            $onConnection = $configStoreNewVisit['onConnection'] ?? null;

            ProcessVisit::dispatch($visit)
                ->delay(Carbon::now()->addSeconds($delayInSeconds))
                ->onQueue($onQueue)
                ->onConnection($onConnection);

            return true;
        }

        // Otherwise, just save the visit in the database
        $visit->save();

        return true;
    }
}
