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
use CyrildeWit\EloquentViewable\Resolvers\IpAddressResolver;

/**
 * Class CreateViewRecord.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class CreateViewRecord
{
    protected $ignoreBots;
    protected $honorDnt;
    protected $visitorCookieKey;
    protected $ignoredIpAddresses;

    protected $createView;

    /** @var \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector */
    protected $crawlerDetector;

    // Illuminate\Http\Request
    protected $request;
    protected $ipAddressResolver;

    public function __construct(CreateView $createView, Request $request, CrawlerDetector $crawlerDetector, IpAddressResolver $ipAddressResolver)
    {
        $this->ignoreBots = config('eloquent-viewable.ignore_bots', true);
        $this->honorDnt = config('eloquent-viewable.honor_dnt', false);
        $this->visitorCookieKey = config('eloquent-viewable.visitor_cookie_key', 'eloquent_viewable');
        $this->ignoredIpAddresses = collect(config('eloquent-viewable.ignored_ip_addresses', []));
        $this->createView = $createView;
        $this->request = $request;
        $this->crawlerDetector = $crawlerDetector;
        $this->ipAddressResolver = $ipAddressResolver;
    }

    public function execute(array $data)
    {
        if (! $this->shouldContinue()) {
            return;
        }

        // validate data?
        // subject is required and should be an instance of Model?

        $visitor = $this->getVisitorCookie();

        return $this->createView->execute([
            'viewable_id' => $data['subject']->getKey(),
            'viewable_type' => $data['subject']->getMorphClass(),
            'visitor' => $data['visitor'] ?? $visitor,
            'tag' => $data['tag'] ?? null,
        ]);
    }

    protected function shouldContinue()
    {
        // If ignore bots is true and the current viewer is a bot, return false
        if ($this->ignoreBots && $this->crawlerDetector->isBot()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($this->honorDnt && (Request::header('HTTP_DNT') == 1)) {
            return false;
        }

        if ($this->ignoredIpAddresses->contains($this->resolveIpAddress())) {
            return false;
        }

        return true;
    }

    protected function resolveIpAddress()
    {
        return $this->ipAddressResolver->resolve();
    }

    protected function getVisitorCookie()
    {
        return $this->request->cookie($this->visitorCookieKey);
    }
}
