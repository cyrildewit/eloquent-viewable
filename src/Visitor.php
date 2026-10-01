<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Visitor implements VisitorContract
{
    /**
     * The name of the Do Not Track request header.
     */
    const string DNT = 'DNT';

    public function __construct(
        protected Request $request,
        protected CrawlerDetector $crawlerDetector,
        protected Config $config,
        protected CookieJar $cookies,
    ) {}

    public function id(): string
    {
        $cookieKey = $this->config->visitorCookieKey();

        $id = $this->request()->cookie($cookieKey);

        if (is_string($id)) {
            return $id;
        }

        $id = $this->generateUniqueCookieValue();

        $this->cookies->queue($cookieKey, $id, $this->cookieExpirationInMinutes());

        return $id;
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
