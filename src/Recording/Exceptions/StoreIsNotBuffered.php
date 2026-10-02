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
        return new self('The configured view store `'.$store::class.'` writes views straight to their table, so there is nothing to flush. Flushing needs a store that implements `'.BufferedViewStore::class.'`, such as the `redis` driver.');
    }
}
