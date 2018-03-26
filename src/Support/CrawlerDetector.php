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

namespace CyrildeWit\EloquentViewable\Support;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Class CrawlerDetector.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class CrawlerDetector
{
    /**
     * CrawlerDetect instance.
     *
     * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
     */
    protected $detector;

    /**
     * Create a new CrawlerDetector instance.
     *
     * @param  array  $headers
     * @param  string  $agent
     * @return void
     */
    public function __construct(array $headers, $agent)
    {
        $this->detector = new CrawlerDetect($headers, $agent);
    }

    /**
     * Check if current request is from a bot.
     *
     * @return bool
     */
    public function isRobot()
    {
        return $this->detector->isCrawler();
    }
}
