<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

/**
 * Implement this on a view source whose counts depend on settings of its own,
 * such as the connection or the table it reads. `remember()` keeps entries
 * apart per identity, so changing those settings starts fresh entries.
 * Without it a source is identified by its driver name only.
 */
interface IdentifiesSource
{
    public function cacheIdentity(): string;
}
