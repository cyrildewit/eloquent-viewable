<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Storage;

it('keeps a value in a column named after the dimension', function (): void {
    $storage = Storage::column();

    expect($storage->isColumn())->toBeTrue()
        ->and($storage->isValid())->toBeTrue()
        ->and($storage->target('source'))->toBe('source')
        ->and($storage->keys())->toBeEmpty();
});

it('keeps a value at a path into context', function (): void {
    $storage = Storage::json('context->billing->plan');

    expect($storage->isColumn())->toBeFalse()
        ->and($storage->isValid())->toBeTrue()
        ->and($storage->target('plan'))->toBe('context->billing->plan')
        ->and($storage->keys())->toBe(['billing', 'plan']);
});

it('only accepts a path into context', function (string $path): void {
    expect(Storage::json($path)->isValid())->toBeFalse();
})->with([
    'another column' => ['visitor->id'],
    'context itself' => ['context'],
    'an empty key' => ['context->'],
    'an injected key' => ["context->plan') or 1=1 --"],
]);
