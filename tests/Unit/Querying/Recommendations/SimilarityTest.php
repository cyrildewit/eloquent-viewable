<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Recommendations\Similarity;

it('takes the shared visitors as they are when counting', function (): void {
    expect(Similarity::Count->between(3, 10, 1000))->toBe(3.0);
});

it('divides by the root of the product of both audiences for cosine', function (): void {
    expect(Similarity::Cosine->between(3, 9, 4))->toBe(0.5)
        ->and(Similarity::Cosine->between(3, 0, 0))->toBe(3.0);
});
