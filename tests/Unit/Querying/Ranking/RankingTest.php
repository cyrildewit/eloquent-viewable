<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->post = new Post(['title' => 'First']);
    $this->apartment = new Apartment(['name' => 'Second']);

    $this->ranking = new Ranking(new Collection([
        new Entry($this->post, 5, 1),
        new Entry($this->apartment, 2, 2),
    ]));
});

it('iterates the entries in rank order', function (): void {
    expect(iterator_to_array($this->ranking))->toHaveCount(2)
        ->and(iterator_to_array($this->ranking)[0]->viewable)->toBe($this->post)
        ->and(iterator_to_array($this->ranking)[1]->rank)->toBe(2);
});

it('exposes the viewables as an Eloquent collection in rank order', function (): void {
    expect($this->ranking->viewables())->toBeInstanceOf(EloquentCollection::class)
        ->and($this->ranking->viewables()->all())->toBe([$this->post, $this->apartment]);
});

it('counts its entries', function (): void {
    expect($this->ranking)->toHaveCount(2)
        ->and($this->ranking->isEmpty())->toBeFalse()
        ->and(new Ranking(new Collection)->isEmpty())->toBeTrue()
        ->and(new Ranking(new Collection))->toBeEmpty();
});

it('serializes each viewable through its own toArray()', function (): void {
    $this->post->setHidden(['title']);

    expect($this->ranking->toArray())->toBe([
        ['rank' => 1, 'count' => 5, 'score' => null, 'viewable' => []],
        ['rank' => 2, 'count' => 2, 'score' => null, 'viewable' => ['name' => 'Second']],
    ])
        ->and(json_encode($this->ranking))->toBe(json_encode($this->ranking->toArray()));
});
