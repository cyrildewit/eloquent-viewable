<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\SchemaCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/** @return list<array{Status, string}> */
function schemaFindings(): array
{
    $findings = app()->make(SchemaCheck::class)->run();

    return array_map(
        fn (Finding $finding): array => [$finding->status, $finding->summary],
        iterator_to_array($findings, preserve_keys: false),
    );
}

/**
 * A connection of its own, so creating and dropping tables leaves the shared
 * test database, and its transaction, alone on every driver.
 */
function emptyConnection(): Builder
{
    config()->set('database.connections.doctor', ['driver' => 'sqlite', 'database' => ':memory:']);
    config()->set('eloquent-viewable.models.view.connection', 'doctor');

    return Schema::connection('doctor');
}

it('passes the schema the migrations create', function (): void {
    expect(schemaFindings())->toBe([
        [Status::Pass, 'The `views` table has every column and index the package needs.'],
    ]);
});

it('says when the views table does not exist', function (): void {
    emptyConnection();

    expect(schemaFindings())->toBe([
        [Status::Failure, 'The `views` table does not exist.'],
    ]);
});

it('names the columns and indexes the views table misses', function (): void {
    emptyConnection()->create('views', function (Blueprint $table): void {
        $table->id();
        $table->morphs('viewable');
        $table->string('visitor')->nullable();
        $table->timestamp('viewed_at');
    });

    expect(schemaFindings())->toBe([
        [Status::Failure, 'The `views` table has no `viewer_type`, `viewer_id`, `collection`, `context` column.'],
        [Status::Failure, 'The `views` table has no index on `(viewable_type, viewable_id, viewed_at)`.'],
        [Status::Failure, 'The `views` table has no index on `(viewed_at)`.'],
    ]);
});

it('accepts an index under a name of its own that starts with the columns', function (): void {
    emptyConnection()->create('views', function (Blueprint $table): void {
        $table->id();
        $table->morphs('viewable');
        $table->nullableMorphs('viewer');
        $table->string('visitor')->nullable();
        $table->string('collection')->nullable();
        $table->json('context')->nullable();
        $table->timestamp('viewed_at');

        $table->index(['viewable_type', 'viewable_id', 'viewed_at', 'visitor'], 'my_own_name');
        $table->index(['viewed_at'], 'another_name');
    });

    expect(schemaFindings())->toBe([
        [Status::Pass, 'The `views` table has every column and index the package needs.'],
    ]);
});

it('checks the retention state table once retention is configured', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    expect(schemaFindings())->toContain([Status::Pass, 'The retention state table exists.']);

    emptyConnection();

    expect(schemaFindings())->toContain([Status::Failure, 'Retention is configured, but the `view_retention_state` table does not exist.']);
});

it('checks the rollup table once rollups are configured', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    expect(schemaFindings())->toContain([Status::Pass, 'The `view_rollups` table exists.']);

    config()->set('eloquent-viewable.retention.rollups.table', 'missing_rollups');

    expect(schemaFindings())->toContain([Status::Failure, 'Rollups are configured, but the `missing_rollups` table does not exist.']);
});

it('checks the milestones table once thresholds are configured', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);
    config()->set('eloquent-viewable.milestones.thresholds', [Post::class => ['cached_views' => [100]]]);

    expect(schemaFindings())->toContain([Status::Pass, 'The `view_milestones` table exists.']);

    config()->set('eloquent-viewable.milestones.table', 'missing_milestones');

    expect(schemaFindings())->toContain([Status::Failure, 'Milestones are configured, but the `missing_milestones` table does not exist.']);
});

it('checks the spikes table once models are watched', function (): void {
    config()->set('eloquent-viewable.spikes.types', [Post::class => []]);

    expect(schemaFindings())->toContain([Status::Pass, 'The `view_spikes` table exists.']);

    config()->set('eloquent-viewable.spikes.table', 'missing_spikes');

    expect(schemaFindings())->toContain([Status::Failure, 'Spikes are configured, but the `missing_spikes` table does not exist.']);
});

it('checks the counter columns on the tables of the models', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    expect(schemaFindings())->toContain([Status::Pass, 'The `posts` table has every counter column in `querying.counters`.']);

    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views', 'views_last_week' => ['period' => '7d']]]);

    expect(schemaFindings())->toContain([Status::Failure, 'The `posts` table has no `views_last_week` column, which `querying.counters` writes to.']);
});

it('says what to run to fix it', function (): void {
    emptyConnection();

    $finding = iterator_to_array(app()->make(SchemaCheck::class)->run(), preserve_keys: false)[0];

    expect($finding->fix)->toContain('--tag="migrations"');
});
