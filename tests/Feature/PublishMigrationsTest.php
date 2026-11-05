<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * The stubs the `migrations` tag publishes, after booting the provider again
 * against a migrations directory of its own that holds the given files.
 *
 * @param  list<string>  $existing
 * @return list<string>
 */
function publishedMigrations(array $existing = []): array
{
    $path = sys_get_temp_dir().'/eloquent-viewable-publish-'.Env::get('TEST_TOKEN', '0');

    File::deleteDirectory($path);
    File::ensureDirectoryExists("{$path}/migrations");

    foreach ($existing as $file) {
        File::put("{$path}/migrations/{$file}", '');
    }

    app()->useDatabasePath($path);

    // The provider adds to what earlier boots registered, so it boots here
    // with nothing registered.
    $publishes = ServiceProvider::$publishes;
    $groups = ServiceProvider::$publishGroups;
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];

    try {
        app()->getProvider(EloquentViewableServiceProvider::class)?->boot();

        return array_map(basename(...), array_keys(ServiceProvider::pathsToPublish(EloquentViewableServiceProvider::class, 'migrations')));
    } finally {
        ServiceProvider::$publishes = $publishes;
        ServiceProvider::$publishGroups = $groups;
        File::deleteDirectory($path);
    }
}

it('publishes every table under the migrations tag', function (): void {
    expect(publishedMigrations())->toBe([
        'create_views_table.php.stub',
        'create_view_retention_state_table.php.stub',
        'create_view_rollups_table.php.stub',
        'create_view_pairs_table.php.stub',
        'create_view_milestones_table.php.stub',
        'create_view_spikes_table.php.stub',
    ]);
});

it('leaves out a migration the application already has', function (): void {
    expect(publishedMigrations(['2018_01_01_000000_create_views_table.php', '2026_01_01_000000_create_view_pairs_table.php']))->toBe([
        'create_view_retention_state_table.php.stub',
        'create_view_rollups_table.php.stub',
        'create_view_milestones_table.php.stub',
        'create_view_spikes_table.php.stub',
    ]);
});
