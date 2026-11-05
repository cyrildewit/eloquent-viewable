<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('picks the grouping that answers a count', function (?bool $saved, bool $perCollection, Grouping $grouping): void {
    $viewable = match ($saved) {
        null => null,
        true => new Post()->forceFill(['id' => 7]),
        false => new Post,
    };

    expect(Grouping::for($viewable, $perCollection))->toBe($grouping);
})->with([
    'a model' => [true, false, Grouping::Viewable],
    'a model in a collection' => [true, true, Grouping::ViewableCollection],
    'a type' => [false, false, Grouping::Type],
    'a type in a collection' => [false, true, Grouping::TypeCollection],
    'any model' => [null, false, Grouping::Viewable],
    'any model in a collection' => [null, true, Grouping::ViewableCollection],
]);

it('groups by the columns of what it counts', function (): void {
    expect(Grouping::Viewable->columns())->toBe(['viewable_type', 'viewable_id'])
        ->and(Grouping::ViewableCollection->columns())->toBe(['viewable_type', 'viewable_id', 'collection'])
        ->and(Grouping::Type->columns())->toBe(['viewable_type'])
        ->and(Grouping::TypeCollection->columns())->toBe(['viewable_type', 'collection']);
});
