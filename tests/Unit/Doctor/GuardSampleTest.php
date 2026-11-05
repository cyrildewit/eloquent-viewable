<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Data\GuardSample;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;

it('adds the recorded and refused attempts up', function (): void {
    $sample = new GuardSample(7, recorded: 6, refused: [IgnoreCrawlers::class => 3, IgnorePrefetch::class => 1]);

    expect($sample->attempts())->toBe(10)
        ->and($sample->refusedBy(IgnoreCrawlers::class))->toBe(3)
        ->and($sample->share(IgnoreCrawlers::class))->toBe(0.3);
});

it('has refused nothing for a guard it did not sample', function (): void {
    $sample = new GuardSample(7, recorded: 4, refused: []);

    expect($sample->refusedBy(IgnoreCrawlers::class))->toBe(0)
        ->and($sample->share(IgnoreCrawlers::class))->toBe(0.0);
});

it('has no share without attempts', function (): void {
    expect(new GuardSample(7, recorded: 0, refused: [IgnoreCrawlers::class => 0])->share(IgnoreCrawlers::class))->toBe(0.0);
});
