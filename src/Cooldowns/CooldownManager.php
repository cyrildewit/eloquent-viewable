<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns;

use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\CacheStore;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\SessionStore;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/** @method CooldownStore driver(?string $driver = null) */
final class CooldownManager extends Manager
{
    public function __construct(Container $container, private readonly Config $packageConfig)
    {
        parent::__construct($container);
    }

    public function getDefaultDriver(): string
    {
        return $this->packageConfig->cooldownStore();
    }

    /**
     * @param  string  $driver
     *
     * @throws InvalidConfiguration
     */
    #[\Override]
    protected function createDriver($driver): CooldownStore
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw InvalidConfiguration::unknownDriver('cooldown.store', $driver);
        }

        /** @var CooldownStore $store */
        $store = parent::createDriver($driver);

        return $store;
    }

    protected function createSessionDriver(): SessionStore
    {
        return new SessionStore($this->container->make(Session::class), $this->packageConfig->cooldownKey());
    }

    protected function createCacheDriver(): CacheStore
    {
        return new CacheStore(
            $this->container->make(CacheFactory::class)->store($this->packageConfig->cooldownCacheStore()),
            $this->packageConfig->cooldownKey(),
        );
    }
}
