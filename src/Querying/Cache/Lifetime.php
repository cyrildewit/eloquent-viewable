<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLifetime;

/**
 * How long a remembered read is served. It is fresh until `$freshUntil`.
 * With `$staleUntil`, the entry is kept until then, and a read past
 * `$freshUntil` returns it at once and refreshes it after the response.
 *
 * @internal
 */
final readonly class Lifetime
{
    /** @throws InvalidLifetime */
    public function __construct(
        public CarbonInterface $freshUntil,
        public ?CarbonInterface $staleUntil = null,
    ) {
        if ($staleUntil instanceof CarbonInterface && $staleUntil <= $freshUntil) {
            throw InvalidLifetime::staleNotAfterLifetime();
        }
    }

    public function servesStale(): bool
    {
        return $this->staleUntil instanceof CarbonInterface;
    }

    public function expiresAt(): CarbonInterface
    {
        return $this->staleUntil ?? $this->freshUntil;
    }
}
