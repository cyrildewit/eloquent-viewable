<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use DateTime;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Session\Session;

class CooldownManager
{
    /**
     * The session repository instance.
     *
     * @var \Illuminate\Contracts\Session\Repository
     */
    protected $session;

    /**
     * The primary key under which history is stored.
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * Create a new view session history instance.
     *
     * @return void
     */
    public function __construct(ConfigRepository $config, Session $session)
    {
        $this->session = $session;
        $this->primaryKey = $config['eloquent-viewable']['cooldown']['key'];
    }

    /**
     * Push a cooldown for the viewable model with an expiry date.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \DateTime  $expiresAt
     * @param  string|null  $collection
     * @return bool
     */
    public function push(Viewable $viewable, DateTime $expiresAt, string $collection = null): bool
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
     *
     * @param  string  $key
     * @return bool
     */
    protected function has(string $viewableKey): bool
    {
        return $this->session->has($viewableKey);
    }

    /**
     * Create a cooldown for given viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \DateTime  $expiresAt
     * @return array
     */
    protected function createCooldown(Viewable $viewable, $expiresAt): array
    {
        return [
            'viewable_id' => $viewable->getKey(),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Remove all expired cooldowns from the session.
     *
     * @param  string  $key
     * @return void
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
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @return string
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
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  string  $collection
     * @return string
     */
    protected function createViewableKey(Viewable $viewable, string $collection = null): string
    {
        $key = $this->createNamespaceKey($viewable, $collection);
        $key .= ".{$viewable->getKey()}";

        return $key;
    }
}
