<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\ConfigReader;

/**
 * Typed access to the `presence` keys of the package config file.
 *
 * @internal
 */
final readonly class PresenceConfig extends ConfigReader
{
    public function enabled(): bool
    {
        return $this->bool('presence.enabled');
    }

    /** @throws InvalidConfiguration */
    public function driver(): string
    {
        return $this->nonEmptyString('presence.driver');
    }

    /** @throws InvalidConfiguration */
    public function window(): int
    {
        return $this->positiveInteger('presence.window');
    }

    /**
     * @return 'exact'|'approximate'
     *
     * @throws InvalidConfiguration
     */
    public function precision(): string
    {
        return $this->oneOf('presence.precision', ['exact', 'approximate'], 'exact');
    }

    /** @throws InvalidConfiguration */
    public function heartbeat(): int
    {
        return $this->positiveInteger('presence.heartbeat');
    }

    public function exposesCount(): bool
    {
        return $this->bool('presence.expose_count');
    }

    public function tracksViewers(): bool
    {
        return $this->bool('presence.viewers');
    }

    /** @throws InvalidConfiguration */
    public function maxCandidates(): int
    {
        return $this->positiveInteger('presence.max_candidates');
    }

    /** @throws InvalidConfiguration */
    public function redisConnection(): ?string
    {
        return $this->string('presence.redis.connection');
    }

    /** @throws InvalidConfiguration */
    public function redisPrefix(): string
    {
        return $this->nonEmptyString('presence.redis.prefix');
    }
}
