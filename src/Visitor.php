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
     */
    const string DNT = 'HTTP_DNT';

    protected string $visitorCookieKey;

    public function __construct(
        protected Request $request,
        protected CrawlerDetector $crawlerDetector,
        ConfigRepository $config
    ) {
        $this->visitorCookieKey = $config['eloquent-viewable']['visitor_cookie_key'];
    }

    public function id(): string
    {
        if (! Cookie::has($this->visitorCookieKey)) {
            $uniqueString = $this->generateUniqueCookieValue();

            Cookie::queue($this->visitorCookieKey, $uniqueString, $this->cookieExpirationInMinutes());

            return $uniqueString;
        }

        return Cookie::get($this->visitorCookieKey);
    }

    public function ip(): ?string
    {
        return $this->request()->ip();
    }

    public function hasDoNotTrackHeader(): bool
    {
        return (int) $this->request()->header(self::DNT) === 1;
    }

    public function isCrawler(): bool
    {
        return $this->crawlerDetector()->isCrawler();
    }

    protected function request(): Request
    {
        return $this->request;
    }

    protected function crawlerDetector(): CrawlerDetector
    {
        return $this->crawlerDetector;
    }

    protected function generateUniqueCookieValue(): string
    {
        return Str::random(80);
    }

    protected function cookieExpirationInMinutes(): int
    {
        return 2628000; // aka 5 years
    }
}
