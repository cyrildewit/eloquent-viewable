<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\ViewableLoader;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\KeepsViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/** @return array{type: string, id: int|string, count: int} */
function rowFor(Model $model, int $count, ?string $type = null): array
{
    return ['type' => $type ?? $model->getMorphClass(), 'id' => $model->getKey(), 'count' => $count];
}

beforeEach(function (): void {
    $this->loader = new ViewableLoader;
});

it('loads the models of every type in the order of the rows, one query per type', function (): void {
    $first = Post::factory()->create();
    $second = Apartment::factory()->create();
    $third = Post::factory()->create();

    DB::enableQueryLog();

    $ranking = $this->loader->load([rowFor($first, 9), rowFor($second, 4), rowFor($third, 1)]);

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($ranking->entries->map(fn (Entry $entry): array => [$entry->viewable::class, $entry->viewable->getKey(), $entry->count, $entry->rank])->all())->toBe([
            [Post::class, $first->getKey(), 9, 1],
            [Apartment::class, $second->getKey(), 4, 2],
            [Post::class, $third->getKey(), 1, 3],
        ])
        ->and($ranking->viewables()->first())->toBeInstanceOf(Post::class)
        ->and($ranking->viewables()->first()->is($first))->toBeTrue();
});

it('returns an empty ranking for no rows', function (): void {
    expect($this->loader->load([])->isEmpty())->toBeTrue();
});

it('resolves a morph map alias', function (): void {
    Relation::morphMap(['post' => Post::class]);

    try {
        $post = Post::factory()->create();

        $ranking = $this->loader->load([rowFor($post, 3, 'post')]);

        expect($ranking)->toHaveCount(1)
            ->and($ranking->viewables()->first()->is($post))->toBeTrue();
    } finally {
        Relation::morphMap([], false);
    }
});

it('skips a type that names no viewable model', function (string $type): void {
    $post = Post::factory()->create();

    $ranking = $this->loader->load([
        ['type' => $type, 'id' => 1, 'count' => 10],
        rowFor($post, 3),
    ]);

    expect($ranking->entries->map(fn (Entry $entry): array => [$entry->viewable->getKey(), $entry->rank])->all())->toBe([[$post->getKey(), 1]]);
})->with([
    'an unknown class' => ['App\Models\Gone'],
    'a class that is not a model' => [ViewableLoader::class],
    'a model that is not viewable' => [User::class],
]);

it('drops a row whose model is gone and renumbers the ranks', function (): void {
    $gone = KeepsViewsPost::create(['title' => 'Title', 'body' => 'Body']);
    $kept = Post::factory()->create();
    $rows = [rowFor($gone, 10), rowFor($kept, 3)];
    $gone->delete();

    $ranking = $this->loader->load($rows);

    expect($ranking->entries->map(fn (Entry $entry): array => [$entry->viewable->getKey(), $entry->rank])->all())->toBe([[$kept->getKey(), 1]]);
});

it('drops a soft-deleted model, as its default query hides it', function (): void {
    $trashed = SoftDeletablePost::create(['title' => 'Title', 'body' => 'Body']);
    $kept = SoftDeletablePost::create(['title' => 'Title', 'body' => 'Body']);
    $trashed->delete();

    $ranking = $this->loader->load([rowFor($trashed, 10), rowFor($kept, 3)]);

    expect($ranking->viewables()->modelKeys())->toBe([$kept->getKey()]);
});

it('matches a key stored as a string to an integer key', function (): void {
    $post = Post::factory()->create();

    $ranking = $this->loader->load([['type' => $post->getMorphClass(), 'id' => (string) $post->getKey(), 'count' => 1]]);

    expect($ranking->viewables()->first()->is($post))->toBeTrue();
});
