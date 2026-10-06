<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Presence\Data\Reference;

it('encodes and decodes a model by its type and key', function (string $type, int|string $id): void {
    $decoded = Reference::decode(new Reference($type, $id)->encode());

    expect($decoded->type)->toBe($type)
        ->and($decoded->id)->toBe((string) $id);
})->with([
    'morph alias' => ['post', 7],
    'class name' => ['App\Models\Post', 'uuid-1'],
    'separator in both parts' => ['a|b', 'c|d'],
]);

it('reads a member without a separator as a type without a key', function (): void {
    expect(Reference::decode('post'))
        ->type->toBe('post')
        ->id->toBe('');
});
