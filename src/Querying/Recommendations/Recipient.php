<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use Illuminate\Database\Eloquent\Model;

/**
 * A recipient is the one recommendations are made for: a viewer, whose
 * history is the views it made, or a visitor id, whose history is the views
 * carrying it.
 */
final readonly class Recipient
{
    private function __construct(
        public ?Model $viewer,
        public int|string|null $viewerKey,
        public ?string $visitor,
    ) {}

    /** @throws InvalidViewer */
    public static function viewer(Model $viewer): self
    {
        return new self($viewer, ViewerKey::of($viewer), null);
    }

    public static function visitor(string $visitor): self
    {
        return new self(null, null, $visitor);
    }

    /**
     * A recipient holds either a viewer with its key or a visitor id, never
     * both.
     *
     * @phpstan-assert-if-true !null $this->viewer
     * @phpstan-assert-if-true !null $this->viewerKey
     *
     * @phpstan-assert-if-false !null $this->visitor
     */
    public function isViewer(): bool
    {
        return $this->viewer instanceof Model;
    }

    /** The identity keeps the remembered recommendations of two recipients apart. */
    public function identity(): string
    {
        if ($this->isViewer()) {
            return "viewer:{$this->viewer->getMorphClass()}:{$this->viewerKey}";
        }

        return "visitor:{$this->visitor}";
    }
}
