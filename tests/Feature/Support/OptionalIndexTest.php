<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Support\Indexes;
use CyrildeWit\EloquentViewable\Support\OptionalIndex;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A connection of its own, so adding and dropping indexes leaves the shared
 * test database alone on every driver.
 */
function indexConnection(): Connection
{
    config()->set('database.connections.indexes', ['driver' => 'sqlite', 'database' => ':memory:']);

    Schema::connection('indexes')->create('views', function (Blueprint $table): void {
        $table->id();
        $table->morphs('viewable');
        $table->string('visitor')->nullable();
        $table->timestamp('viewed_at');
    });

    return DB::connection('indexes');
}

it('reads a list of indexes', function (): void {
    expect(OptionalIndex::fromList('visitor, type-viewed-at'))->toBe([OptionalIndex::Visitor, OptionalIndex::TypeViewedAt])
        ->and(OptionalIndex::fromList('none'))->toBeEmpty()
        ->and(OptionalIndex::fromList(' '))->toBeEmpty();
});

it('rejects an index it does not know', function (): void {
    expect(fn (): array => OptionalIndex::fromList('visitor,unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown index [unknown]. Choose from: visitor, type-viewed-at, visitor-history, or none.');
});

it('writes a list of indexes', function (): void {
    expect(OptionalIndex::toList([OptionalIndex::Visitor, OptionalIndex::VisitorHistory]))->toBe('visitor,visitor-history');
});

it('names the columns of every index', function (OptionalIndex $index, array $columns): void {
    expect($index->columns())->toBe($columns);
})->with([
    [OptionalIndex::Visitor, ['viewable_type', 'viewable_id', 'viewed_at', 'visitor']],
    [OptionalIndex::TypeViewedAt, ['viewable_type', 'viewed_at']],
    [OptionalIndex::VisitorHistory, ['visitor', 'viewed_at', 'viewable_type', 'viewable_id']],
]);

it('adds and drops an index', function (OptionalIndex $index): void {
    $connection = indexConnection();
    $schema = $connection->getSchemaBuilder();

    $index->create($connection, 'views');

    expect(Indexes::of($schema, 'views')->cover($index->columns()))->toBeTrue()
        ->and(array_column($schema->getIndexes('views'), 'name'))->toContain($index->name());

    $index->drop($connection, 'views');

    expect(Indexes::of($schema, 'views')->cover($index->columns()))->toBeFalse();
})->with(OptionalIndex::cases());

it('adds the visitor as an included column on Postgres', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->allows('getDriverName')->andReturn('pgsql');
    $connection->expects('statement')
        ->with('create index views_viewable_viewed_at_visitor_index on views (viewable_type, viewable_id, viewed_at) include (visitor)')
        ->andReturnTrue();

    OptionalIndex::Visitor->create($connection, 'views');
});

it('writes the line that adds an index in a migration', function (): void {
    expect(OptionalIndex::TypeViewedAt->migration('views', 'mysql'))->toBe("\$table->index(['viewable_type', 'viewed_at']);")
        ->and(OptionalIndex::Visitor->migration('views', 'sqlite'))->toBe("\$table->index(['viewable_type', 'viewable_id', 'viewed_at', 'visitor']);")
        ->and(OptionalIndex::Visitor->migration('page_views', 'pgsql'))->toBe('create index on page_views (viewable_type, viewable_id, viewed_at) include (visitor)');
});
