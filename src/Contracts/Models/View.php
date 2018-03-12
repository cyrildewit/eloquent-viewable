<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Contracts\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Interface View.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface View
{
    /**
     * Create the polymorphic relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model(): MorphTo;
}
