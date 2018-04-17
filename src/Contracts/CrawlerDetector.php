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

/**
 * Interface CrawlerDetector.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface CrawlerDetector
{
    /**
     * Determine if the current user is crawler.
     *
     * @return bool
     */
    public function isBot();
}
