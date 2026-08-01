<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Container\Container;

it('can have a custom connection through config file', function () {
    Container::getInstance()->make('config')->get(['eloquent-viewable.models.view.connection', 'testing']);

    expect((new View)->getConnection()->getName())->toBe('testing');
});

it('can fill visitor', function () {
    $view = new View([
        'visitor' => 'uniqueString',
    ]);

    expect($view->getAttribute('visitor'))->toBe('uniqueString');
});

it('can fill visitor with null', function () {
    $view = new View([
        'visitor' => null,
    ]);

    expect($view->getAttribute('visitor'))->toBeNull();
});

it('can fill collection', function () {
    $view = new View([
        'collection' => null,
    ]);

    expect($view->getAttribute('collection'))->toBeNull();
});

it('can fill viewed at', function () {
    Carbon::setTestNow($now = Carbon::create(2018, 1, 12));

    $view = new View([
        'viewed_at' => $now,
    ]);

    expect($view->viewed_at->format('Y-m-d'))->toBe('2018-01-12');
});

it('can belong to viewable model', function () {
    $post = Post::factory()->create();

    View::create([
        'viewable_id' => $post->getKey(),
        'viewable_type' => $post->getMorphClass(),
    ]);

    expect(View::first()->viewable)->toBeInstanceOf(Post::class);
});

it('can scope to within period with only start date time', function () {
    Post::factory()->create();

    expect(View::withinPeriod(Period::since('2019-06-12'))->toSql())
        ->toBe('select * from "views" where "viewed_at" >= ?');
});

it('can scope to within period with only end date time', function () {
    Post::factory()->create();

    expect(View::withinPeriod(Period::upto('2019-03-23'))->toSql())
        ->toBe('select * from "views" where "viewed_at" <= ?');
});

it('can scope to within period with both start and end date time', function () {
    Post::factory()->create();

    expect(View::withinPeriod(Period::create('2019-02-15', '2019-06-12'))->toSql())
        ->toBe('select * from "views" where "viewed_at" between ? and ?');
});

it('can scope to collection null', function () {
    Post::factory()->create();

    expect(View::collection(null)->toSql())
        ->toBe('select * from "views" where "collection" is null');
});

it('can scope to collection custom', function () {
    Post::factory()->create();

    expect(View::collection('custom')->toSql())
        ->toBe('select * from "views" where "collection" = ?');
});
