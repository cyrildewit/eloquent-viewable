<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendation;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendations;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

function recommendedPost(int $id): Post
{
    $post = new Post;
    $post->id = $id;

    return $post;
}

it('lists the recommended models, their reasons and their scores', function (): void {
    $recommended = recommendedPost(2);
    $reason = recommendedPost(1);

    $recommendations = new Recommendations(new Collection([new Recommendation($recommended, 0.5, 1, new EloquentCollection([$reason]))]));

    expect($recommendations->count())->toBe(1)
        ->and($recommendations->isEmpty())->toBeFalse()
        ->and($recommendations->viewables()->all())->toBe([$recommended])
        ->and(iterator_to_array($recommendations)[0]->because->all())->toBe([$reason])
        ->and($recommendations->toArray())->toBe([['rank' => 1, 'score' => 0.5, 'viewable' => ['id' => 2], 'because' => [['id' => 1]]]])
        ->and($recommendations->jsonSerialize())->toBe($recommendations->toArray())
        ->and(new Recommendations(new Collection)->isEmpty())->toBeTrue();
});
