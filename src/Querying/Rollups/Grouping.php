<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Contracts\Viewable;

/**
 * What a rollup row is counted per. Views add up across groupings, unique
 * visitors do not: a visitor who saw two collections is one unique, not two.
 */
enum Grouping: string
{
    case Viewable = 'viewable';

    case ViewableCollection = 'viewable_collection';

    case Type = 'type';

    case TypeCollection = 'type_collection';

    /**
     * The grouping that answers a count of the viewable, within one collection
     * or across them. A viewable without a key stands for its type.
     */
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
     * The columns of the views table a row of this grouping is grouped by.
     *
     * @return non-empty-list<string>
     */
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
