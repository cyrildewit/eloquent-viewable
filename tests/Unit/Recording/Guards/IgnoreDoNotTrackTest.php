<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function doNotTrackAttempt(bool $hasHeader): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('hasDoNotTrackHeader')->andReturn($hasHeader);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses a visitor sending the header and allows one without', function (): void {
    $guard = new IgnoreDoNotTrack;

    expect($guard->allows(doNotTrackAttempt(true)))->toBeFalse()
        ->and($guard->allows(doNotTrackAttempt(false)))->toBeTrue();
});
