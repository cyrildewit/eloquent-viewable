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

namespace CyrildeWit\EloquentViewable\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Interface View.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface View
{
    /**
     * Get all of the owning viewable models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function viewable(): MorphTo;
}
