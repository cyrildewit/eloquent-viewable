<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * It is dispatched once the counter columns of the models of `class` with these
 * keys are written: once per chunk by `views:recount` and `views:maintain`, and
 * once for a model whose views were destroyed or erased. So a listener sees
 * every write to a counter column. The keys are the models recounted, not only
 * those whose values changed.
 */
final readonly class CountersRecounted
{
    /**
     * @param  class-string<Model&Viewable>  $class
     * @param  list<int|string>  $keys
     */
    public function __construct(
        public string $class,
        public array $keys,
    ) {}
}
