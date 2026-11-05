<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function userAgentAttempt(?string $userAgent): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('userAgent')->andReturn($userAgent);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses a request without a user agent and allows one with', function (): void {
    $guard = new IgnoreMissingUserAgent;

    expect($guard->allows(userAgentAttempt(null)))->toBeFalse()
        ->and($guard->allows(userAgentAttempt('Mozilla/5.0')))->toBeTrue();
});
