<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Contracts;

use CyrildeWit\EloquentViewable\Doctor\Data\Finding;

/**
 * One part of the setup `views:doctor` looks at. A check that does not apply
 * to the current config says so with a skipped finding rather than none.
 */
interface Check
{
    public function name(): string;

    /** @return iterable<int, Finding> */
    public function run(): iterable;
}
