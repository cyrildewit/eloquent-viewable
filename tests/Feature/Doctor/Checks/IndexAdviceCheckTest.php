<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\IndexAdviceCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Support\OptionalIndex;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** @return list<Finding> */
function indexAdvice(): array
{
    return iterator_to_array(app()->make(IndexAdviceCheck::class)->run(), preserve_keys: false);
}

/** @return list<array{Status, string}> */
function indexAdviceSummaries(): array
{
    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], indexAdvice());
}

/**
 * A views table on a connection of its own, so its indexes and its keys can
 * be set up without touching the shared test database.
 *
 * @param  list<OptionalIndex>  $indexes
 */
function viewsTableWith(array $indexes = [], int $rows = 0): void
{
    config()->set('database.connections.doctor', ['driver' => 'sqlite', 'database' => ':memory:']);
    config()->set('eloquent-viewable.models.view.connection', 'doctor');

    Schema::connection('doctor')->create('views', function (Blueprint $table): void {
        $table->id();
        $table->morphs('viewable');
        $table->string('visitor')->nullable();
        $table->timestamp('viewed_at');
    });

    foreach ($indexes as $index) {
        $index->create(DB::connection('doctor'), 'views');
    }

    if ($rows > 0) {
        DB::connection('doctor')->table('views')->insert([
            'id' => $rows,
            'viewable_type' => 'post',
            'viewable_id' => 1,
            'viewed_at' => now(),
        ]);
    }
}

function useUniqueCounter(): void
{
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_unique_views' => ['unique' => true]]]);
}

it('recommends nothing for a small table and a config that relies on no index', function (): void {
    viewsTableWith();

    expect(indexAdviceSummaries())->toBe([
        [Status::Pass, 'No other index is needed yet: nothing in the config relies on one, and the views table is small.'],
    ]);
});

it('recommends the visitor index for a unique counter', function (): void {
    viewsTableWith();
    useUniqueCounter();

    expect(indexAdviceSummaries())->toBe([
        [Status::Advice, 'Add an index on `(viewable_type, viewable_id, viewed_at, visitor)`: `querying.counters` keeps a unique count, and it speeds up `unique()` counts.'],
    ]);
});

it('ignores a counter that is not unique', function (): void {
    viewsTableWith();

    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    expect(indexAdviceSummaries())->toBe([
        [Status::Pass, 'No other index is needed yet: nothing in the config relies on one, and the views table is small.'],
    ]);
});

it('warns about an index the config relies on once the table is large', function (): void {
    viewsTableWith(rows: IndexAdviceCheck::LargeTable);
    useUniqueCounter();

    expect(indexAdviceSummaries()[0][0])->toBe(Status::Warning);
});

it('suggests every index for a large table', function (): void {
    viewsTableWith(rows: 1_200_000);

    expect(indexAdviceSummaries())->toBe([
        [Status::Advice, 'With about 1,200,000 views, an index on `(viewable_type, viewable_id, viewed_at, visitor)` speeds up `unique()` counts.'],
        [Status::Advice, 'With about 1,200,000 views, an index on `(viewable_type, viewed_at)` speeds up counts over a whole model type, such as `views(Post::class)->count()` and `orderByTrending()`.'],
        [Status::Advice, 'With about 1,200,000 views, an index on `(visitor, viewed_at, viewable_type, viewable_id)` speeds up `alsoViewed()`.'],
    ]);
});

it('passes the indexes that are in place', function (): void {
    viewsTableWith(OptionalIndex::cases(), rows: 1_200_000);

    expect(indexAdviceSummaries())->toBe([
        [Status::Pass, 'The `(viewable_type, viewable_id, viewed_at, visitor)` index is in place.'],
        [Status::Pass, 'The `(viewable_type, viewed_at)` index is in place.'],
        [Status::Pass, 'The `(visitor, viewed_at, viewable_type, viewable_id)` index is in place.'],
    ]);
});

it('says how to add an index in a migration', function (): void {
    viewsTableWith();
    useUniqueCounter();

    expect(indexAdvice()[0]->fix)->toBe("Add it in a migration of your own: `\$table->index(['viewable_type', 'viewable_id', 'viewed_at', 'visitor']);`.");
});

it('skips a views table that does not exist', function (): void {
    config()->set('eloquent-viewable.models.view.table_name', 'missing_views');

    expect(indexAdviceSummaries())->toBe([
        [Status::Skipped, 'The `missing_views` table does not exist yet.'],
    ]);
});

it('reads the indexes the migrations create on every driver', function (): void {
    expect(indexAdviceSummaries())->toBe([
        [Status::Pass, 'No other index is needed yet: nothing in the config relies on one, and the views table is small.'],
    ]);
});

it('counts the included visitor column on Postgres', function (): void {
    if (driver() !== 'pgsql') {
        $this->markTestSkipped('Only Postgres includes a column in an index.');
    }

    OptionalIndex::Visitor->create(DB::connection(), 'views');

    expect(indexAdviceSummaries())->toContain([Status::Pass, 'The `(viewable_type, viewable_id, viewed_at, visitor)` index is in place.']);
});
