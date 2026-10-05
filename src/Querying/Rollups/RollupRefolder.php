<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Refolder;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\RollupsNotInstalled;

final readonly class RollupRefolder implements Refolder
{
    public function __construct(private FoldViews $fold) {}

    /** @throws RollupsNotInstalled */
    public function floor(): ?CarbonInterface
    {
        return $this->fold->refoldableFrom();
    }

    /** @throws RollupsNotInstalled */
    public function refold(CarbonInterface $from): void
    {
        $this->fold->handle(from: $from);
    }
}
