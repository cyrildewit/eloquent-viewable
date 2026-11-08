<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

/**
 * The server behind the Redis stream. Valkey speaks the same protocol and
 * reports the Redis release it is compatible with next to its own version.
 */
class RedisServer
{
    public function __construct(
        public string $name,
        public string $version,
        public string $redisVersion,
    ) {}

    /**
     * @param  array<array-key, mixed>  $info  the fields of `INFO server`, flat as phpredis returns them or under `Server` as Predis does
     */
    public static function fromInfo(array $info): ?self
    {
        $fields = is_array($info['Server'] ?? null) ? $info['Server'] : $info;
        $redisVersion = $fields['redis_version'] ?? null;

        if (! is_string($redisVersion)) {
            return null;
        }

        $valkeyVersion = $fields['valkey_version'] ?? null;

        if (($fields['server_name'] ?? null) === 'valkey' && is_string($valkeyVersion)) {
            return new self('Valkey', $valkeyVersion, $redisVersion);
        }

        return new self('Redis', $redisVersion, $redisVersion);
    }

    public function label(): string
    {
        return "{$this->name} {$this->version}";
    }
}
