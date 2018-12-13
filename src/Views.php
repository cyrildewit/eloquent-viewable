<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Contracts\HeaderResolver;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Contracts\IpAddressResolver;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;

class Views
{
    /**
     * The subject where we are applying actions to.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $subject;

    /**
     * The period that the current query should scoped to.
     *
     * @var \CyrildeWit\EloquentViewable\Support\Period|null
     */
    protected $period = null;

    /**
     * Determine if only unique views should be returned.
     *
     * @var bool
     */
    protected $unique = false;

    /**
     * The delay that should be finished before a new view can be recorded.
     *
     * @var \DateTime|null
     */
    protected $sessionDelay = null;

    /**
     * The tag under which view will be saved.
     *
     * @var string|null
     */
    protected $tag = null;

    /**
     * Determine if the views count should be cached.
     *
     * @var string|null
     */
    protected $shouldCache = false;

    /**
     * Used IP Address instead of the provided one by the resolver.
     *
     * @var string
     */
    protected $overriddenIpAddress;

    /**
     * The view session history instance.
     *
     * @var \CyrildeWit\EloquentViewable\ViewSessionHistory
     */
    protected $viewSessionHistory;

    /**
     * The visitor cookie repository instance.
     *
     * @var \CyrildeWit\EloquentViewable\VisitorCookieRepository
     */
    protected $visitorCookieRepository;

    /**
     * The crawler detector instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector
     */
    protected $crawlerDetector;

    /**
     * The IP Address resolver instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\IpAddressResolver
     */
    protected $ipAddressResolver;

    /**
     * The request header resolver instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\HeaderResolver
     */
    protected $headerResolver;

    /**
     * Create a new views instance.
     *
     * @return void
     */
    public function __construct(
        ViewSessionHistory $viewSessionHistory,
        VisitorCookieRepository $visitorCookieRepository,
        CrawlerDetector $crawlerDetector,
        IpAddressResolver $ipAddressResolver,
        HeaderResolver $headerResolver
    ) {
        $this->viewSessionHistory = $viewSessionHistory;
        $this->visitorCookieRepository = $visitorCookieRepository;
        $this->crawlerDetector = $crawlerDetector;
        $this->ipAddressResolver = $ipAddressResolver;
        $this->headerResolver = $headerResolver;
    }

    /**
     * Count the views for a viewable type.
     *
     * @param  string  $viewableType
     * @return int
     */
    public function countByType(string $viewableType): int
    {
        $period = $this->period ?? Period::create();

        $query = app(ViewContract::class)->where('viewable_type', $viewableType);
        $query = $this->applyPeriodToQuery($query, $period);

        if ($this->unique) {
            $viewsCount = $query->distinct('visitor')->count('visitor');
        } else {
            $viewsCount = $query->count();
        }

        return $viewsCount;
    }

    /**
     * Save a new record of the made view.
     *
     * @return bool
     */
    public function record(): bool
    {
        if ($this->shouldRecord()) {
            $view = app(ViewContract::class);
            $view->viewable_id = $this->subject->getKey();
            $view->viewable_type = $this->subject->getMorphClass();
            $view->visitor = $this->resolveVisitorId();
            $view->tag = $this->tag;
            $view->viewed_at = Carbon::now();

            return $view->save();
        }

        return false;
    }

    /**
     * Count the views.
     *
     * @return int
     */
    public function count(): int
    {
        $period = $this->period ?? Period::create();

        $query = $this->morphViews();
        $query = $this->applyPeriodToQuery($query, $period);

        if ($this->unique) {
            $viewsCount = $query->distinct('visitor')->count('visitor');
        } else {
            $viewsCount = $query->count();
        }

        return $viewsCount;
    }

    /**
     * Destroy all views of the subject.
     *
     * @return void
     */
    public function destroy()
    {
        $this->morphViews()->delete();
    }

    /**
     * Set a new subject.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null
     * @return self
     */
    public function setSubject($subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the delay in the session.
     *
     * @param
     * @return self
     */
    public function delayInSession($delay): self
    {
        $this->sessionDelay = $delay;

        return $this;
    }

    /**
     * Set the period.
     *
     * @param  \CyrildeWit\EloquentViewable\Period
     * @return self
     */
    public function period($period): self
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Set a tag.
     *
     * @param  string
     * @return self
     */
    public function tag($tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Fetch only unique views.
     *
     * @param  bool  $state
     * @return self
     */
    public function unique(bool $state = true): self
    {
        $this->unique = $state;

        return $this;
    }

    // /**
    //  * Cache the current views count.
    //  *
    //  * @return self
    //  */
    // public function cache()
    // {
    //     $this->shouldCache = true;

    //     return $this;
    // }

    /**
     * Override the visitor's IP Address.
     *
     * @param  string  $address
     * @return self
     */
    public function overrideIpAddress(string $address)
    {
        $this->overriddenIpAddress = $address;

        return $this;
    }

    /**
     * Get the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    protected function morphViews()
    {
        return $this->subject->morphMany(app(ViewContract::class), 'viewable');
    }

    /**
     * Apply the period constraint to the given query.
     *
     * @param  $query
     * @param  string  $column
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyPeriodToQuery($query, $period, string $column = 'viewed_at')
    {
        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        if ($startDateTime && ! $endDateTime) {
            $query->where($column, '>=', $startDateTime);
        } elseif (! $startDateTime && $endDateTime) {
            $query->where($column, '<=', $endDateTime);
        } elseif ($startDateTime && $endDateTime) {
            $query->whereBetween($column, [$startDateTime, $endDateTime]);
        }

        return $query;
    }

    /**
     * Determine if we should record the view.
     *
     * @return bool
     */
    protected function shouldRecord(): bool
    {
        // If ignore bots is true and the current viewer is a bot, return false
        if (config('eloquent-viewable.ignore_bots') && $this->crawlerDetector->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if (config('eloquent-viewable.honor_dnt', false) && $this->requestHasDoNotTrackHeader()) {
            return false;
        }

        if (collect(config('eloquent-viewable.ignored_ip_addresses'))->contains($this->resolveIpAddress())) {
            return false;
        }

        if ($this->sessionDelay && $this->viewSessionHistory->push($this->subject, $this->sessionDelay)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the visitor's IP Address.
     *
     * It will first check if the overriddenIpAddress property has been set,
     * otherwise it will resolve it using the IP Address resolver.
     *
     * @return string
     */
    protected function resolveIpAddress(): string
    {
        return $this->overriddenIpAddress ?? $this->ipAddressResolver->resolve();
    }

    /**
     * Determine if the request has a Do Not Track header.
     *
     * @return string
     */
    protected function requestHasDoNotTrackHeader(): bool
    {
        return 1 === (int) $this->headerResolver->resolve('HTTP_DNT');
    }

    /**
     * Resolve the visitor's unique ID.
     *
     * @return string|null
     */
    protected function resolveVisitorId()
    {
        return $this->visitorCookieRepository->get();
    }
}
