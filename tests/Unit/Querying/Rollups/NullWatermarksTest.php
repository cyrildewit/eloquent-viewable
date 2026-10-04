<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Rollups\NullWatermarks;

it('leaves every cutoff where it is', function (): void {
    $cutoff = Carbon::parse('2026-03-01 00:00:00');

    expect(new NullWatermarks()->clamp($cutoff))->toBe($cutoff);
});

it('has nothing to wait for after folding either', function (): void {
    $watermarks = new NullWatermarks;

    expect($watermarks->afterFolding())->toBe($watermarks);
});
