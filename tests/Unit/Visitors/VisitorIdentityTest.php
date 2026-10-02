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
use Illuminate\Encryption\Encrypter;

const IDENTITY_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function identity(string $identity = 'cookie', string $appKey = IDENTITY_KEY): VisitorIdentity
{
    return new VisitorIdentity(
        new Config(new Repository(['eloquent-viewable' => ['visitor' => ['identity' => $identity]]])),
        new Encrypter($appKey, 'AES-256-CBC'),
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
        ->toBe(hash_hmac('sha256', Post::class.'|7', IDENTITY_KEY));
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
    expect(identity('viewer', str_repeat('b', 32))->ofViewer(new Post(['id' => 7])))
        ->not->toBe(identity('viewer')->ofViewer(new Post(['id' => 7])));
});

it('refuses a viewer without a usable key', function (): void {
    expect(fn (): string => identity('viewer')->ofViewer(new Post))
        ->toThrow(InvalidViewer::class, 'The key of the viewer ['.Post::class.'] must be an integer or a string, null given.');
});

it('refuses an unknown identity', function (): void {
    expect(fn (): string => identity('session')->of(cookieVisitor(), new Post(['id' => 7])))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.visitor.identity` config value must be one of `cookie`, `viewer`, `"session"` given.');
});
