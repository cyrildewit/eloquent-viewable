<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Erasure\Selection;

function selectionRecord(?string $visitor, ?string $viewerType = null, int|string|null $viewerId = null): ViewRecord
{
    return new ViewRecord(
        viewableId: 1,
        viewableType: 'post',
        visitor: $visitor,
        collection: null,
        viewedAt: Carbon::parse('2026-03-01 10:00:00'),
        viewerType: $viewerType,
        viewerId: $viewerId,
    );
}

it('matches a record of the viewer, comparing keys as strings', function (): void {
    $selection = Selection::viewer('user', 42, 'hash');

    expect($selection->matches(selectionRecord('cookie', 'user', '42')))->toBeTrue()
        ->and($selection->matches(selectionRecord('cookie', 'user', 43)))->toBeFalse()
        ->and($selection->matches(selectionRecord('cookie', 'admin', 42)))->toBeFalse()
        ->and($selection->matches(selectionRecord('hash')))->toBeTrue()
        ->and($selection->matches(selectionRecord(null)))->toBeFalse();
});

it('matches a record of the visitor and of the visitors it was given', function (): void {
    $selection = Selection::visitor('cookie-1');

    expect($selection->matches(selectionRecord('cookie-1')))->toBeTrue()
        ->and($selection->matches(selectionRecord('cookie-2')))->toBeFalse()
        ->and($selection->matches(selectionRecord(null, 'user', 42)))->toBeFalse()
        ->and($selection->withVisitors(['cookie-2', 'cookie-1'])->visitors)->toBe(['cookie-1', 'cookie-2'])
        ->and($selection->withVisitors(['cookie-2'])->matches(selectionRecord('cookie-2')))->toBeTrue();
});
