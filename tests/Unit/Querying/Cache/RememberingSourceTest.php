<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Querying\Cache\RememberingSource;
use CyrildeWit\EloquentViewable\Querying\Cache\VersionedCache;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;

function rememberingType(): Viewable
{
    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn(null);
    $viewable->allows('getMorphClass')->andReturn('posts');

    return $viewable;
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $this->cache = new CacheRepository(new ArrayStore);
    $this->versioned = new VersionedCache(
        $this->cache,
        new CacheVersions($this->cache, new Config(new Repository(['eloquent-viewable' => ['querying' => ['cache' => ['key' => 'views']]]]))),
    );

    $this->remembering = fn (ViewSource $source, string $identity = 'database'): RememberingSource => new RememberingSource(
        $source,
        $this->versioned,
        Carbon::now()->addMinutes(10),
        'views',
        $identity,
    );
});

it('remembers a key the source leaves out as zero', function (): void {
    $type = rememberingType();

    $source = Mockery::mock(ViewSource::class);
    $source->expects('countMany')->once()->with($type, [7, 8], Mockery::type(ViewsQuery::class))->andReturn([7 => 3]);

    $first = ($this->remembering)($source)->countMany($type, [7, 8], new ViewsQuery);
    $second = ($this->remembering)($source)->countMany($type, [7, 8], new ViewsQuery);

    expect($first)->toBe([7 => 3, 8 => 0])
        ->and($second)->toBe([7 => 3, 8 => 0]);
});

it('asks the source only for the keys the cache lacks', function (): void {
    $type = rememberingType();

    $source = Mockery::mock(ViewSource::class);
    $source->expects('countMany')->with($type, [7], Mockery::type(ViewsQuery::class))->andReturn([7 => 3]);
    $source->expects('countMany')->with($type, [8], Mockery::type(ViewsQuery::class))->andReturn([8 => 5]);

    ($this->remembering)($source)->countMany($type, [7], new ViewsQuery);

    expect(($this->remembering)($source)->countMany($type, [7, 8], new ViewsQuery))->toBe([7 => 3, 8 => 5]);
});

it('keeps the entries of two source identities apart', function (): void {
    $type = rememberingType();

    $source = Mockery::mock(ViewSource::class);
    $source->expects('count')->twice()->andReturn(3, 4);

    expect(($this->remembering)($source, 'database:one')->count($type, new ViewsQuery))->toBe(3)
        ->and(($this->remembering)($source, 'database:two')->count($type, new ViewsQuery))->toBe(4)
        ->and(($this->remembering)($source, 'database:one')->count($type, new ViewsQuery))->toBe(3);
});
