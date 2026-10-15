<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function globalPrivacyControlAttempt(bool $hasSignal): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('hasGlobalPrivacyControl')->andReturn($hasSignal);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses a visitor sending the signal and allows one without', function (): void {
    $guard = new IgnoreGlobalPrivacyControl;

    expect($guard->allows(globalPrivacyControlAttempt(true)))->toBeFalse()
        ->and($guard->allows(globalPrivacyControlAttempt(false)))->toBeTrue();
});
