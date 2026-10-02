<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Views;

it('accepts a fully qualified class name as viewable', function (): void {
    expect(views(Post::class))->toBeInstanceOf(Views::class);
});

it('accepts an empty model instance as viewable', function (): void {
    expect(views(new Post))->toBeInstanceOf(Views::class);
});

it('rejects a class name that is not viewable', function (): void {
    expect(fn (): Views => views(stdClass::class))
        ->toThrow(InvalidViewable::class, 'Class [stdClass] must implement '.Viewable::class.'.');
});

it('throws an InvalidArgumentException for a class name that is not viewable', function (): void {
    expect(fn (): Views => views(stdClass::class))->toThrow(InvalidArgumentException::class);
});
