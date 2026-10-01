<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Config;
use DateTimeInterface;
use Illuminate\Contracts\Session\Session;

class CooldownManager
{
    public function __construct(protected Config $config, protected Session $session) {}

    /**
     * Push a cooldown for the viewable model with an expiry date.
     */
    public function push(Viewable $viewable, DateTimeInterface $expiresAt, ?string $collection = null): bool
    {
        $namespaceKey = $this->createNamespaceKey($viewable, $collection);
        $viewableKey = $this->createViewableKey($viewable, $collection);

        $this->forgetExpiredCooldowns($namespaceKey);

        if (! $this->has($viewableKey)) {
            $this->session->put($viewableKey, $this->createCooldown($viewable, $expiresAt));

            return true;
        }

        return false;
    }

    /**
     * Determine if the given model has been viewed.
     */
    protected function has(string $viewableKey): bool
    {
        return $this->session->has($viewableKey);
    }

    /**
     * Create a cooldown for given viewable model.
     *
     * @return array{viewable_id: mixed, expires_at: DateTimeInterface}
     */
    protected function createCooldown(Viewable $viewable, DateTimeInterface $expiresAt): array
    {
        return [
            'viewable_id' => $viewable->getKey(),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Remove all expired cooldowns from the session.
     */
    protected function forgetExpiredCooldowns(string $key): void
    {
        $currentTime = Carbon::now();
        $viewHistory = $this->session->get($key, []);

        foreach ($viewHistory as $viewableKey => $record) {
            if (Carbon::parse($record['expires_at'])->lte($currentTime)) {
                $this->session->forget($key.'.'.$viewableKey);
            }
        }
    }

    /**
     * Create a base key from the given viewable model.
     *
     * Returns for example:
     * => `eloquent-viewable.session.key.app-models-post`
     */
    protected function createNamespaceKey(Viewable $viewable, ?string $collection = null): string
    {
        $key = $this->config->cooldownKey();
        $key .= '.'.strtolower(str_replace('\\', '-', $viewable->getMorphClass()));
        $key .= is_string($collection) ? ":{$collection}" : '';

        return $key;
    }

    /**
     * Create a unique key from the given viewable model.
     *
     * Returns for example:
     * => `eloquent-viewable.session.key.app-models-post.1`
     */
    protected function createViewableKey(Viewable $viewable, ?string $collection = null): string
    {
        $key = $this->createNamespaceKey($viewable, $collection);

        return $key.".{$viewable->getKey()}";
    }
}
