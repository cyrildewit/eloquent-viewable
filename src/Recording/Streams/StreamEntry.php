<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RedisStreamFailed;

/** @internal */
final readonly class StreamEntry
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public string $id,
        public array $fields,
    ) {}

    public static function of(string $id, mixed $fields): self
    {
        $map = [];

        if (is_array($fields)) {
            foreach ($fields as $name => $value) {
                if (is_scalar($value)) {
                    $map[(string) $name] = (string) $value;
                }
            }
        }

        return new self($id, $map);
    }

    /**
     * Redis has no null, so the optional columns are left out and filled
     * back in by `ViewRecord::fromPayload()`.
     *
     * @return array<string, string>
     */
    public static function encode(ViewRecord $record): array
    {
        $fields = [];

        foreach ($record->toPayload() as $name => $value) {
            if ($value !== null) {
                $fields[$name] = (string) $value;
            }
        }

        return $fields;
    }

    /**
     * An entry deleted from the stream while a consumer had it pending is
     * handed back without fields.
     */
    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /**
     * Redis ids start with the millisecond the entry was appended at.
     */
    public function appendedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampMs((int) strtok($this->id, '-'));
    }

    /** @throws RedisStreamFailed */
    public function record(): ViewRecord
    {
        if (! isset($this->fields['viewable_id'], $this->fields['viewable_type'], $this->fields['viewed_at'])) {
            throw RedisStreamFailed::malformedEntry($this->id);
        }

        /** @var array{viewable_id: string, viewable_type: string, visitor?: string, collection?: string, viewed_at: string} $payload */
        $payload = $this->fields;

        return ViewRecord::fromPayload($payload);
    }
}
