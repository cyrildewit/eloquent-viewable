<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;

/** @param  list<Scope>  $scopes */
function sightingScopeIds(array $scopes): array
{
    return array_map(static fn (Scope $scope): string => $scope->id(), $scopes);
}

it('counts the visitor on the site, the type and the viewable', function (): void {
    $sighting = new Sighting('post', 7, 'visitor', Carbon::now());

    expect(sightingScopeIds($sighting->scopes()))->toBe(['*|*|*', 'post|*|*', 'post|7|*'])
        ->and(sightingScopeIds($sighting->viewableScopes()))->toBe(['post|7|*'])
        ->and($sighting->viewable()->encode())->toBe('post|7');
});

it('also counts the visitor within their collection', function (): void {
    $sighting = new Sighting('post', 7, 'visitor', Carbon::now(), 'amp');

    expect(sightingScopeIds($sighting->scopes()))->toBe(['*|*|*', 'post|*|*', 'post|7|*', '*|*|amp', 'post|*|amp', 'post|7|amp'])
        ->and(sightingScopeIds($sighting->viewableScopes()))->toBe(['post|7|*', 'post|7|amp']);
});
