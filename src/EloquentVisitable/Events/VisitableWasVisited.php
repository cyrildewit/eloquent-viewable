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

namespace CyrildeWit\EloquentVisitable\Events;

/**
 * Class VisitableWasVisited.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class VisitableWasVisited
{
    /**
     * The visited visitable model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $visitable;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $visitable
     * @return void
     */
    public function __construct($visitable)
    {
        $this->visitable = $visitable;
    }
}
