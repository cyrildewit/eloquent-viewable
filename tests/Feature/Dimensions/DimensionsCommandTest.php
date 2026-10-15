<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * A database directory of this worker's own, so parallel workers never read
 * each other's migrations.
 */
function dimensionMigrations(): string
{
    $path = sys_get_temp_dir().'/eloquent-viewable-dimensions-'.Env::get('TEST_TOKEN', '0');

    File::deleteDirectory($path);
    app()->useDatabasePath($path);

    return "{$path}/migrations";
}

/**
 * A views table on a connection of its own, without dimension columns, the
 * way the shipped stub creates it.
 */
function bareViewsTable(): void
{
    config()->set('database.connections.dimensions', ['driver' => 'sqlite', 'database' => ':memory:']);
    config()->set('eloquent-viewable.models.view.connection', 'dimensions');

    Schema::connection('dimensions')->create('views', function (Blueprint $table): void {
        $table->id();
        $table->morphs('viewable');
        $table->string('visitor')->nullable();
        $table->json('context')->nullable();
        $table->timestamp('viewed_at');
    });
}

function writtenMigration(string $directory): Migration
{
    $files = glob("{$directory}/*.php") ?: [];

    expect($files)->toHaveCount(1);

    return require $files[0];
}

/** @param  array<string, mixed>|null  $context */
function viewWithContext(?array $context, ?string $campaign = null): View
{
    return View::factory()
        ->for(test()->post, 'viewable')
        ->withContext($context)
        ->withDimensions(['campaign' => $campaign])
        ->create();
}

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/eloquent-viewable-dimensions-'.Env::get('TEST_TOKEN', '0'));
});

it('says when the views table does not exist', function (): void {
    config()->set('database.connections.dimensions', ['driver' => 'sqlite', 'database' => ':memory:']);
    config()->set('eloquent-viewable.models.view.connection', 'dimensions');

    $this->artisan('views:dimensions')
        ->expectsOutputToContain('The `views` table does not exist.')
        ->assertFailed();
});

it('writes nothing when every dimension has its column', function (): void {
    $directory = dimensionMigrations();
    config()->set('eloquent-viewable.dimensions.definitions', ['source' => Source::class, 'plan' => PlanDimension::class]);

    $this->artisan('views:dimensions')
        ->expectsOutputToContain('Every dimension has its column.')
        ->assertSuccessful();

    expect(File::isDirectory($directory))->toBeFalse();
});

it('writes a migration that adds the columns the views table lacks', function (): void {
    $directory = dimensionMigrations();
    bareViewsTable();
    config()->set('eloquent-viewable.dimensions.definitions', [
        'source' => Source::class,
        'device' => Device::class,
        'plan' => PlanDimension::class,
    ]);

    $this->artisan('views:dimensions')
        ->expectsOutputToContain('Wrote a migration that adds `source`, `device`')
        ->expectsOutputToContain('php artisan migrate')
        ->assertSuccessful();

    $files = glob("{$directory}/*.php") ?: [];

    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->toEndWith('_add_source_device_dimensions_to_views_table.php');

    $migration = writtenMigration($directory);
    $schema = Schema::connection('dimensions');

    $migration->up();

    expect($schema->hasColumns('views', ['source', 'device']))->toBeTrue()
        ->and($schema->hasColumn('views', 'plan'))->toBeFalse();

    $migration->up();

    expect($schema->hasColumns('views', ['source', 'device']))->toBeTrue();

    $migration->down();

    expect($schema->hasColumn('views', 'source'))->toBeFalse()
        ->and($schema->hasColumn('views', 'device'))->toBeFalse();

    $migration->down();
});

it('lets the written migration run after the columns were added another way', function (): void {
    $directory = dimensionMigrations();
    bareViewsTable();
    config()->set('eloquent-viewable.dimensions.definitions', ['source' => Source::class]);

    $this->artisan('views:dimensions')->assertSuccessful();

    Schema::connection('dimensions')->table('views', function (Blueprint $table): void {
        $table->string('source', 64)->nullable();
    });

    writtenMigration($directory)->up();

    expect(Schema::connection('dimensions')->hasColumn('views', 'source'))->toBeTrue();
});

describe('backfill', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.dimensions.definitions', [
            'campaign' => [Campaign::class, 'personal' => false],
            'plan' => PlanDimension::class,
        ]);

        $this->post = Post::factory()->create();
    });

    it('copies the values a dimension kept in context into its column', function (): void {
        $launch = viewWithContext(['campaign' => 'launch']);
        $long = viewWithContext(['campaign' => str_repeat('a', 80)]);
        $kept = viewWithContext(['campaign' => 'launch'], campaign: 'already');
        $none = viewWithContext(['other' => 'x']);
        $empty = viewWithContext(null);

        $this->artisan('views:dimensions', ['--backfill' => 'campaign'])
            ->expectsOutputToContain('Copying `context->campaign` into the `campaign` column...')
            ->expectsOutputToContain('Copied 2 values.')
            ->assertSuccessful();

        expect($launch->fresh()?->getAttribute('campaign'))->toBe('launch')
            ->and($long->fresh()?->getAttribute('campaign'))->toBe(str_repeat('a', 64))
            ->and($kept->fresh()?->getAttribute('campaign'))->toBe('already')
            ->and($none->fresh()?->getAttribute('campaign'))->toBeNull()
            ->and($empty->fresh()?->getAttribute('campaign'))->toBeNull();
    });

    it('copies from the path it is given', function (): void {
        $view = viewWithContext(['utm' => ['campaign' => 'spring']]);

        $this->artisan('views:dimensions', ['--backfill' => 'campaign', '--from' => 'context->utm->campaign'])
            ->expectsOutputToContain('Copied 1 values.')
            ->assertSuccessful();

        expect($view->fresh()?->getAttribute('campaign'))->toBe('spring');
    });

    it('copies in chunks', function (): void {
        config()->set('eloquent-viewable.retention.chunk', 2);

        foreach (range(1, 5) as $index) {
            viewWithContext(['campaign' => "campaign-{$index}"]);
        }

        $this->artisan('views:dimensions', ['--backfill' => 'campaign'])
            ->expectsOutputToContain('Copied 5 values.')
            ->assertSuccessful();

        expect(View::query()->whereNull('campaign')->count())->toBe(0);
    });

    it('copies nothing when nothing is left to copy', function (): void {
        viewWithContext(['campaign' => 'launch'], campaign: 'launch');

        $this->artisan('views:dimensions', ['--backfill' => 'campaign'])
            ->expectsOutputToContain('Copied 0 values.')
            ->assertSuccessful();
    });

    it('stops at the time limit and carries on in the next run', function (): void {
        config()->set('eloquent-viewable.retention.chunk', 1);

        $first = viewWithContext(['campaign' => 'one']);
        $second = viewWithContext(['campaign' => 'two']);

        travelOnFirst('update', 'views');

        $this->artisan('views:dimensions', ['--backfill' => 'campaign', '--max-seconds' => 60])
            ->expectsOutputToContain('Copied 1 values.')
            ->expectsOutputToContain('Stopped at the time limit.')
            ->assertSuccessful();

        expect($first->fresh()?->getAttribute('campaign'))->toBe('one')
            ->and($second->fresh()?->getAttribute('campaign'))->toBeNull();

        $this->artisan('views:dimensions', ['--backfill' => 'campaign'])
            ->expectsOutputToContain('Copied 1 values.')
            ->assertSuccessful();

        expect($second->fresh()?->getAttribute('campaign'))->toBe('two');
    });

    it('refuses what it cannot backfill', function (array $options, string $error): void {
        $this->artisan('views:dimensions', $options)
            ->expectsOutputToContain($error)
            ->assertFailed();
    })->with([
        'an unknown dimension' => [['--backfill' => 'missing'], 'No dimension is named `missing`'],
        'a dimension kept in context' => [['--backfill' => 'plan'], 'The `plan` dimension is kept in `context`.'],
        'a path outside context' => [['--backfill' => 'campaign', '--from' => 'visitor'], 'The --from option must be a path into context'],
        'a time limit that is not one' => [['--backfill' => 'campaign', '--max-seconds' => 'soon'], 'The --max-seconds option must be a positive integer.'],
    ]);
});
