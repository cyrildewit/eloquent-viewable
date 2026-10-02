<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Querying\Sources\SourceManager;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('is a singleton', function (): void {
    expect($this->app->make(SourceManager::class))->toBe($this->app->make(SourceManager::class));
});

it('builds the database source by default', function (): void {
    expect($this->app->make(SourceManager::class)->driver())->toBeInstanceOf(DatabaseSource::class);
});

it('resolves the ViewSource contract through the manager', function (): void {
    $source = $this->app->make(ViewSource::class);

    expect($source)->toBe($this->app->make(SourceManager::class)->driver())
        ->and($this->app->make(ViewSource::class))->toBe($source);
});

it('accepts a custom driver', function (): void {
    $custom = new class implements ViewSource
    {
        public function count(Viewable $viewable, ViewsQuery $query): int
        {
            return 7;
        }

        public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
        {
            return [];
        }

        public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
        {
            return DB::query()->selectRaw('7');
        }
    };

    $this->app->make(SourceManager::class)->extend('custom', fn (Application $app): ViewSource => $custom);

    Config::set('eloquent-viewable.querying.source.driver', 'custom');

    expect($this->app->make(ViewSource::class))->toBe($custom)
        ->and(views(Post::factory()->create())->count())->toBe(7);
});

it('rejects a driver that is not registered', function (): void {
    Config::set('eloquent-viewable.querying.source.driver', 'aggregate');

    expect(fn (): ViewSource => $this->app->make(ViewSource::class))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.querying.source.driver` config value names a driver that is not registered, `aggregate` given.');
});
