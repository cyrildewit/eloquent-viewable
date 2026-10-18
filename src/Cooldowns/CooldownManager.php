<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns;

use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\CacheStore;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\SessionStore;
use CyrildeWit\EloquentViewable\Support\PackageManager;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Session\Session;

/** @extends PackageManager<CooldownStore> */
final class CooldownManager extends PackageManager
{
    public function getDefaultDriver(): string
    {
        return $this->packageConfig->cooldownStore();
    }

    protected function configKey(): string
    {
        return 'cooldown.store';
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
