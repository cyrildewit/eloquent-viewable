<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonImmutable;
use Closure;

/**
 * How long a maintenance run may keep starting new work. A run asks before
 * every unit of work, such as a bucket, a day or a chunk, and stops once the
 * deadline has passed. The unit in progress always finishes, so a run
 * overshoots by at most one unit, and the marks it leaves let the next run
 * pick up from there.
 *
 * Every question also beats the heart of the run, which keeps the lock of a
 * long run from expiring underneath it.
 *
 * @internal
 */
final readonly class Deadline
{
    /** @param  ?Closure(): void  $heartbeat */
    private function __construct(
        private ?CarbonImmutable $at,
        private ?Closure $heartbeat = null,
    ) {}

    public static function none(): self
    {
        return new self(null);
    }

    public static function in(int $seconds): self
    {
        return new self(CarbonImmutable::now()->addSeconds($seconds));
    }

    /**
     * The heartbeat runs after any the deadline already has.
     *
     * @param  Closure(): void  $heartbeat
     */
    public function withHeartbeat(Closure $heartbeat): self
    {
        $previous = $this->heartbeat;

        if (! $previous instanceof Closure) {
            return new self($this->at, $heartbeat);
        }

        return new self($this->at, function () use ($previous, $heartbeat): void {
            $previous();
            $heartbeat();
        });
    }

    public function passed(): bool
    {
        if ($this->heartbeat instanceof Closure) {
            ($this->heartbeat)();
        }

        if (! $this->at instanceof CarbonImmutable) {
            return false;
        }

        return CarbonImmutable::now() >= $this->at;
    }
}
