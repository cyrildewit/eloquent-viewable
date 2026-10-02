<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

final readonly class ViewAttempt
{
    public function __construct(
        public Viewable $viewable,
        public Visitor $visitor,
        public ?string $collection = null,
        public ?CarbonInterface $cooldown = null,
        public ?bool $queue = null,
    ) {}
}
