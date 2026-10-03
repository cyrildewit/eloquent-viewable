<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
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

    /**
     * @return class-string<View>
     *
     * @throws InvalidConfiguration
     */
    public function viewModel(): string
    {
        $value = $this->get('models.view.class', View::class);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeViewModel('models.view.class', $value);
        }

        if (! is_a($value, View::class, true)) {
            throw InvalidConfiguration::mustBeViewModel('models.view.class', $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    public function viewTable(): ?string
    {
        return $this->string('models.view.table_name');
    }

    /** @throws InvalidConfiguration */
    public function viewConnection(): ?string
    {
        return $this->string('models.view.connection');
    }

    /** @throws InvalidConfiguration */
    public function storeDriver(): string
    {
        return $this->nonEmptyString('recording.store.driver');
    }

    /** @throws InvalidConfiguration */
    public function redisConnection(): ?string
    {
        return $this->string('recording.store.redis.connection');
    }

    /** @throws InvalidConfiguration */
    public function redisStream(): string
    {
        return $this->nonEmptyString('recording.store.redis.stream');
    }

    /** @throws InvalidConfiguration */
    public function redisGroup(): string
    {
        return $this->nonEmptyString('recording.store.redis.group');
    }

    /** @throws InvalidConfiguration */
    public function redisLandingDriver(): string
    {
        return $this->nonEmptyString('recording.store.redis.landing');
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    public function guards(): array
    {
        $value = $this->get('recording.guards');

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $value);
        }

        foreach ($value as $guard) {
            if (! is_string($guard)) {
                throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $guard);
            }

            if (! class_exists($guard)) {
                throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $guard);
            }
        }

        /** @var list<class-string> */
        return array_values($value);
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    public function ignoredIpAddresses(): array
    {
        $value = (array) $this->get('recording.ignored_ip_addresses', []);

        foreach ($value as $ipAddress) {
            if (! is_string($ipAddress)) {
                throw InvalidConfiguration::mustBeListOfStrings('recording.ignored_ip_addresses', $ipAddress);
            }
        }

        /** @var list<string> */
        return array_values($value);
    }

    public function queueEnabled(): bool
    {
        return (bool) $this->get('recording.queue.enabled', false);
    }

    /** @throws InvalidConfiguration */
    public function queueConnection(): ?string
    {
        return $this->string('recording.queue.connection');
    }

    /** @throws InvalidConfiguration */
    public function queueName(): ?string
    {
        return $this->string('recording.queue.queue');
    }

    public function viewerEnabled(): bool
    {
        return (bool) $this->get('recording.viewer.enabled', false);
    }

    /** @throws InvalidConfiguration */
    public function viewerGuard(): ?string
    {
        return $this->string('recording.viewer.guard');
    }

    /** @throws InvalidConfiguration */
    public function sourceDriver(): string
    {
        return $this->nonEmptyString('querying.source.driver');
    }

    /** @throws InvalidConfiguration */
    public function cacheKey(): string
    {
        return $this->nonEmptyString('querying.cache.key');
    }

    /** @throws InvalidConfiguration */
    public function cacheStore(): ?string
    {
        return $this->string('querying.cache.store');
    }

    /** @throws InvalidConfiguration */
    public function maxIntervals(): int
    {
        return $this->positiveInteger('querying.max_intervals');
    }

    /** @throws InvalidConfiguration */
    public function visitorCookieName(): string
    {
        return $this->nonEmptyString('visitor.cookie.name');
    }

    /** @throws InvalidConfiguration */
    public function visitorCookieLifetime(): int
    {
        return $this->positiveInteger('visitor.cookie.lifetime');
    }

    /**
     * @return 'cookie'|'viewer'|'fingerprint'
     *
     * @throws InvalidConfiguration
     */
    public function visitorIdentity(): string
    {
        $value = $this->get('visitor.identity', 'cookie');

        if (! in_array($value, ['cookie', 'viewer', 'fingerprint'], true)) {
            throw InvalidConfiguration::mustBeOneOf('visitor.identity', ['cookie', 'viewer', 'fingerprint'], $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    public function fingerprintKey(): string
    {
        return $this->nonEmptyString('visitor.fingerprint.key');
    }

    /** @throws InvalidConfiguration */
    public function fingerprintCacheStore(): ?string
    {
        return $this->string('visitor.fingerprint.store');
    }

    /** @throws InvalidConfiguration */
    public function cooldownStore(): string
    {
        return $this->nonEmptyString('cooldown.store');
    }

    /** @throws InvalidConfiguration */
    public function cooldownKey(): string
    {
        return $this->nonEmptyString('cooldown.key');
    }

    /** @throws InvalidConfiguration */
    public function cooldownCacheStore(): ?string
    {
        return $this->string('cooldown.cache.store');
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get("eloquent-viewable.{$key}", $default);
    }

    /** @throws InvalidConfiguration */
    private function string(string $key): ?string
    {
        $value = $this->get($key);

        if ($value !== null && ! is_string($value)) {
            throw InvalidConfiguration::mustBeStringOrNull($key, $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    private function nonEmptyString(string $key): string
    {
        $value = $this->get($key);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        if ($value === '') {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    private function positiveInteger(string $key): int
    {
        $value = $this->get($key);
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        if ($integer < 1) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        return $integer;
    }
}
