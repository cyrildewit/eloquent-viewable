<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Visitor implements VisitorContract
{
    /**
     * PHP stores the DNT header under the "HTTP_DNT" key instead of "DNT".
     *
     * @var string
     */
    const DNT = 'HTTP_DNT';

    /**
     * The visitor cookie key.
     *
     * @var string
     */
    protected $visitorCookieKey;

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
     * Create a new visitor instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector  $crawlerDetector
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function __construct(
        Request $request,
        CrawlerDetector $crawlerDetector,
        ConfigRepository $config
    ) {
        $this->visitorCookieKey = $config['eloquent-viewable']['visitor_cookie_key'];
        $this->request = $request;
        $this->crawlerDetector = $crawlerDetector;
    }

    /**
     * Get the unique ID that represent's the visitor.
     *
     * @return string
     */
    public function id(): string
    {
        if (! Cookie::has($this->visitorCookieKey)) {
            $uniqueString = $this->generateUniqueCookieValue();

            Cookie::queue($this->visitorCookieKey, $uniqueString, $this->cookieExpirationInMinutes());

            return $uniqueString;
        }

        return Cookie::get($this->visitorCookieKey);
    }

    /**
     * Get the visitor IP address.
     *
     * @return string|null
     */
    public function ip(): string
    {
        return $this->request()->ip();
    }

    /**
     * Determine if the visitor has a "Do Not Track" header.
     *
     * @return bool
     */
    public function hasDoNotTrackHeader(): bool
    {
        return 1 === (int) $this->request()->header(self::DNT);
    }

    /**
     * Determine if the visitor is a crawler.
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

    /**
     * Generate a unique visitor id.
     *
     * @return string
     */
    protected function generateUniqueCookieValue(): string
    {
        return Str::random(80);
    }

    /**
     * Get the expiration in minutes.
     *
     * @return int
     */
    protected function cookieExpirationInMinutes()
    {
        return 2628000; // aka 5 years
    }
}
