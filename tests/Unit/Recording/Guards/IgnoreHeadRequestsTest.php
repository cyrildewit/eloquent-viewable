<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function headAttempt(bool $isHeadRequest): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('isHeadRequest')->andReturn($isHeadRequest);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses a HEAD request and allows any other', function (): void {
    $guard = new IgnoreHeadRequests;

    expect($guard->allows(headAttempt(true)))->toBeFalse()
        ->and($guard->allows(headAttempt(false)))->toBeTrue();
});
