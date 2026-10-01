<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Config\Repository;

/**
 * Typed access to the keys of the package config file.
 *
 * Defaults live in `config/eloquent-viewable.php` and are merged in by the
 * service provider, so they are not repeated here. A key that has to hold a
 * value throws when it is missing or empty, because there is no fallback that
 * would be correct; a boolean or list key falls back to the shipped default,
 * because there is.
 *
 * @internal
 */
final readonly class Config
{
    public function __construct(
        private Repository $config,
    ) {}

    public function viewTable(): ?string
    {
        return $this->string('models.view.table_name');
    }

    public function viewConnection(): ?string
    {
        return $this->string('models.view.connection');
    }

    /**
     * @throws InvalidConfiguration
     */
    public function cacheKey(): string
    {
        return $this->nonEmptyString('cache.key');
    }

    public function cacheStore(): ?string
    {
        return $this->string('cache.store');
    }

    /**
     * @throws InvalidConfiguration
     */
    public function maxIntervals(): int
    {
        return $this->positiveInteger('max_intervals');
    }

    public function queueEnabled(): bool
    {
        return (bool) $this->get('queue.enabled', false);
    }

    public function queueConnection(): ?string
    {
        return $this->string('queue.connection');
    }

    public function queueName(): ?string
    {
        return $this->string('queue.queue');
    }

    /**
     * @throws InvalidConfiguration
     */
    public function cooldownKey(): string
    {
        return $this->nonEmptyString('cooldown.key');
    }

    public function ignoreBots(): bool
    {
        return (bool) $this->get('ignore_bots', true);
    }

    public function honorDoNotTrack(): bool
    {
        return (bool) $this->get('honor_dnt', false);
    }

    /**
     * @throws InvalidConfiguration
     */
    public function visitorCookieKey(): string
    {
        return $this->nonEmptyString('visitor_cookie_key');
    }

    /**
     * @return list<string>
     */
    public function ignoredIpAddresses(): array
    {
        return array_values(array_map(strval(...), (array) $this->get('ignored_ip_addresses', [])));
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get("eloquent-viewable.{$key}", $default);
    }

    private function string(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : (string) $value;
    }

    /**
     * @throws InvalidConfiguration
     */
    private function nonEmptyString(string $key): string
    {
        $value = $this->get($key);

        if (! is_string($value) || $value === '') {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        return $value;
    }

    /**
     * @throws InvalidConfiguration
     */
    private function positiveInteger(string $key): int
    {
        $value = $this->get($key);
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        return $integer;
    }
}
