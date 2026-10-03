<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Views;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['cache'])]
#[BeforeMethods(['setUp', 'rememberCounts'])]
#[AfterMethods('flushCacheStore')]
#[Warmup(1)]
#[Revs(10)]
#[Iterations(5)]
final class RememberedCountsBench extends BenchCase
{
    private const int PAGE = 20;

    /** @var Collection<int, Article> */
    private Collection $page;

    /** @return Generator<string, array{store: string}> */
    public function provideStores(): Generator
    {
        yield 'array store' => ['store' => 'array'];
        yield 'redis store' => ['store' => Application::REDIS_CACHE_STORE];
    }

    /** @param  array{store: string}  $params */
    public function rememberCounts(array $params): void
    {
        config()->set('eloquent-viewable.querying.cache.store', $params['store']);

        $this->page = Article::query()->orderBy('id')->limit(self::PAGE)->get();

        foreach (['hot', 'cold', 'type'] as $target) {
            views($this->target(['target' => $target]))->remember(60)->count();
        }

        $this->views()->forViewables($this->page)->remember(60)->counts();
    }

    /** @param  array{store: string}  $params */
    public function flushCacheStore(array $params): void
    {
        Cache::store($params['store'])->flush();
    }

    /** @param  array{target: string, store: string}  $params */
    #[ParamProviders(['provideTargets', 'provideStores'])]
    public function benchRememberedCount(array $params): void
    {
        views($this->target($params))->remember(60)->count();
    }

    /** @param  array{store: string}  $params */
    #[ParamProviders('provideStores')]
    public function benchRememberedCounts(array $params): void
    {
        $this->views()->forViewables($this->page)->remember(60)->counts();
    }

    /** @param  array{store: string}  $params */
    #[ParamProviders('provideStores')]
    public function benchForgetCache(array $params): void
    {
        views($this->target(['target' => 'hot']))->forgetCache();
    }

    /** @param  array{store: string}  $params */
    #[ParamProviders('provideStores')]
    public function benchFlushCache(array $params): void
    {
        $this->views()->flushCache();
    }

    private function views(): Views
    {
        return Container::getInstance()->make(Views::class);
    }
}
