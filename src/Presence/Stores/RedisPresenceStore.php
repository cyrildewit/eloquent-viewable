<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Precision;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Redis;
use Throwable;

/**
 * Keeps presence in Redis: a sorted set of visitor hashes scored by when they
 * were last seen per scope, or a HyperLogLog per scope and minute, plus a
 * sorted set of the viewables seen, which `top()` ranks.
 *
 * Every key shares one hash tag, so each write and read is one Lua script
 * that a Redis Cluster runs on a single slot. A key expires once nobody has
 * been seen in it for twice the window plus one minute.
 */
final readonly class RedisPresenceStore implements PresenceStore
{
    /*
     * KEYS: the visitor keys, the active keys, then the viewer keys.
     * ARGV: now, cutoff, ttl, visitor, viewable, viewer, the number of
     * visitor keys, the number of active keys, and 1 when approximate.
     */
    private const string Touch = <<<'LUA'
        local now = tonumber(ARGV[1])
        local visitorKeys = tonumber(ARGV[7])
        local activeKeys = tonumber(ARGV[8])

        local function remember(key, member)
            redis.call('ZADD', key, now, member)
            redis.call('ZREMRANGEBYSCORE', key, '-inf', ARGV[2])
            redis.call('EXPIRE', key, ARGV[3])
        end

        for index = 1, visitorKeys do
            if ARGV[9] == '1' then
                redis.call('PFADD', KEYS[index], ARGV[4])
                redis.call('EXPIRE', KEYS[index], ARGV[3])
            else
                remember(KEYS[index], ARGV[4])
            end
        end

        for index = visitorKeys + 1, visitorKeys + activeKeys do
            remember(KEYS[index], ARGV[5])
        end

        for index = visitorKeys + activeKeys + 1, #KEYS do
            remember(KEYS[index], ARGV[6])
        end

        return 1
        LUA;

    /*
     * KEYS: the visitor keys, then the viewer keys.
     * ARGV: visitor, viewer, the number of visitor keys.
     */
    private const string Leave = <<<'LUA'
        local visitorKeys = tonumber(ARGV[3])

        for index = 1, visitorKeys do
            redis.call('ZREM', KEYS[index], ARGV[1])
        end

        for index = visitorKeys + 1, #KEYS do
            redis.call('ZREM', KEYS[index], ARGV[2])
        end

        return 1
        LUA;

    /*
     * KEYS: the keys of every scope, in groups of the given size.
     * ARGV: cutoff, group size, and 1 when approximate.
     */
    private const string Count = <<<'LUA'
        local size = tonumber(ARGV[2])
        local counts = {}

        for index = 1, #KEYS, size do
            if ARGV[3] == '1' then
                counts[#counts + 1] = redis.call('PFCOUNT', unpack(KEYS, index, index + size - 1))
            else
                counts[#counts + 1] = redis.call('ZCOUNT', KEYS[index], '(' .. ARGV[1], '+inf')
            end
        end

        return counts
        LUA;

    /*
     * KEYS: one sorted set.
     * ARGV: cutoff, limit.
     */
    private const string Recent = <<<'LUA'
        return redis.call('ZREVRANGEBYSCORE', KEYS[1], '+inf', '(' .. ARGV[1], 'LIMIT', 0, ARGV[2])
        LUA;

    private string $tag;

    public function __construct(
        private PhpRedisConnection|PredisConnection $connection,
        string $prefix,
        private int $window,
        private Precision $precision,
        private ExceptionHandler $exceptions,
    ) {
        $this->tag = "{{$prefix}}";
    }

    /**
     * A failure is reported rather than thrown, so Redis being down never
     * stops a view from being recorded.
     */
    public function touch(Sighting $sighting): void
    {
        $seenAt = $sighting->seenAt->getTimestamp();
        $minute = intdiv($seenAt, 60);

        $visitorKeys = array_map(
            fn (Scope $scope): string => $this->approximate()
                ? $this->bucketKey($scope, $minute)
                : $this->key('visitors', $scope),
            $sighting->scopes(),
        );

        $activeKeys = [
            $this->key('active', new Scope),
            $this->key('active', new Scope($sighting->type)),
        ];

        $viewerKeys = $sighting->viewer instanceof Reference
            ? array_map(fn (Scope $scope): string => $this->key('viewers', $scope), $sighting->scopes())
            : [];

        $this->report(fn (): mixed => $this->run(self::Touch, [...$visitorKeys, ...$activeKeys, ...$viewerKeys], [
            $seenAt,
            $seenAt - $this->window,
            $this->window * 2 + 60,
            $sighting->visitor,
            $sighting->viewable()->encode(),
            $sighting->viewer?->encode() ?? '',
            count($visitorKeys),
            count($activeKeys),
            $this->approximate() ? 1 : 0,
        ]));
    }

    /**
     * A HyperLogLog cannot forget one visitor, so in approximate mode the
     * visitor stays counted until their minute passes out of the window.
     */
    public function leave(Sighting $sighting): void
    {
        if ($this->approximate()) {
            return;
        }

        $visitorKeys = array_map(fn (Scope $scope): string => $this->key('visitors', $scope), $sighting->viewableScopes());

        $viewerKeys = $sighting->viewer instanceof Reference
            ? array_map(fn (Scope $scope): string => $this->key('viewers', $scope), $sighting->viewableScopes())
            : [];

        $this->report(fn (): mixed => $this->run(self::Leave, [...$visitorKeys, ...$viewerKeys], [
            $sighting->visitor,
            $sighting->viewer?->encode() ?? '',
            count($visitorKeys),
        ]));
    }

    /**
     * In approximate mode a scope is counted over every minute the window
     * touches, so it counts up to a minute more than the window.
     *
     * @param  list<Scope>  $scopes
     * @return list<int>
     *
     * @throws PresenceFailed
     */
    public function countVisitors(array $scopes, CarbonInterface $since): array
    {
        if ($scopes === []) {
            return [];
        }

        $minutes = $this->approximate()
            ? range(intdiv($since->getTimestamp(), 60), intdiv(Carbon::now()->getTimestamp(), 60))
            : [];

        $keys = [];

        foreach ($scopes as $scope) {
            if (! $this->approximate()) {
                $keys[] = $this->key('visitors', $scope);

                continue;
            }

            foreach ($minutes as $minute) {
                $keys[] = $this->bucketKey($scope, $minute);
            }
        }

        $counts = $this->run(self::Count, $keys, [
            $since->getTimestamp(),
            max(count($minutes), 1),
            $this->approximate() ? 1 : 0,
        ]);

        return array_map(
            static fn (mixed $count): int => is_int($count) ? $count : 0,
            array_values(is_array($counts) ? $counts : []),
        );
    }

    /**
     * @return list<Reference>
     *
     * @throws PresenceFailed
     */
    public function active(?string $type, CarbonInterface $since, int $limit): array
    {
        return $this->recent($this->key('active', new Scope($type)), $since, $limit);
    }

    /**
     * @return list<Reference>
     *
     * @throws PresenceFailed
     */
    public function viewers(Scope $scope, CarbonInterface $since, int $limit): array
    {
        return $this->recent($this->key('viewers', $scope), $since, $limit);
    }

    /**
     * @return list<Reference>
     *
     * @throws PresenceFailed
     */
    private function recent(string $key, CarbonInterface $since, int $limit): array
    {
        $members = $this->run(self::Recent, [$key], [$since->getTimestamp(), $limit]);

        $references = [];

        foreach (is_array($members) ? $members : [] as $member) {
            if (is_string($member)) {
                $references[] = Reference::decode($member);
            }
        }

        return $references;
    }

    private function approximate(): bool
    {
        return $this->precision === Precision::Approximate;
    }

    private function key(string $kind, Scope $scope): string
    {
        return "{$this->tag}:{$kind}:{$scope->id()}";
    }

    private function bucketKey(Scope $scope, int $minute): string
    {
        return "{$this->tag}:hll:{$scope->id()}:{$minute}";
    }

    /** @param  callable(): mixed  $write */
    private function report(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }

    /**
     * phpredis answers a failed script with `false` and keeps the error,
     * where Predis throws.
     *
     * @param  list<string>  $keys
     * @param  list<int|string>  $arguments
     *
     * @throws PresenceFailed
     */
    private function run(string $script, array $keys, array $arguments): mixed
    {
        $result = $this->connection->eval($script, count($keys), ...$keys, ...array_map(strval(...), $arguments));

        if ($result === false) {
            throw PresenceFailed::redisScript($this->lastError());
        }

        return $result;
    }

    private function lastError(): string
    {
        $client = $this->connection->client();

        if (! $client instanceof Redis) {
            return 'no error was given';
        }

        $error = (string) $client->getLastError();

        $client->clearLastError();

        return $error;
    }
}
