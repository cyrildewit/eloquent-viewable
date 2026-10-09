<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

/**
 * A grouping is what a rollup row is counted per. Views add up across
 * groupings, unique visitors do not: a visitor who saw two collections is one
 * unique, not two.
 */
enum Grouping: string
{
    case Viewable = 'viewable';

    case ViewableCollection = 'viewable_collection';

    case Type = 'type';

    case TypeCollection = 'type_collection';

    public static function for(?Viewable $viewable, bool $perCollection): self
    {
        $type = $viewable instanceof Viewable && $viewable->getKey() === null;

        return match (true) {
            $type && $perCollection => self::TypeCollection,
            $type => self::Type,
            $perCollection => self::ViewableCollection,
            default => self::Viewable,
        };
    }

    /**
     * This is the value of the `grouping` column. The rows per dimension value
     * of a custom rollup are kept apart from its totals, because unique
     * visitors summed across values would count a visitor once per value.
     */
    public function stored(bool $perDimension = false): string
    {
        if (! $perDimension) {
            return $this->value;
        }

        return "{$this->value}:dimension";
    }

    /** @return non-empty-list<string> */
    public function columns(): array
    {
        return match ($this) {
            self::Viewable => ['viewable_type', 'viewable_id'],
            self::ViewableCollection => ['viewable_type', 'viewable_id', 'collection'],
            self::Type => ['viewable_type'],
            self::TypeCollection => ['viewable_type', 'collection'],
        };
    }
}
