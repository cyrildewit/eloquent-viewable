<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * The `retention` config, read and checked once.
 */
final readonly class RetentionPolicy
{
    /**
     * @param  list<'visitor'|'viewer'|'context'>  $anonymiseColumns
     *
     * @throws InvalidConfiguration
     */
    public function __construct(
        public ?Duration $anonymiseAfter,
        public array $anonymiseColumns,
        public ?Duration $pruneAfter,
        public int $chunk,
    ) {
        if ($anonymiseAfter instanceof Duration
            && $pruneAfter instanceof Duration
            && $anonymiseAfter->isLongerThan($pruneAfter, Carbon::now())) {
            throw InvalidConfiguration::anonymisedAfterPruned($anonymiseAfter->shorthand(), $pruneAfter->shorthand());
        }
    }

    /** @throws InvalidConfiguration */
    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->anonymiseAfter(),
            $config->anonymiseColumns(),
            $config->pruneAfter(),
            $config->retentionChunk(),
        );
    }
}
