<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Cooldowns\Cooldown;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('takes the viewable type and key, the visitor and the collection', function (): void {
    $cooldown = Cooldown::of(new Post(['id' => 1]), 'visitor', 'sidebar');

    expect($cooldown)
        ->viewableType->toBe(new Post()->getMorphClass())
        ->viewableId->toBe(1)
        ->visitorId->toBe('visitor')
        ->collection->toBe('sidebar');
});

it('keys the same cooldown the same way', function (): void {
    expect(Cooldown::of(new Post(['id' => 1]), 'visitor', 'sidebar')->key())
        ->toBe(Cooldown::of(new Post(['id' => 1]), 'visitor', 'sidebar')->key())
        ->toMatch('/^[0-9a-f]{32}$/');
});

it('keys an integer and a numeric string id the same way', function (): void {
    expect(Cooldown::of(new Post(['id' => 1]), 'visitor')->key())
        ->toBe(Cooldown::of(new Post(['id' => '1']), 'visitor')->key());
});

it('keys every part apart', function (Cooldown $other): void {
    expect(Cooldown::of(new Post(['id' => 1]), 'visitor', 'sidebar')->key())->not->toBe($other->key());
})->with([
    'another viewable' => fn (): Cooldown => Cooldown::of(new Post(['id' => 2]), 'visitor', 'sidebar'),
    'another visitor' => fn (): Cooldown => Cooldown::of(new Post(['id' => 1]), 'someone-else', 'sidebar'),
    'another collection' => fn (): Cooldown => Cooldown::of(new Post(['id' => 1]), 'visitor', 'footer'),
    'no collection' => fn (): Cooldown => Cooldown::of(new Post(['id' => 1]), 'visitor'),
]);

it('does not let one part run into the next', function (): void {
    expect(Cooldown::of(new Post(['id' => 1]), 'b:c', 'a')->key())
        ->not->toBe(Cooldown::of(new Post(['id' => 1]), 'c', 'a:b')->key());
});
