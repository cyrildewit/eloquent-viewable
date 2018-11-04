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

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Contracts\ViewableService as ViewableServiceContract;

/**
 * Class Views.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Views
{
    /**
     * The subject that has been viewed.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $subject;

    /**
     * The period that the current query should scoped to.
     *
     * @var CyrildeWit\EloquentViewable\Period|null
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
     * The viewable service instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\ViewableService
     */
    protected $viewableService;

    /**
     * The view session history instance.
     *
     * @var \CyrildeWit\EloquentViewable\ViewSessionHistory
     */
    protected $viewSessionHistory;

    /**
     * Create a new views instance.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\ViewableService
     * @return void
     */
    public function __construct(ViewableServiceContract $viewableService, ViewSessionHistory $viewSessionHistory)
    {
        $this->viewableService = $viewableService;
        $this->viewSessionHistory = $viewSessionHistory;
    }

    /**
     * Get the views count of the subject.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getViews($period = null): int
    {
        return $this->viewableService->getViewsCount($this->subject, $this->period, $this->unique, $this->tag);
    }

    /**
     * Save a new record of the made view.
     *
     * @todo rethink about the behaviour of this method
     * @return bool
     */
    public function record(): bool
    {
        if ($this->sessionDelay) {
            if (! $this->viewSessionHistory->push($this->subject, $this->sessionDelay)) {
                return $this->viewableService->addViewTo($this->subject, $this->tag);
            }

            return false;
        }

        return $this->viewableService->addViewTo($this->subject, $this->tag);
    }

    /**
     * Set a new subject.
     *
     * @param  \Illuminate\Database\Eloquent\Model
     * @return self
     */
    public function setSubject(Model $subject): self
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
     * @param \CyrildeWit\EloquentViewable\Period
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
}
