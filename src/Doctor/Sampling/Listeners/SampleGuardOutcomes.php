<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Sampling\Listeners;

use CyrildeWit\EloquentViewable\Doctor\Sampling\GuardSamples;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;

/** @internal */
final readonly class SampleGuardOutcomes
{
    public function __construct(
        private GuardSamples $samples,
    ) {}

    public function recorded(): void
    {
        $this->samples->countRecorded();
    }

    public function refused(ViewSkipped $event): void
    {
        $this->samples->countRefused($event->guard);
    }
}
