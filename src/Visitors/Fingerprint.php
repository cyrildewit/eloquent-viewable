<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Visitors;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Carbon;

final readonly class Fingerprint
{
    private const string MappedIpv4Prefix = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public function __construct(
        private Config $config,
        private CacheFactory $cache,
    ) {}

    /** @throws InvalidConfiguration */
    public function of(Visitor $visitor): string
    {
        $network = $this->network($visitor->ip());

        $userAgent = $visitor->userAgent() ?? '';

        return hash_hmac('sha256', "{$network}|{$userAgent}", $this->salt());
    }

    private function network(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        $packed = (string) inet_pton($ip);

        if (str_starts_with($packed, self::MappedIpv4Prefix)) {
            $packed = substr($packed, strlen(self::MappedIpv4Prefix));
        }

        $kept = strlen($packed) === 4 ? 3 : 6;

        return (string) inet_ntop(substr($packed, 0, $kept).str_repeat("\0", strlen($packed) - $kept));
    }

    /** @throws InvalidConfiguration */
    private function salt(): string
    {
        [$window, $expires] = $this->window(Carbon::now());
        $key = "{$this->config->fingerprintKey()}:{$window}";
        $cache = $this->cache->store($this->config->fingerprintCacheStore());

        $salt = $cache->get($key);

        if (is_string($salt)) {
            return $salt;
        }

        $salt = bin2hex(random_bytes(32));

        $addedByThisRequest = $cache->add($key, $salt, $expires);

        if ($addedByThisRequest) {
            return $salt;
        }

        $stored = $cache->get($key);

        return is_string($stored) ? $stored : $salt;
    }

    /**
     * The name of the window the salt belongs to, and the moment it ends.
     *
     * @return array{string, Carbon}
     *
     * @throws InvalidConfiguration
     */
    private function window(Carbon $now): array
    {
        return match ($this->config->fingerprintRotation()) {
            'day' => [$now->toDateString(), $now->copy()->startOfDay()->addDay()],
            'week' => [$now->format('o-\\WW'), $now->copy()->startOfWeek(Carbon::MONDAY)->addWeek()],
            'month' => [$now->format('Y-m'), $now->copy()->startOfMonth()->addMonth()],
        };
    }
}
