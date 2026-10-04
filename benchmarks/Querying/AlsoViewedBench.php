<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Generator;
use Illuminate\Support\Facades\Config;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/**
 * `alsoViewed()` joins the views table to itself through the visitors of one
 * article, so its cost follows every view of every visitor it pairs. The hot
 * article has the most visitors, and the cap on them is what keeps it bounded.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class AlsoViewedBench extends BenchCase
{
    private const int Limit = 10;

    /**
     * @return Generator<string, array{target: string}>
     */
    public function provideArticles(): Generator
    {
        yield 'hot article' => ['target' => 'hot'];
        yield 'cold article' => ['target' => 'cold'];
    }

    /**
     * @return Generator<string, array{max_visitors: int|null}>
     */
    public function provideCaps(): Generator
    {
        yield 'thousand visitors' => ['max_visitors' => 1_000];
        yield 'every visitor' => ['max_visitors' => null];
    }

    /**
     * @param  array{target: string, max_visitors: int|null, days: int|null}  $params
     */
    #[ParamProviders(['provideArticles', 'provideCaps', 'providePeriods'])]
    public function benchAlsoViewed(array $params): void
    {
        Config::set('eloquent-viewable.querying.also_viewed.max_visitors', $params['max_visitors']);

        views($this->article($params))->period($this->period($params))->alsoViewed(self::Limit);
    }

    /**
     * @param  array{target: string}  $params
     */
    private function article(array $params): Viewable
    {
        $article = $this->target($params);

        if (! $article instanceof Viewable) {
            throw new RuntimeException("alsoViewed() needs one article, [{$params['target']}] given.");
        }

        return $article;
    }
}
