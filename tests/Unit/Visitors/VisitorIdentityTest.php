<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Config\Repository;

function identity(string $identity = 'cookie', mixed $appKey = 'base64:secret'): VisitorIdentity
{
    return new VisitorIdentity(
        new Config(new Repository(['eloquent-viewable' => ['visitor' => ['identity' => $identity]]])),
        new Repository(['app' => ['key' => $appKey]]),
    );
}

function cookieVisitor(): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn('cookie-id');

    return $visitor;
}

it('uses the cookie id by default', function (): void {
    $viewer = new Post(['id' => 7]);

    expect(identity()->of(cookieVisitor(), $viewer))->toBe('cookie-id')
        ->and(identity()->of(cookieVisitor(), null))->toBe('cookie-id');
});

it('derives the id from the viewer when configured', function (): void {
    $visitor = Mockery::mock(Visitor::class);
    $visitor->shouldNotReceive('id');

    expect(identity('viewer')->of($visitor, new Post(['id' => 7])))
        ->toBe(hash_hmac('sha256', Post::class.'|7', 'base64:secret'));
});

it('falls back to the cookie id for a guest', function (): void {
    expect(identity('viewer')->of(cookieVisitor(), null))->toBe('cookie-id');
});

it('tells viewers apart by type and key and is stable for the same one', function (): void {
    $identity = identity('viewer');

    expect($identity->ofViewer(new Post(['id' => 7])))->toBe($identity->ofViewer(new Post(['id' => 7])))
        ->and($identity->ofViewer(new Post(['id' => 7])))->not->toBe($identity->ofViewer(new Post(['id' => 8])))
        ->and($identity->ofViewer(new Post(['id' => 7])))->not->toBe($identity->ofViewer(new Apartment(['id' => 7])))
        ->and($identity->ofViewer(new Post(['id' => 7])))->toHaveLength(64);
});

it('changes with the application key', function (): void {
    expect(identity('viewer', 'base64:one')->ofViewer(new Post(['id' => 7])))
        ->not->toBe(identity('viewer', 'base64:two')->ofViewer(new Post(['id' => 7])));
});

it('refuses to derive an id without an application key', function (mixed $appKey): void {
    expect(fn (): string => identity('viewer', $appKey)->ofViewer(new Post(['id' => 7])))
        ->toThrow(InvalidConfiguration::class, 'The `app.key` config value must be set to derive visitor ids from viewers.');
})->with([
    'null' => [null],
    'empty' => [''],
    'integer' => [1],
]);

it('refuses a viewer without a usable key', function (): void {
    expect(fn (): string => identity('viewer')->ofViewer(new Post))
        ->toThrow(InvalidViewer::class, 'The key of the viewer ['.Post::class.'] must be an integer or a string, null given.');
});

it('refuses an unknown identity', function (): void {
    expect(fn (): string => identity('session')->of(cookieVisitor(), new Post(['id' => 7])))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.visitor.identity` config value must be one of `cookie`, `viewer`, `"session"` given.');
});
