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

namespace CyrildeWit\EloquentVisitable\Exceptions;

use InvalidArgumentException;

/**
 * This is the DateTransformerDoesNotExists exception.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class DateTransformerDoesNotExists extends InvalidArgumentException
{
    public static function create(string $dateTransformerKey)
    {
        return new static("There is no date transformer named `{$dateTransformerKey}`.");
    }
}
