<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * It is dispatched once the counter columns of the models with these keys are
 * written, by every recount, so a listener sees every write to a counter
 * column.
 *
 * @internal
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
