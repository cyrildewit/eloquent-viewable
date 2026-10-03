<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Visitors;

use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class Visitor implements VisitorContract
{
    /**
     * The name of the Do Not Track request header.
     */
    const string DNT = 'DNT';

    /**
     * The name of the Global Privacy Control request header.
     */
    const string GPC = 'Sec-GPC';

    const array PREFETCH_HEADERS = ['Sec-Purpose', 'Purpose', 'X-Moz'];

    public function __construct(
        protected Request $request,
        protected Config $config,
        protected CookieJar $cookies,
        protected AuthFactory $auth,
    ) {}

    public function viewer(): ?Model
    {
        $user = $this->auth->guard($this->config->viewerGuard())->user();

        return $user instanceof Model ? $user : null;
    }

    public function id(): string
    {
        $cookieName = $this->config->visitorCookieName();

        $id = $this->request()->cookie($cookieName) ?? $this->queuedId($cookieName);

        if (is_string($id)) {
            return $id;
        }

        $id = $this->generateUniqueCookieValue();

        $this->cookies->queue($cookieName, $id, $this->config->visitorCookieLifetime());

        return $id;
    }

    public function ip(): ?string
    {
        return $this->request()->ip();
    }

    /**
     * The `User-Agent` header followed by any device headers a proxy adds,
     * space separated, so a proxied browser is judged by the device behind
     * it. The list is the one jaybizzle/crawler-detect reads, copied here so
     * this module does not depend on that package.
     */
    public function userAgent(): ?string
    {
        $values = [];

        foreach ($this->userAgentHeaders() as $header) {
            $value = $this->request()->header($header);

            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return $values === [] ? null : implode(' ', $values);
    }

    public function hasDoNotTrackHeader(): bool
    {
        return (int) $this->request()->header(self::DNT) === 1;
    }

    public function hasGlobalPrivacyControl(): bool
    {
        return (int) $this->request()->header(self::GPC) === 1;
    }

    public function isPrefetch(): bool
    {
        foreach (self::PREFETCH_HEADERS as $header) {
            $value = $this->request()->header($header);

            if (is_string($value) && str_contains(strtolower($value), 'prefetch')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every visitor instance in a request must hand out the same new id.
     */
    protected function queuedId(string $cookieName): ?string
    {
        foreach ($this->cookies->getQueuedCookies() as $cookie) {
            if ($cookie instanceof Cookie && $cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    protected function request(): Request
    {
        return $this->request;
    }

    /**
     * The headers that may carry a user agent, in the order they are joined.
     *
     * @return list<string>
     */
    protected function userAgentHeaders(): array
    {
        return [
            'User-Agent',
            'X-Operamini-Phone-UA',
            'X-Device-User-Agent',
            'X-Original-User-Agent',
            'X-Skyfire-Phone',
            'X-Bolt-Phone-UA',
            'Device-Stock-UA',
            'X-UCBrowser-Device-UA',
            'From',
            'X-Scanner',
            'Sec-CH-UA',
        ];
    }

    protected function generateUniqueCookieValue(): string
    {
        return Str::random(80);
    }
}
