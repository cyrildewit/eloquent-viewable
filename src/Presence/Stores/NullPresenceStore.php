<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;

final readonly class NullPresenceStore implements PresenceStore
{
    public function touch(Sighting $sighting): void {}

    public function leave(Sighting $sighting): void {}

    /**
     * @param  list<Scope>  $scopes
     * @return list<int>
     */
    public function countVisitors(array $scopes, CarbonInterface $since): array
    {
        return array_fill(0, count($scopes), 0);
    }

    /** @return list<Reference> */
    public function active(?string $type, CarbonInterface $since, int $limit): array
    {
        return [];
    }

    /** @return list<Reference> */
    public function viewers(Scope $scope, CarbonInterface $since, int $limit): array
    {
        return [];
    }
}
