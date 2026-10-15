<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;

enum PeriodAnchor: string
{
    case Past = 'past';

    case Sub = 'sub';

    public function dateTime(?DateTimeZone $timezone = null): CarbonInterface
    {
        return match ($this) {
            self::Past => Carbon::today($timezone),
            self::Sub => Carbon::now($timezone),
        };
    }
}
