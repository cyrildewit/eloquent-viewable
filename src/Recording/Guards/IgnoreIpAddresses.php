<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Support\Config;

final readonly class IgnoreIpAddresses implements RecordingGuard
{
    public function __construct(private Config $config) {}

    public function allows(ViewAttempt $attempt): bool
    {
        return ! in_array($attempt->visitor->ip(), $this->config->ignoredIpAddresses(), true);
    }
}
