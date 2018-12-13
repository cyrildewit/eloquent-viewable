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

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

class CrawlerDetectAdapter implements CrawlerDetector
{
    /**
     * CrawlerDetect instance.
     *
     * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
     */
    private $detector;

    /**
     * Create a new CrawlerDetector instance.
     *
     * @param  \Jaybizzle\CrawlerDetect\CrawlerDetect  $detector
     * @return void
     */
    public function __construct(CrawlerDetect $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Determine if the current user is a crawler.
     *
     * @return bool
     */
    public function isCrawler(): bool
    {
        return $this->detector->isCrawler();
    }
}
