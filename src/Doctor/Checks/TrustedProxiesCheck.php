<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use ReflectionMethod;

/**
 * The console sees no request, so the proxies are read from the middleware
 * the application configures, once the HTTP kernel has applied that config.
 */
class TrustedProxiesCheck implements Check
{
    public function __construct(
        protected Container $container,
        protected Config $config,
    ) {}

    public function name(): string
    {
        return 'Trusted proxies';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    public function run(): Generator
    {
        $readers = $this->readersOfTheAddress();

        if ($readers === []) {
            yield Finding::skipped('Nothing reads the IP address of the visitor: the `fingerprint` identity, `IgnoreIpAddresses` with addresses and `IgnoreBursts` by network are all off.');

            return;
        }

        $last = array_pop($readers);
        $others = implode(', ', $readers);

        $readers = $others === ''
            ? $last
            : "{$others} and {$last}";
        $proxies = $this->trustedProxies();

        if (in_array($proxies, [null, [], ''], true)) {
            yield Finding::warning(
                "The IP address of the visitor is read by {$readers}, but no proxy is trusted. Behind a load balancer or CDN, every visitor then has the address of the proxy.",
                'Trust the proxy with `$middleware->trustProxies(at: [...])` in `bootstrap/app.php`. Ignore this when the app faces the internet directly.',
            );

            return;
        }

        if (in_array($proxies, ['*', '**'], true)) {
            yield Finding::advice(
                "The IP address of the visitor is read by {$readers}, and every proxy is trusted, so a client that reaches the app directly can pass any address.",
                'List the addresses of your proxies instead, unless the app is only reachable through them.',
            );

            return;
        }

        yield Finding::pass("The IP address of the visitor is read by {$readers}, and the proxies in front of the app are trusted.");
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    protected function readersOfTheAddress(): array
    {
        $readers = [];

        if ($this->config->visitorIdentity() === 'fingerprint') {
            $readers[] = 'the `fingerprint` identity';
        }

        if (in_array(IgnoreIpAddresses::class, $this->config->guards(), true) && $this->config->ignoredIpAddresses() !== []) {
            $readers[] = '`IgnoreIpAddresses`';
        }

        if (in_array(IgnoreBursts::class, $this->config->guards(), true) && in_array('network', $this->config->burstKeys(), true)) {
            $readers[] = '`IgnoreBursts`';
        }

        return $readers;
    }

    /**
     * Resolving the kernel runs the middleware config in `bootstrap/app.php`,
     * where `trustProxies(at:)` is called.
     */
    protected function trustedProxies(): mixed
    {
        if ($this->container->bound(Kernel::class)) {
            $this->container->make(Kernel::class);
        }

        $middleware = $this->container->make(TrustProxies::class);

        return new ReflectionMethod(TrustProxies::class, 'proxies')->invoke($middleware);
    }
}
