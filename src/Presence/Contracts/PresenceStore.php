<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Contracts;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;

/**
 * Keeps who was seen where, and for how long they count as active.
 */
interface PresenceStore
{
    public function touch(Sighting $sighting): void;

    public function leave(Sighting $sighting): void;

    /**
     * The visitors seen after the given moment, one count per scope in the
     * order the scopes were given.
     *
     * @param  list<Scope>  $scopes
     * @return list<int>
     */
    public function countVisitors(array $scopes, CarbonInterface $since): array;

    /**
     * The viewables seen after the given moment, of one type or of every
     * type, the most recently seen first.
     *
     * @return list<Reference>
     */
    public function active(?string $type, CarbonInterface $since, int $limit): array;

    /**
     * The signed-in viewers seen after the given moment, the most recently
     * seen first.
     *
     * @return list<Reference>
     */
    public function viewers(Scope $scope, CarbonInterface $since, int $limit): array;
}
