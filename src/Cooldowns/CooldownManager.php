<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use DateTimeInterface;
use Illuminate\Contracts\Session\Session;

class CooldownManager
{
    public function __construct(protected Config $config, protected Session $session) {}

    /**
     * Start a cooldown for the viewable unless one is still running. Returns
     * false while the previous cooldown has not expired.
     */
    public function push(Viewable $viewable, DateTimeInterface $expiresAt, ?string $collection = null): bool
    {
        if ($this->isActive($viewable, $collection)) {
            return false;
        }

        $this->start($viewable, $expiresAt, $collection);

        return true;
    }

    public function isActive(Viewable $viewable, ?string $collection = null): bool
    {
        $this->forgetExpiredCooldowns($this->createNamespaceKey($viewable, $collection));

        return $this->has($this->createViewableKey($viewable, $collection));
    }

    public function start(Viewable $viewable, DateTimeInterface $expiresAt, ?string $collection = null): void
    {
        $this->session->put($this->createViewableKey($viewable, $collection), $this->createCooldown($viewable, $expiresAt));
    }

    /**
     * Determine if the given model has been viewed.
     */
    protected function has(string $viewableKey): bool
    {
        return $this->session->has($viewableKey);
    }

    /** @return array{viewable_id: int|string|null, expires_at: DateTimeInterface} */
    protected function createCooldown(Viewable $viewable, DateTimeInterface $expiresAt): array
    {
        return [
            'viewable_id' => ViewableKey::of($viewable),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Remove all expired cooldowns from the session.
     */
    protected function forgetExpiredCooldowns(string $key): void
    {
        $currentTime = Carbon::now();
        /** @var array<array-key, array{expires_at: DateTimeInterface|string}> $viewHistory */
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

        return $key.'.'.ViewableKey::of($viewable);
    }
}
