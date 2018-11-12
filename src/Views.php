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

    public function countByType($viewableType)
    {
        // Use given period, otherwise create an empty one
        $period = $this->period ?? Period::create();

        $query = View::where('viewable_type', $viewableType);

        $query = $this->applyPeriodToQuery($query, $period);

        // Count only the unique views
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
     * @todo rethink about the behaviour of this method
     * @return bool
     */
    public function record()//: bool
    {
        if (! $this->shouldRecord()) {
            return false;
        }

        $view = app(ViewContract::class);
        $view->viewable_id = $this->subject->getKey();
        $view->viewable_type = $this->subject->getMorphClass();
        $view->visitor = $this->getVisitorCookie();
        $view->tag = $this->tag;
        $view->viewed_at = Carbon::now();
        $view->save();

        return $view;
    }

    /**
     * Count the views.
     *
     * @return int
     */
    public function count(): int
    {
        // Use given period, otherwise create an empty one
        $period = $this->period ?? Period::create();

        // Create new Query Builder instance of the views relationship
        $query = $this->morphViews();

        $query = $this->applyPeriodToQuery($query, $period);

        // Count only the unique views
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
     * Set the tag.
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
     * Get a collection of all the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    private function morphViews()
    {
        return $this->subject->morphMany(app(ViewContract::class), 'viewable');
    }

    private function applyPeriodToQuery($query, $period)
    {
        $startDateTime = $period->getStartDateTime();
        $endDateTime = $period->getEndDateTime();

        // Apply period to query
        if ($startDateTime && ! $endDateTime) {
            $query->where('viewed_at', '>=', $startDateTime);
        } elseif (! $startDateTime && $endDateTime) {
            $query->where('viewed_at', '<=', $endDateTime);
        } elseif ($startDateTime && $endDateTime) {
            $query->whereBetween('viewed_at', [$startDateTime, $endDateTime]);
        }

        return $query;
    }

    private function shouldRecord()
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
     * @return string
     */
    private function resolveIpAddress(): string
    {
        return $this->overriddenIpAddress ?? $this->ipAddressResolver->resolve();
    }

    /**
     * Determine if the request has a Do Not Track header.
     *
     * @return string
     */
    private function requestHasDoNotTrackHeader(): string
    {
        return 1 === (int) $this->$this->headerResolver->resolve('HTTP_DNT');
    }

    /**
     * Determine if the request has a Do Not Track header.
     *
     * @return string|null
     */
    private function getVisitorCookie()
    {
        return $this->visitorCookieRepository->get();
    }
}
