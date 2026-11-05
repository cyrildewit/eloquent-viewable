<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

final readonly class RetentionPolicy
{
    /**
     * @param  list<'visitor'|'viewer'|'context'|'dimensions'>  $anonymiseColumns
     *
     * @throws InvalidConfiguration
     */
    public function __construct(
        public ?Duration $anonymiseAfter,
        public array $anonymiseColumns,
        public ?Duration $pruneAfter,
        public int $chunk,
    ) {
        $this->guardAnonymisingBeforePruning();
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

    /** @throws InvalidConfiguration */
    private function guardAnonymisingBeforePruning(): void
    {
        if (! $this->anonymiseAfter instanceof Duration) {
            return;
        }

        if (! $this->pruneAfter instanceof Duration) {
            return;
        }

        if (! $this->anonymiseAfter->isLongerThan($this->pruneAfter, Carbon::now())) {
            return;
        }

        throw InvalidConfiguration::anonymisedAfterPruned($this->anonymiseAfter->shorthand(), $this->pruneAfter->shorthand());
    }
}
