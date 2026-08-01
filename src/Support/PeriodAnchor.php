<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

enum PeriodAnchor: string
{
    case Past = 'past';

    case Sub = 'sub';

    public function dateTime(): CarbonInterface
    {
        return match ($this) {
            self::Past => Carbon::today(),
            self::Sub => Carbon::now(),
        };
    }
}
