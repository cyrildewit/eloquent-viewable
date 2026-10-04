<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Support\Config;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Drops views from `recording.ignored_ip_addresses`, which holds addresses
 * and CIDR ranges such as `10.0.0.0/8`.
 */
final readonly class IgnoreIpAddresses implements RecordingGuard
{
    public function __construct(private Config $config) {}

    public function allows(ViewAttempt $attempt): bool
    {
        $ip = $attempt->visitor->ip();

        if ($ip === null) {
            return true;
        }

        return ! IpUtils::checkIp($ip, $this->config->ignoredIpAddresses());
    }
}
