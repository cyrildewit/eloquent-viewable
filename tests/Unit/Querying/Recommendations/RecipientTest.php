<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;

it('holds a viewer with its key', function (): void {
    $user = new User;
    $user->id = 7;

    $recipient = Recipient::viewer($user);

    expect($recipient->isViewer())->toBeTrue()
        ->and($recipient->viewerKey)->toBe(7)
        ->and($recipient->visitor)->toBeNull()
        ->and($recipient->identity())->toBe('viewer:'.User::class.':7');
});

it('holds a visitor id', function (): void {
    $recipient = Recipient::visitor('abc');

    expect($recipient->isViewer())->toBeFalse()
        ->and($recipient->viewer)->toBeNull()
        ->and($recipient->identity())->toBe('visitor:abc');
});

it('refuses a viewer without a key', function (): void {
    Recipient::viewer(new User);
})->throws(InvalidViewer::class);

it('names everything that changes the pairs in the identity of a request', function (): void {
    $request = new RecommendationRequest(Recipient::visitor('abc'), new Post, 20, 3, 500);

    expect($request->identity())->toBe('visitor:abc:'.Post::class.':20:3:500:unseen')
        ->and(new RecommendationRequest(Recipient::visitor('abc'), null, 5, 1, null, includeSeen: true)->identity())->toBe('visitor:abc::5:1:all:seen');
});
