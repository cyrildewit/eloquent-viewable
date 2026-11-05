<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns\Contracts;

use DateTimeInterface;

interface CooldownStore
{
    public function has(string $key): bool;

    public function put(string $key, DateTimeInterface $expiresAt): void;
}
