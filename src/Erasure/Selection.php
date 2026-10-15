<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure;

use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * A selection matches the views of a subject: those it made as a viewer and
 * those carrying one of its visitor ids. It is never empty, because a
 * constraint without a condition would match every view.
 *
 * @internal
 */
final readonly class Selection
{
    /**
     * @param  list<string>  $visitors
     */
    private function __construct(
        public ?string $viewerType,
        public int|string|null $viewerKey,
        public array $visitors,
    ) {}

    public static function viewer(string $type, int|string $key, string $visitor): self
    {
        return new self($type, $key, [$visitor]);
    }

    public static function visitor(string $visitor): self
    {
        return new self(null, null, [$visitor]);
    }

    /** @param  list<string>  $visitors */
    public function withVisitors(array $visitors): self
    {
        return new self($this->viewerType, $this->viewerKey, array_values(array_unique([...$this->visitors, ...$visitors])));
    }

    /**
     * @template TView of View
     *
     * @param  Builder<TView>  $query
     * @return Builder<TView>
     */
    public function constrain(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            if ($this->viewerType !== null) {
                $query->orWhere(fn (Builder $query): Builder => $query
                    ->where('viewer_type', $this->viewerType)
                    ->where('viewer_id', $this->viewerKey));
            }

            $query->orWhereIn('visitor', $this->visitors);
        });
    }

    /** Keys are compared as strings, because a buffer hands back string keys. */
    public function matches(ViewRecord $record): bool
    {
        if (in_array($record->visitor, $this->visitors, true)) {
            return true;
        }

        if ($this->viewerType === null) {
            return false;
        }

        if ($record->viewerType !== $this->viewerType) {
            return false;
        }

        return (string) $record->viewerId === (string) $this->viewerKey;
    }
}
