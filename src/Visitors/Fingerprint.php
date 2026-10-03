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
        return hash_hmac('sha256', $this->network($visitor->ip()).'|'.($visitor->userAgent() ?? ''), $this->salt());
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
        $now = Carbon::now();
        $key = $this->config->fingerprintKey().':'.$now->toDateString();
        $cache = $this->cache->store($this->config->fingerprintCacheStore());

        $salt = $cache->get($key);

        if (is_string($salt)) {
            return $salt;
        }

        $salt = bin2hex(random_bytes(32));

        // Another request may have written the salt since the read above;
        // add() keeps the first one, so both hash under the same salt.
        if ($cache->add($key, $salt, $now->copy()->startOfDay()->addDay())) {
            return $salt;
        }

        $stored = $cache->get($key);

        return is_string($stored) ? $stored : $salt;
    }
}
