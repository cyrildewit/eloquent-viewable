<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Listeners;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Milestones\Actions\CheckMilestones;
use CyrildeWit\EloquentViewable\Milestones\Exceptions\MilestonesNotInstalled;
use CyrildeWit\EloquentViewable\Querying\Counters\Events\CountersRecounted;
use JsonException;

/**
 * Every recount names the models whose counter columns it wrote, so their
 * milestones are checked right after.
 *
 * @internal
 */
final readonly class CheckAfterRecount
{
    public function __construct(
        private CheckMilestones $milestones,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws MilestonesNotInstalled
     */
    public function __invoke(CountersRecounted $event): void
    {
        $this->milestones->recounted($event->class, $event->keys);
    }
}
