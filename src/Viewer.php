<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Illuminate\Http\Request;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

class Viewer
{
    /**
     * PHP stores the DNT header under the "HTTP_DNT" key instead of "DNT".
     *
     * @var string
     */
    const DNT = 'HTTP_DNT';

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
        return $this->request()->ip();
    }

    /**
     * Determine if the viewer has a "Do Not Track" header.
     *
     * @return bool
     */
    public function hasDoNotTrackHeader(): bool
    {
        return 1 === (int) $this->request()->header(self::DNT);
    }

    /**
     * Determine if the viewer is a crawler.
     *
     * @return bool
     */
    public function isCrawler(): bool
    {
        return $this->crawlerDetector()->isCrawler();
    }

    /**
     * Returns the request instance.
     *
     * @return \Illuminate\Http\Request
     */
    protected function request(): Request
    {
        return $this->request;
    }

    /**
     * Returns the crawler detector instance.
     *
     * @return \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector
     */
    protected function crawlerDetector(): CrawlerDetector
    {
        return $this->crawlerDetector;
    }
}
