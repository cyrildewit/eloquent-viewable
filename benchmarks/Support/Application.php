<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Env;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;

/**
 * Boots a Laravel application through Testbench, outside PHPUnit, with the
 * package registered and a `benchmark` database connection as the default.
 * The connection is built from the same `DB_*` variables the test suite uses,
 * so `make bench DRIVER=mysql` and `make test-mysql` reach the same kind of
 * server, only a different database.
 */
final class Application
{
    public const string CONNECTION = 'benchmark';

    /**
     * The cache store on Redis the remembered count benchmarks read from.
     */
    public const string REDIS_CACHE_STORE = 'bench-redis';

    private static ?LaravelApplication $app = null;

    public static function boot(): LaravelApplication
    {
        if (self::$app instanceof LaravelApplication) {
            return self::$app;
        }

        $app = TestbenchApplication::create(options: [
            'load_environment_variables' => false,
            'extra' => [
                'providers' => [EloquentViewableServiceProvider::class],
                'dont-discover' => ['*'],
            ],
        ]);

        /** @var Repository $config */
        $config = $app->make(Repository::class);

        $config->set('app.timezone', 'UTC');
        // The visitor identity takes its HMAC key from the encrypter, which
        // refuses to boot without one. Fixed, so derived ids are stable.
        $config->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
        $config->set('database.default', self::CONNECTION);
        $config->set('database.connections.'.self::CONNECTION, self::connection());
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');

        // The `redis` store benchmarks reach the same Redis service the test
        // suite uses, through phpredis unless REDIS_CLIENT says otherwise.
        $config->set('database.redis.client', (string) Env::get('REDIS_CLIENT', 'phpredis'));
        $config->set('database.redis.default', [
            'host' => (string) Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => (int) Env::get('REDIS_PORT', 6379),
            'database' => 0,
        ]);

        // Its own database, so flushing the store after a run leaves the
        // buffered views and the test suite's keys in database 0 alone.
        $config->set('database.redis.bench-cache', [
            'host' => (string) Env::get('REDIS_HOST', '127.0.0.1'),
            'port' => (int) Env::get('REDIS_PORT', 6379),
            'database' => 1,
        ]);
        $config->set('cache.stores.'.self::REDIS_CACHE_STORE, ['driver' => 'redis', 'connection' => 'bench-cache']);

        date_default_timezone_set('UTC');

        return self::$app = $app;
    }

    /**
     * The path of the SQLite benchmark database. Inside the containers this
     * is a named volume, so the file survives `docker compose down` and does
     * not sit on the slow bind mount of the project tree.
     */
    public static function sqlitePath(): string
    {
        return (string) Env::get('BENCH_SQLITE_PATH', self::projectPath('build/benchmarks/benchmark.sqlite'));
    }

    public static function projectPath(string $path = ''): string
    {
        return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function connection(): array
    {
        $driver = (string) Env::get('DB_CONNECTION', 'sqlite');

        if ($driver === 'testing') {
            $driver = 'sqlite';
        }

        if ($driver === 'sqlite') {
            $path = self::sqlitePath();

            // Laravel refuses a path that does not exist yet, so an empty
            // file stands in until the seeder fills it.
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            if (! is_file($path)) {
                touch($path);
            }

            return [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ];
        }

        $server = [
            'driver' => $driver,
            'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
            'port' => (string) Env::get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => (string) Env::get('DB_DATABASE', 'benchmark'),
            'username' => (string) Env::get('DB_USERNAME', 'testing'),
            'password' => (string) Env::get('DB_PASSWORD', 'testing'),
            'prefix' => '',
        ];

        return match ($driver) {
            'mysql', 'mariadb' => [
                ...$server,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
                'engine' => null,
            ],
            'pgsql' => [
                ...$server,
                'charset' => 'utf8',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            default => throw new \RuntimeException("Unsupported benchmark driver [{$driver}]."),
        };
    }
}
