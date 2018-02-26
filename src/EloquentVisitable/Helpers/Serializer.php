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
     * Find the right type based upon the given argument.
     *
     * @return string
     */
    public function createType(bool $unique): string
    {
        return $unique ? 'unique' : 'normal';
    }

    /**
     * Create a period from the since date and the upto date.
     *
     * @param  \Carbon\Carbon  $sinceDate
     * @param  \Carbon\Carbon  $uptoDate
     * @param  \Carbon\Carbon  $now
     * @return string
     */
    public function createPeriod($sinceDate, $uptoDate, $now): string
    {
        $sinceDateString = $sinceDate ? $sinceDate->diffInSeconds($now) : '';
        $uptoDateString = $uptoDate ? $uptoDate->diffInSeconds($now) : '';

        return "{$sinceDateString}|{$uptoDateString}";
    }
}
