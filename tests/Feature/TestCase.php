<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Feature;

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

#[WithEnv('DB_CONNECTION', 'testing')]
abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EloquentViewableServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        require_once __DIR__.'/../../migrations/create_views_table.php.stub';
        require_once __DIR__.'/../database/migrations/2018_02_22_194715_create_posts_table.php';
        require_once __DIR__.'/../database/migrations/2018_02_22_194716_create_apartments_table.php';

        new \CreateViewsTable()->up();
        new \CreatePostsTable()->up();
        new \CreateApartmentsTable()->up();
    }
}
