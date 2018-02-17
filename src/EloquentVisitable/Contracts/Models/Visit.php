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

namespace CyrildeWit\EloquentVisitable\Contracts\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Contract for the Visit Eloquent model.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface Visit
{
    /**
     * Create the polymorphic relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model(): MorphTo;
}
