<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Refolder;

final readonly class NullRefolder implements Refolder
{
    public function floor(): ?CarbonInterface
    {
        return null;
    }

    public function refold(CarbonInterface $from): void {}
}
