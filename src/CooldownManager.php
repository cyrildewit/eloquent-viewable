<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Session\Session;

class CooldownManager
{
    protected Session $session;

    /**
     * The primary key under which history is stored.
     */
    protected string $primaryKey;

    public function __construct(ConfigRepository $config, Session $session)
    {
        $this->session = $session;
        $this->primaryKey = $config['eloquent-viewable']['cooldown']['key'];
    }

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
    protected function forgetExpiredCooldowns(string $key)
    {
        $currentTime = Carbon::now();
        $viewHistory = $this->session->get($key, []);

        foreach ($viewHistory as $record) {
            if ($record['expires_at']->lte($currentTime)) {
                $recordId = array_search($record['viewable_id'], array_column($record, 'viewable_id'));

                $this->session->pull($key.$recordId);
            }
        }
    }

    /**
     * Create a base key from the given viewable model.
     *
     * Returns for example:
     * => `eloquent-viewable.session.key.app-models-post`
     */
    protected function createNamespaceKey(Viewable $viewable, string $collection = null): string
    {
        $key = $this->primaryKey;
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
        $key .= ".{$viewable->getKey()}";

        return $key;
    }
}
