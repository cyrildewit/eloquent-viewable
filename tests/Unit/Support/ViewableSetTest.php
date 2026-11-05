<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Support\ViewableSet;

function setMember(int|string|null $key, string $type = 'posts'): Viewable
{
    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn($key);
    $viewable->allows('getMorphClass')->andReturn($type);

    return $viewable;
}

it('keeps the viewables in the order given, keyed by their key', function (): void {
    $three = setMember(3);
    $one = setMember(1);

    expect(ViewableSet::of([$three, $one])->all())->toBe([3 => $three, 1 => $one]);
});

it('keeps the first viewable of a key given twice', function (): void {
    $first = setMember(5);

    expect(ViewableSet::of([$first, setMember(5), setMember('5')])->all())->toBe([5 => $first]);
});

it('sorts integer keys as numbers', function (): void {
    expect(ViewableSet::of([setMember(10), setMember(9), setMember(100)])->keys())->toBe([9, 10, 100]);
});

it('sorts other keys as strings', function (): void {
    expect(ViewableSet::of([setMember('b'), setMember('a'), setMember(10), setMember(9)])->keys())->toBe([10, 9, 'a', 'b']);
});

it('stands for its type with the first viewable', function (): void {
    $first = setMember(2);

    expect(ViewableSet::of([$first, setMember(1)])->type())->toBe($first)
        ->and(ViewableSet::of([])->type())->toBeNull()
        ->and(ViewableSet::of([])->keys())->toBeEmpty();
});

it('accepts any iterable', function (): void {
    $generator = (function (): Generator {
        yield setMember(1);
        yield setMember(2);
    })();

    expect(ViewableSet::of($generator)->keys())->toBe([1, 2]);
});

it('refuses what is not a viewable', function (): void {
    expect(fn (): ViewableSet => ViewableSet::of([setMember(1), new stdClass]))
        ->toThrow(InvalidViewable::class, 'Class [stdClass] must implement '.Viewable::class.'.');
});

it('refuses a viewable without a key', function (): void {
    $unsaved = setMember(null);

    expect(fn (): ViewableSet => ViewableSet::of([setMember(1), $unsaved]))
        ->toThrow(InvalidViewable::class, 'Every viewable in a set needs a key, an unsaved ['.$unsaved::class.'] was given.');
});

it('refuses viewables of more than one type', function (): void {
    expect(fn (): ViewableSet => ViewableSet::of([setMember(1), setMember(2, 'videos')]))
        ->toThrow(InvalidViewable::class, 'Every viewable in a set must be of one type, [posts] and [videos] given.');
});
