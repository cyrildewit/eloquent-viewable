<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure;

use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use Illuminate\Database\Eloquent\Model;

/**
 * The person whose view history is forgotten, anonymised or exported: a
 * viewer, or a visitor id for a guest. A viewer is held by its morph type and
 * key, so one that was already deleted can be named as well.
 */
final readonly class Subject
{
    private function __construct(
        public ?string $viewerType,
        public int|string|null $viewerKey,
        public ?string $visitor,
    ) {}

    /** @throws InvalidViewer */
    public static function viewer(Model $viewer): self
    {
        return new self($viewer->getMorphClass(), ViewerKey::of($viewer), null);
    }

    public static function viewerKey(string $type, int|string $key): self
    {
        return new self($type, $key, null);
    }

    public static function visitor(string $visitor): self
    {
        return new self(null, null, $visitor);
    }
}
