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

    protected string $visitorCookieKey;

    protected Request $request;

    protected CrawlerDetector $crawlerDetector;

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
     * Get the unique ID that represents the visitor.
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
     */
    public function ip(): ?string
    {
        return $this->request()->ip();
    }

    /**
     * Determine if the visitor has a "Do Not Track" header.
     */
    public function hasDoNotTrackHeader(): bool
    {
        return 1 === (int) $this->request()->header(self::DNT);
    }

    /**
     * Determine if the visitor is a crawler.
     */
    public function isCrawler(): bool
    {
        return $this->crawlerDetector()->isCrawler();
    }

    /**
     * Returns the request instance.
     */
    protected function request(): Request
    {
        return $this->request;
    }

    /**
     * Returns the crawler detector instance.
     */
    protected function crawlerDetector(): CrawlerDetector
    {
        return $this->crawlerDetector;
    }

    /**
     * Generate a unique visitor id.
     */
    protected function generateUniqueCookieValue(): string
    {
        return Str::random(80);
    }

    /**
     * Get the expiration in minutes.
     */
    protected function cookieExpirationInMinutes(): int
    {
        return 2628000; // aka 5 years
    }
}
