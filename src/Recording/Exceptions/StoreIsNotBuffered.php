<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use Exception;

final class StoreIsNotBuffered extends Exception implements EloquentViewableException
{
    public static function forStore(ViewStore $store): self
    {
        $class = $store::class;

        $buffered = BufferedViewStore::class;

        return new self("The configured view store `{$class}` writes views straight to their table, so there is nothing to flush. Flushing needs a store that implements `{$buffered}`, such as the `redis` driver.");
    }
}
