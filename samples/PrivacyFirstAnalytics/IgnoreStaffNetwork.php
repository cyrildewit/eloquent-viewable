<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Drops views from the office and the VPN, where the writers proofread the
 * docs all day. `recording.ignored_ip_addresses` compares whole addresses,
 * and a network hands out hundreds of them.
 */
final readonly class IgnoreStaffNetwork implements RecordingGuard
{
    private const array Networks = ['10.20.0.0/16', '2001:db8:20::/48'];

    public function allows(ViewAttempt $attempt): bool
    {
        $ip = $attempt->visitor->ip();

        if ($ip === null) {
            return true;
        }

        return ! IpUtils::checkIp($ip, self::Networks);
    }
}
