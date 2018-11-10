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
     * The view session history instance.
     *
     * @var \CyrildeWit\EloquentViewable\ViewSessionHistory
     */
    protected $viewSessionHistory;

    /**
     * The create view record instance.
     *
     * @var \CyrildeWit\EloquentViewable\CreateViewRecord
     */
    protected $createViewRecord;

    /**
     * Create a new views instance.
     *
     * @return void
     */
    public function __construct(
        ViewSessionHistory $viewSessionHistory,
        CreateViewRecord $createViewRecord
    ) {
        $this->viewSessionHistory = $viewSessionHistory;
        $this->createViewRecord = $createViewRecord;
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
        if ($this->sessionDelay && $this->viewSessionHistory->push($this->subject, $this->sessionDelay)) {
            return false;
        }

        return $this->createViewRecord->execute([
            'subject' => $this->subject,
            'tag' => $this->tag,
        ]);
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
        $query = $this->subject->views();

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
        $this->subject->views()->delete();
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

    protected function applyPeriodToQuery($query, $period)
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
}
