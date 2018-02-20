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

namespace CyrildeWit\EloquentVisitable\Helpers;

/**
 * This is the serializer helper.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Serializer
{
    /**
     * Return the right type, based upon the given options.
     *
     * Available types:
     * - normal
     * - unique
     *
     * @return string
     */
    public function createType($unique): string
    {
        // If unique option was given, it's: 'unique' otherwise it's: 'normal'
        return $unique ? 'unique' : 'normal';
    }

    /**
     * Create a period from two dates.
     *
     * @return string
     */
    public function createPeriod($sinceDate = null, $uptoDate = null, $now)
    {
        $sinceDateString = $sinceDate ? $sinceDate->diffInSeconds($now) : '';
        $uptoDateString = $uptoDate ? $uptoDate->diffInSeconds($now) : '';

        return "{$sinceDateString}|{$uptoDateString}";
    }
}
