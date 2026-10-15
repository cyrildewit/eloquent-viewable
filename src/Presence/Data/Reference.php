<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Data;

/**
 * A model by its morph type and key, as presence keeps it.
 *
 * Both parts are URL encoded, so neither holds the `|` that joins them.
 */
final readonly class Reference
{
    public function __construct(
        public string $type,
        public int|string $id,
    ) {}

    public static function decode(string $member): self
    {
        $separator = strpos($member, '|');

        if ($separator === false) {
            return new self(rawurldecode($member), '');
        }

        return new self(
            rawurldecode(substr($member, 0, $separator)),
            rawurldecode(substr($member, $separator + 1)),
        );
    }

    public function encode(): string
    {
        $type = rawurlencode($this->type);
        $id = rawurlencode((string) $this->id);

        return "{$type}|{$id}";
    }
}
