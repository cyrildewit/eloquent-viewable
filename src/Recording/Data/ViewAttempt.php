<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

final readonly class ViewAttempt
{
    /**
     * @param  ?array<string, mixed>  $context
     * @param  ?Arrival  $arrival  how the visitor reached the page, when the request is not the page's own, as for the beacon
     */
    public function __construct(
        public Viewable $viewable,
        public Visitor $visitor,
        public ?string $collection = null,
        public ?CarbonInterface $cooldown = null,
        public ?bool $queue = null,
        public ?Model $viewer = null,
        public ?array $context = null,
        public ?Arrival $arrival = null,
    ) {}

    public function withViewer(?Model $viewer): self
    {
        return new self($this->viewable, $this->visitor, $this->collection, $this->cooldown, $this->queue, $viewer, $this->context, $this->arrival);
    }
}
