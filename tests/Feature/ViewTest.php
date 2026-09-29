<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Support\Facades\Config;

it('reads the connection name from the config', function (): void {
    Config::set('database.connections.analytics', ['driver' => 'sqlite', 'database' => ':memory:']);
    Config::set('eloquent-viewable.models.view.connection', 'analytics');

    expect(new View()->getConnectionName())->toBe('analytics');
});

it('reads the table name from the config', function (): void {
    Config::set('eloquent-viewable.models.view.table_name', 'page_views');

    expect(new View()->getTable())->toBe('page_views');
});

it('can belong to viewable model', function (): void {
    $post = Post::factory()->create();

    View::create([
        'viewable_id' => $post->getKey(),
        'viewable_type' => $post->getMorphClass(),
    ]);

    expect(View::first()->viewable)->toBeInstanceOf(Post::class);
});

it('can scope to within period with only start date time', function (): void {
    expect(View::withinPeriod(Period::since('2019-06-12'))->toSql())
        ->toBe('select * from "views" where "viewed_at" >= ?');
});

it('can scope to within period with only end date time', function (): void {
    expect(View::withinPeriod(Period::upto('2019-03-23'))->toSql())
        ->toBe('select * from "views" where "viewed_at" <= ?');
});

it('can scope to within period with both start and end date time', function (): void {
    expect(View::withinPeriod(Period::create('2019-02-15', '2019-06-12'))->toSql())
        ->toBe('select * from "views" where "viewed_at" between ? and ?');
});

it('can scope to collection null', function (): void {
    expect(View::collection(null)->toSql())
        ->toBe('select * from "views" where "collection" is null');
});

it('can scope to collection custom', function (): void {
    expect(View::collection('custom')->toSql())
        ->toBe('select * from "views" where "collection" = ?');
});
