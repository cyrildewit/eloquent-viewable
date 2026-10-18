<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;

it('names what the source cannot do and what cannot read from it', function (string $contract, string $message): void {
    $source = Mockery::mock(ViewSource::class);

    expect(UnsupportedBySource::missing($contract, $source)->getMessage())
        ->toStartWith('The view source ['.$source::class.'] cannot ')
        ->toContain($message)
        ->toEndWith("Implement `{$contract}` on it".(str_contains($message, 'scopes') ? ', or count through views() instead.' : '.'));
})->with([
    'count by' => [CountsBy::class, 'cannot count by a dimension, so countBy() cannot read from it.'],
    'trending' => [RanksTrending::class, 'cannot rank by trending, so trending() cannot read from it.'],
    'scopes' => [SubquerySource::class, 'so the withViewsCount(), orderByViews(), whereViewsCount() and whereViewedBy() scopes cannot read from it.'],
]);

it('says so for a contract no shipped read asks for', function (): void {
    $source = Mockery::mock(ViewSource::class);

    expect(UnsupportedBySource::missing(Countable::class, $source)->getMessage())
        ->toBe('The view source ['.$source::class.'] does not implement `Countable`, so what needs it cannot read from it.');
});
