<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('builds the middleware string', function (string $expected, array $arguments): void {
    expect(RecordViews::using(...$arguments))->toBe($expected);
})->with([
    'nothing' => ['views', []],
    'a parameter' => ['views:post', ['post']],
    'a class' => ['views:'.Post::class, [Post::class]],
    'several' => ['views:post,author', [['post', 'author']]],
    'options' => ['views:post,collection=amp,cooldown=30,queue=true', ['post', 'amp', 30, true]],
    'options only' => ['views:queue=false', ['queue' => false]],
]);
