<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Views;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['read'])]
#[BeforeMethods(['setUp', 'loadPages'])]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class CountViewsForViewablesBench extends BenchCase
{
    private const int Page = 20;

    private const int List = 1_000;

    /** @var Collection<int, Article> */
    private Collection $hotPage;

    /** @var Collection<int, Article> */
    private Collection $coldPage;

    /** @var Collection<int, Article> */
    private Collection $list;

    public function loadPages(): void
    {
        $this->hotPage = Article::query()->orderBy('id')->limit(self::Page)->get();
        $this->coldPage = Article::query()->orderByDesc('id')->limit(self::Page)->get()->reverse()->values();
        $this->list = Article::query()->orderBy('id')->limit(self::List)->get();
    }

    /**
     * @return Generator<string, array{page: string}>
     */
    public function providePages(): Generator
    {
        yield 'hot page' => ['page' => 'hot'];
        yield 'cold page' => ['page' => 'cold'];
    }

    /**
     * @return Generator<string, array{size: int}>
     */
    public function provideSizes(): Generator
    {
        yield '100 articles' => ['size' => 100];
        yield '250 articles' => ['size' => 250];
        yield '1,000 articles' => ['size' => 1_000];
    }

    /**
     * @param  array{page: string, days: int|null}  $params
     */
    #[ParamProviders(['providePages', 'providePeriods'])]
    public function benchCounts(array $params): void
    {
        $this->views()->forViewables($this->page($params))->period($this->period($params))->counts();
    }

    /**
     * @param  array{page: string, days: int|null}  $params
     */
    #[ParamProviders(['providePages', 'providePeriods'])]
    public function benchUniqueCounts(array $params): void
    {
        $this->views()->forViewables($this->page($params))->period($this->period($params))->unique()->counts();
    }

    /**
     * @param  array{page: string, days: int|null}  $params
     */
    #[ParamProviders(['providePages', 'providePeriods'])]
    public function benchCountLoop(array $params): void
    {
        $period = $this->period($params);

        foreach ($this->page($params) as $article) {
            views($article)->period($period)->count();
        }
    }

    /**
     * @param  array{size: int, days: int|null}  $params
     */
    #[ParamProviders(['provideSizes', 'providePeriods'])]
    public function benchCountsManyKeys(array $params): void
    {
        $this->views()->forViewables($this->list->take($params['size']))->period($this->period($params))->counts();
    }

    /**
     * @param  array{size: int, days: int|null}  $params
     */
    #[ParamProviders(['provideSizes', 'providePeriods'])]
    public function benchCountLoopManyKeys(array $params): void
    {
        $period = $this->period($params);

        foreach ($this->list->take($params['size']) as $article) {
            views($article)->period($period)->count();
        }
    }

    private function views(): Views
    {
        return Container::getInstance()->make(Views::class);
    }

    /**
     * @param  array{page: string}  $params
     * @return Collection<int, Article>
     */
    private function page(array $params): Collection
    {
        return $params['page'] === 'hot' ? $this->hotPage : $this->coldPage;
    }
}
