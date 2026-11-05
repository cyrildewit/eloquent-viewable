<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('returns an integer key', function (): void {
    expect(ViewerKey::of(new Post(['id' => 7])))->toBe(7);
});

it('returns a string key', function (): void {
    $viewer = new class extends Apartment
    {
        protected $keyType = 'string';

        public $incrementing = false;
    };
    $viewer->setAttribute('id', 'uuid-three');

    expect(ViewerKey::of($viewer))->toBe('uuid-three');
});

it('refuses a model without a key', function (): void {
    expect(fn (): int|string => ViewerKey::of(new Post))
        ->toThrow(InvalidViewer::class, 'The key of the viewer ['.Post::class.'] must be an integer or a string, null given.');
});
