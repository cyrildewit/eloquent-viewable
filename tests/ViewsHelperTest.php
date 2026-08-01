<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Views;

it('accepts a fully qualified class name as viewable', function () {
    expect(views(Post::class))->toBeInstanceOf(Views::class);
});

it('accepts an empty model instance as viewable', function () {
    expect(views(new Post))->toBeInstanceOf(Views::class);
});
