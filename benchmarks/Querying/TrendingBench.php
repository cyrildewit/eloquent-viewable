<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Views;
use Generator;
use Illuminate\Container\Container;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/**
 * `Views::trending()` and `orderByTrending()`, the counterparts of
 * `TopViewedBench` and `OrderByViewsBench` with every view weighed by its
 * age. They read the same rows, so compare a subject with its counterpart to
 * see what the weighing costs. A period without a start reads the curve's
 * horizon, eight days by default, up to the anchor of the dataset. The past
 * day is weighed per hour, the longer periods per day.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class TrendingBench extends BenchCase
{
    private const int Limit = 10;

    private const int PageSize = 20;

    /**
     * @return Generator<string, array{scope: string}>
     */
    public function provideScopes(): Generator
    {
        yield 'every type' => ['scope' => 'all'];
        yield 'articles' => ['scope' => 'type'];
    }

    /**
     * @param  array{scope: string, days: int|null}  $params
     */
    #[ParamProviders(['provideScopes', 'providePeriods'])]
    public function benchTrending(array $params): void
    {
        $this->ranker($params)->period($this->window($params))->trending(self::Limit);
    }

    /**
     * @param  array{scope: string, days: int|null}  $params
     */
    #[ParamProviders(['provideScopes', 'providePeriods'])]
    public function benchUniqueTrending(array $params): void
    {
        $this->ranker($params)->period($this->window($params))->unique()->trending(self::Limit);
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchOrderByTrending(array $params): void
    {
        Article::query()->orderByTrending('desc', $this->window($params))->limit(self::PageSize)->get();
    }

    /**
     * @param  array{days: int|null}  $params
     */
    #[ParamProviders('providePeriods')]
    public function benchOrderByUniqueTrending(array $params): void
    {
        Article::query()->orderByTrending('desc', $this->window($params), unique: true)->limit(self::PageSize)->get();
    }

    /**
     * The period, or one without a start that ends at the anchor, so all
     * time reads the horizon before the dataset ends rather than before now.
     *
     * @param  array{days: int|null}  $params
     */
    private function window(array $params): Period
    {
        return $this->period($params) ?? Period::upto(Dataset::anchor());
    }

    /** @param  array{scope: string}  $params */
    private function ranker(array $params): Views
    {
        return match ($params['scope']) {
            'all' => Container::getInstance()->make(Views::class),
            'type' => views(Article::class),
            default => throw new RuntimeException("Unknown scope [{$params['scope']}]."),
        };
    }
}
