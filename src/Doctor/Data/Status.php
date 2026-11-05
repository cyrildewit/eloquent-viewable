<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Data;

enum Status: string
{
    case Pass = 'pass';
    case Advice = 'advice';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';

    public function icon(): string
    {
        return match ($this) {
            self::Pass => '✓',
            self::Advice => 'i',
            self::Warning => '!',
            self::Failure => '✗',
            self::Skipped => '-',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pass => 'green',
            self::Advice => 'blue',
            self::Warning => 'yellow',
            self::Failure => 'red',
            self::Skipped => 'gray',
        };
    }
}
