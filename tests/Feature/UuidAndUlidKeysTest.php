<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\UlidPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\UlidUser;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\UuidPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\UuidUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// The TestCase creates both views tables from the shipped stub.
dataset('key types', [
    'uuid' => ['uuid_views', UuidPost::class, UuidUser::class],
    'ulid' => ['ulid_views', UlidPost::class, UlidUser::class],
]);

/** @param  class-string<Model&Viewable>  $class */
function viewableWithKeyType(string $table, string $class): Model&Viewable
{
    config()->set('eloquent-viewable.models.view.table_name', $table);

    return $class::query()->create(['title' => 'A post']);
}

it('follows the morph key type the application set', function (string $table): void {
    expect(Schema::getColumnType($table, 'viewable_id'))->not->toBeIn(['integer', 'bigint'])
        ->and(Schema::getColumnType($table, 'viewer_id'))->not->toBeIn(['integer', 'bigint']);
})->with('key types');

it('records and counts views of a model', function (string $table, string $post): void {
    $viewable = viewableWithKeyType($table, $post);

    views($viewable)->record();
    views($viewable)->record();
    views($post::query()->create(['title' => 'Another post']))->record();

    expect(View::query()->where('viewable_id', $viewable->getKey())->count())->toBe(2)
        ->and(views($viewable)->count())->toBe(2)
        ->and(views($viewable)->unique()->count())->toBe(1)
        ->and(views($viewable)->remember()->count())->toBe(2)
        ->and(views($post)->count())->toBe(3);
})->with('key types');

it('orders, counts and filters models by their views', function (string $table, string $post): void {
    $one = viewableWithKeyType($table, $post);
    $two = $post::query()->create(['title' => 'Two']);
    $three = $post::query()->create(['title' => 'Three']);

    View::factory()->for($one, 'viewable')->count(3)->create();
    View::factory()->for($two, 'viewable')->create();

    expect($post::orderByViews()->pluck('id'))->toEqual(keysOf($one, $two, $three))
        ->and($post::withViewsCount()->findOrFail($one->getKey())->getAttribute('views_count'))->toEqual(3)
        ->and($post::whereViewsCount('>=', 1)->orderByViews()->pluck('id'))->toEqual(keysOf($one, $two));
})->with('key types');

it('ranks and counts models it already has', function (string $table, string $post): void {
    $one = viewableWithKeyType($table, $post);
    $two = $post::query()->create(['title' => 'Two']);
    $three = $post::query()->create(['title' => 'Three']);

    View::factory()->for($one, 'viewable')->create();
    View::factory()->for($two, 'viewable')->count(2)->create();

    expect(Views::top()->viewables()->modelKeys())->toBe([$two->getKey(), $one->getKey()])
        ->and(Views::forViewables([$one, $two, $three])->counts()->all())
        ->toBe([$one->getKey() => 1, $two->getKey() => 2, $three->getKey() => 0]);
})->with('key types');

it('ranks what the visitors of a model also viewed', function (string $table, string $post): void {
    config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);

    $one = viewableWithKeyType($table, $post);
    $two = $post::query()->create(['title' => 'Two']);

    View::factory()->for($one, 'viewable')->fromVisitor('reader')->create();
    View::factory()->for($two, 'viewable')->fromVisitor('reader')->create();

    expect(views($one)->alsoViewed()->viewables()->modelKeys())->toBe([$two->getKey()])
        ->and(views($two)->alsoViewed()->viewables()->modelKeys())->toBe([$one->getKey()]);
})->with('key types');

it('records and reads the viewer', function (string $table, string $post, string $user): void {
    $viewable = viewableWithKeyType($table, $post);
    $other = $post::query()->create(['title' => 'Another post']);
    $viewer = $user::query()->create(['name' => 'A viewer']);

    views($viewable)->viewedBy($viewer)->record();
    views($other)->record();

    expect($viewer->hasViewed($viewable))->toBeTrue()
        ->and($viewer->hasViewed($other))->toBeFalse()
        ->and(views($viewable)->viewedBy($viewer)->count())->toBe(1)
        ->and($post::whereViewedBy($viewer)->pluck('id'))->toEqual(keysOf($viewable));
})->with('key types');
