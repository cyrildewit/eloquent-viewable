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

use DateTime;
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Support\Period;
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
     * The delay that should be made before a new view can be recorded.
     *
     * @var \DateTime
     */
    protected $sessionDelay = null;

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
     * Save a new record of the made view.
     *
     * @return bool
     */
    public function record(): bool
    {
        $subject = $this->subject;
        $sessionDelay = $this->sessionDelay;

        if ($sessionDelay) {
            if (! $this->viewSessionHistory->push($subject, $sessionDelay)) {
                return $this->viewableService->addViewTo($subject);
            } else {
                return false;
            }
        }

        return $this->viewableService->addViewTo($subject);
    }

    /**
     * Set a new subject.
     *
     * @param  \Illuminate\Database\Eloquent\Model
     * @return $this
     */
    public function setSubject(Model $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set a delay in the session.
     *
     * @param
     * @return $this
     */
    public function delayInSession($delay) // = null, means using config
    {
        // default maybe?
        $this->sessionDelay = $delay;

        return $this;
    }
}
