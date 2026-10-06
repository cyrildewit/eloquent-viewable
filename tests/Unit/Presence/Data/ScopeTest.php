<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('reads the site, a type or a viewable from what it is given', function (): void {
    expect(Scope::of(null, 'amp'))
        ->type->toBeNull()
        ->key->toBeNull()
        ->collection->toBe('amp')
        ->and(Scope::of(new Post))
        ->type->toBe(Post::class)
        ->key->toBeNull()
        ->and(Scope::of(new Post(['id' => 7])))
        ->type->toBe(Post::class)
        ->key->toBe(7)
        ->collection->toBeNull();
});

it('gives every scope an id of its own', function (): void {
    expect(new Scope()->id())->toBe('*|*|*')
        ->and(new Scope('post', 7, 'amp')->id())->toBe('post|7|amp')
        ->and(new Scope('a|b')->id())->not->toBe(new Scope('a', 'b')->id())
        ->and(new Scope('post', collection: '*')->id())->not->toBe(new Scope('post')->id());
});
