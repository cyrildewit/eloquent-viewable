<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Stores;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;

/**
 * Keeps presence in memory for as long as the process lives, for tests and
 * for `Views::fake()`. Nothing expires; a read only counts what was seen
 * after the moment it asks for.
 */
final class ArrayPresenceStore implements PresenceStore
{
    /** @var array<string, array<string, int>> */
    private array $visitors = [];

    /** @var array<string, array<string, int>> */
    private array $active = [];

    /** @var array<string, array<string, int>> */
    private array $viewers = [];

    public function touch(Sighting $sighting): void
    {
        $seenAt = $sighting->seenAt->getTimestamp();

        foreach ($sighting->scopes() as $scope) {
            $this->visitors[$scope->id()][$sighting->visitor] = $seenAt;

            if ($sighting->viewer instanceof Reference) {
                $this->viewers[$scope->id()][$sighting->viewer->encode()] = $seenAt;
            }
        }

        foreach ([new Scope, new Scope($sighting->type)] as $scope) {
            $this->active[$scope->id()][$sighting->viewable()->encode()] = $seenAt;
        }
    }

    public function leave(Sighting $sighting): void
    {
        foreach ($sighting->viewableScopes() as $scope) {
            unset($this->visitors[$scope->id()][$sighting->visitor]);

            if ($sighting->viewer instanceof Reference) {
                unset($this->viewers[$scope->id()][$sighting->viewer->encode()]);
            }
        }
    }

    /**
     * @param  list<Scope>  $scopes
     * @return list<int>
     */
    public function countVisitors(array $scopes, CarbonInterface $since): array
    {
        return array_map(
            fn (Scope $scope): int => count($this->since($this->visitors[$scope->id()] ?? [], $since)),
            $scopes,
        );
    }

    /** @return list<Reference> */
    public function active(?string $type, CarbonInterface $since, int $limit): array
    {
        return $this->recent($this->active[new Scope($type)->id()] ?? [], $since, $limit);
    }

    /** @return list<Reference> */
    public function viewers(Scope $scope, CarbonInterface $since, int $limit): array
    {
        return $this->recent($this->viewers[$scope->id()] ?? [], $since, $limit);
    }

    /**
     * @param  array<string, int>  $members
     * @return array<string, int>
     */
    private function since(array $members, CarbonInterface $since): array
    {
        $cutoff = $since->getTimestamp();

        return array_filter($members, static fn (int $seenAt): bool => $seenAt > $cutoff);
    }

    /**
     * @param  array<string, int>  $members
     * @return list<Reference>
     */
    private function recent(array $members, CarbonInterface $since, int $limit): array
    {
        $members = $this->since($members, $since);

        arsort($members);

        return array_map(
            Reference::decode(...),
            array_slice(array_keys($members), 0, $limit),
        );
    }
}
