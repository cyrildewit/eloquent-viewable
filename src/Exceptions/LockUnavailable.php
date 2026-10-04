<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Exception;

final class LockUnavailable extends Exception implements EloquentViewableException
{
    public static function storeCannotLock(string $store): self
    {
        return new self("The `{$store}` cache store cannot hold an atomic lock, which keeps two servers from rolling up, anonymising or pruning views at once. Set `eloquent-viewable.querying.cache.store` to a store that can, such as `redis` or `database`.");
    }
}
