<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use DateTimeZone;

/**
 * A timezone named by an identifier such as `Europe/Amsterdam`. Offsets and
 * abbreviations are refused, because they carry no daylight saving rules.
 */
final class Timezone extends DateTimeZone
{
    /** @throws InvalidTimezone */
    public function __construct(string $timezone)
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw InvalidTimezone::notAnIdentifier($timezone);
        }

        parent::__construct($timezone);
    }

    /**
     * The zone `viewed_at` is stored as the wall clock of.
     */
    public static function application(): self
    {
        return new self(date_default_timezone_get());
    }

    /** @throws InvalidTimezone */
    public static function from(DateTimeZone|string $timezone): self
    {
        if ($timezone instanceof self) {
            return $timezone;
        }

        return new self($timezone instanceof DateTimeZone ? $timezone->getName() : $timezone);
    }
}
