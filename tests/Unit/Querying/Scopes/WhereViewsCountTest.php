<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidOperator;
use CyrildeWit\EloquentViewable\Querying\Scopes\WhereViewsCount;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

it('accepts the comparison operators', function (string $operator): void {
    expect(new WhereViewsCount(Mockery::mock(SubquerySource::class), new ViewsQuery, $operator, 10))
        ->toBeInstanceOf(WhereViewsCount::class);
})->with(['=', '!=', '<>', '<', '<=', '>', '>=']);

it('refuses an operator that does not compare a count', function (string $operator): void {
    expect(fn (): WhereViewsCount => new WhereViewsCount(Mockery::mock(SubquerySource::class), new ViewsQuery, $operator, 10))
        ->toThrow(InvalidOperator::class, "whereViewsCount() compares a count, so it takes one of =, !=, <>, <, <=, >, >=, '{$operator}' given.");
})->with(['=>', 'like', '&', '']);
