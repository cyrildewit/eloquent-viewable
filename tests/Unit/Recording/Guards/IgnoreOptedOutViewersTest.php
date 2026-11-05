<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\OptOutUser;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

function optOutUser(bool $hidesReadingHistory): OptOutUser
{
    $user = new OptOutUser;
    $user->hidesReadingHistory = $hidesReadingHistory;

    return $user;
}

function signedInAttempt(?Model $signedIn, ?Model $viewer = null): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('viewer')->andReturn($signedIn);

    return new ViewAttempt(new Post(['id' => 1]), $visitor, viewer: $viewer);
}

it('allows a guest and a viewer that cannot opt out', function (): void {
    $guard = new IgnoreOptedOutViewers;

    expect($guard->allows(signedInAttempt(null)))->toBeTrue()
        ->and($guard->allows(signedInAttempt(new User)))->toBeTrue();
});

it('refuses a signed-in user who opted out and allows one who did not', function (): void {
    $guard = new IgnoreOptedOutViewers;

    expect($guard->allows(signedInAttempt(optOutUser(true))))->toBeFalse()
        ->and($guard->allows(signedInAttempt(optOutUser(false))))->toBeTrue();
});

it('asks the viewer of the attempt before the signed-in user', function (): void {
    $guard = new IgnoreOptedOutViewers;

    expect($guard->allows(signedInAttempt(optOutUser(false), viewer: optOutUser(true))))->toBeFalse()
        ->and($guard->allows(signedInAttempt(optOutUser(true), viewer: optOutUser(false))))->toBeTrue();
});
