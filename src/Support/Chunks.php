<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Closure;
use Countable;

/**
 * Runs work a chunk at a time until a chunk comes back short, which is how
 * every scheduled action walks a table. The deadline is asked before each
 * chunk, so a run overshoots by at most one.
 *
 * @internal
 */
final class Chunks
{
    /**
     * @template TBatch of array<mixed>|Countable
     *
     * @param  Closure(): TBatch  $next  the next chunk, at most `$chunk` long
     * @param  Closure(TBatch): int  $apply  applies a chunk that is not empty and returns how many rows it changed
     */
    public static function run(Deadline $deadline, int $chunk, Closure $next, Closure $apply): Progress
    {
        $count = 0;

        do {
            if ($deadline->passed()) {
                return new Progress($count, stopped: true);
            }

            $batch = $next();

            if (count($batch) > 0) {
                $count += $apply($batch);
            }
        } while (count($batch) === $chunk);

        return new Progress($count, stopped: false);
    }
}
