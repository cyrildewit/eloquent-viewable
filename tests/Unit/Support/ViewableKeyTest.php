<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;

function viewableWithKey(mixed $key): Viewable
{
    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn($key);

    return $viewable;
}

it('returns an integer, string or missing key as it is', function (int|string|null $key): void {
    expect(ViewableKey::of(viewableWithKey($key)))->toBe($key);
})->with([
    'integer' => [7],
    'string' => ['01J9Z3'],
    'missing' => [null],
]);

it('rejects a key that is not an integer, string or null', function (): void {
    $viewable = viewableWithKey(new stdClass);

    expect(fn (): int|string|null => ViewableKey::of($viewable))
        ->toThrow(InvalidViewable::class, 'The key of ['.$viewable::class.'] must be an integer, a string or null, stdClass given.');
});
