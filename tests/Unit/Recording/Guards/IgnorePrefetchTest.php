<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function prefetchAttempt(bool $isPrefetch): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('isPrefetch')->andReturn($isPrefetch);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses a prefetch and allows a visit', function (): void {
    $guard = new IgnorePrefetch;

    expect($guard->allows(prefetchAttempt(true)))->toBeFalse()
        ->and($guard->allows(prefetchAttempt(false)))->toBeTrue();
});
