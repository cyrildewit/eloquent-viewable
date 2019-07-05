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

use Illuminate\Http\Request;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

class Viewer
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The crawler detector instance.
     *
     * @var \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector
     */
    protected $crawlerDetector;

    /**
     * The visitor cookie repository instance.
     *
     * @var \CyrildeWit\EloquentViewable\VisitorCookieRepository
     */
    protected $visitorCookieRepository;

    /**
     * Create a new viewer instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector  $crawlerDetector
     * @return void
     */
    public function __construct(
        Request $request,
        CrawlerDetector $crawlerDetector,
        VisitorCookieRepository $visitorCookieRepository
    ) {
        $this->request = $request;
        $this->crawlerDetector = $crawlerDetector;
        $this->visitorCookieRepository = $visitorCookieRepository;
    }

    /**
     * Get the unique ID that represent's the viewer.
     *
     * @return string
     */
    public function id()
    {
        return $this->visitorCookieRepository->get();
    }

    /**
     * Get the viewer IP address.
     *
     * @return string|null
     */
    public function ip()
    {
        return $this->request->ip();
    }

    /**
     * Determine if the viewer is a crawler.
     *
     * @return bool
     */
    public function isCrawler(): bool
    {
        return $this->crawlerDetector->isCrawler();
    }
}
