<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Feature;

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EloquentViewableServiceProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        // The in-memory SQLite connection by default. CI sets DB_CONNECTION to
        // run the same suite against MySQL, MariaDB and Postgres.
        $app['config']->set('database.default', Env::get('DB_CONNECTION', 'testing'));

        // The Redis store's tests run against a real Redis, the `redis`
        // service in Docker Compose and on CI, and against both clients. Every
        // parallel worker gets a pair of databases of its own, this one and
        // the next for the cache, so workers never share a stream or flush
        // each other's keys. A serial run has no token and uses 0 and 1.
        $app['config']->set('database.redis.default', [
            'host' => Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => Env::get('REDIS_PORT', 6379),
            'database' => 2 * (int) Env::get('TEST_TOKEN', 0),
        ]);
    }

    /**
     * Runs on the `DatabaseRefreshed` event, so the schema is created once per
     * process. `RefreshDatabase` rolls every test back into it. Creating it
     * from `defineDatabaseMigrations()` instead would rerun per test, which a
     * server-backed driver rejects on the second one because the tables are
     * still there.
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        require_once __DIR__.'/../../database/migrations/create_views_table.php.stub';
        require_once __DIR__.'/../Fixtures/database/migrations/2018_02_22_194715_create_posts_table.php';
        require_once __DIR__.'/../Fixtures/database/migrations/2018_02_22_194716_create_apartments_table.php';
        require_once __DIR__.'/../Fixtures/database/migrations/2018_02_22_194717_create_users_table.php';

        new \CreateViewsTable()->up();

        require_once __DIR__.'/../../database/migrations/create_view_retention_state_table.php.stub';

        new \CreateViewRetentionStateTable()->up();

        require_once __DIR__.'/../../database/migrations/create_view_rollups_table.php.stub';

        new \CreateViewRollupsTable()->up();
        new \CreatePostsTable()->up();
        new \CreateApartmentsTable()->up();
        new \CreateUsersTable()->up();

        require_once __DIR__.'/../Fixtures/database/migrations/2018_02_22_194718_create_uuid_and_ulid_tables.php';

        new \CreateUuidAndUlidTables()->up();

        // The shipped stub again, the way an application that called
        // `Schema::morphUsingUuids()` or `morphUsingUlids()` runs it.
        $this->createViewsTableWithMorphKeyType('uuid', 'uuid_views');
        $this->createViewsTableWithMorphKeyType('ulid', 'ulid_views');

        // Each sample keeps its own tables next to its code.
        foreach (glob(__DIR__.'/../../samples/*/database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }
    }

    private function createViewsTableWithMorphKeyType(string $type, string $table): void
    {
        $config = $this->app['config'];
        $default = $config->get('eloquent-viewable.models.view.table_name');

        Schema::defaultMorphKeyType($type);
        $config->set('eloquent-viewable.models.view.table_name', $table);

        try {
            new \CreateViewsTable()->up();
        } finally {
            Schema::defaultMorphKeyType('int');
            $config->set('eloquent-viewable.models.view.table_name', $default);
        }
    }
}
