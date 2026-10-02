<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Feature;

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
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
        // service in Docker Compose and on CI, and against both clients.
        $app['config']->set('database.redis.default', [
            'host' => Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => Env::get('REDIS_PORT', 6379),
            'database' => 0,
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
        new \CreatePostsTable()->up();
        new \CreateApartmentsTable()->up();
        new \CreateUsersTable()->up();

        // Each sample keeps its own tables next to its code.
        foreach (glob(__DIR__.'/../../samples/*/database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }
    }
}
