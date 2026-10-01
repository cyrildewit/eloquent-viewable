<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

final readonly class ViewsQuery
{
    public function __construct(
        public ?Period $period = null,
        public ?string $collection = null,
        public bool $unique = false,
    ) {}
}
