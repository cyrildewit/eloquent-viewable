<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
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
 * `rising()`, `anomalies()` and `againstBaseline()` over the last hour and
 * the last day before the anchor. `rising()` compares a window with the one
 * before it, `anomalies()` with the same window on past weeks or days, so a
 * subject reads one window more than it has samples. Spikes and drops run the
 * same statement and only score it apart, so compare them to see that a
 * negative threshold costs nothing extra.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class GrowthBench extends BenchCase
{
    private const int Limit = 10;

    /**
     * @return Generator<string, array{scope: string}>
     */
    public function provideScopes(): Generator
    {
        yield 'every type' => ['scope' => 'all'];
        yield 'articles' => ['scope' => 'type'];
    }

    /**
     * @return Generator<string, array{hours: int}>
     */
    public function provideWindows(): Generator
    {
        yield 'past hour' => ['hours' => 1];
        yield 'past day' => ['hours' => 24];
    }

    /**
     * @return Generator<string, array{seasonality: string, samples: int}>
     */
    public function provideBaselines(): Generator
    {
        yield '4 weeks' => ['seasonality' => 'week', 'samples' => 4];
        yield '8 weeks' => ['seasonality' => 'week', 'samples' => 8];
        yield '7 days' => ['seasonality' => 'day', 'samples' => 7];
    }

    /**
     * @return Generator<string, array{target: string}>
     */
    public function provideArticles(): Generator
    {
        yield 'hot article' => ['target' => 'hot'];
        yield 'cold article' => ['target' => 'cold'];
    }

    /**
     * @param  array{scope: string, hours: int}  $params
     */
    #[ParamProviders(['provideScopes', 'provideWindows'])]
    public function benchRising(array $params): void
    {
        $this->ranker($params)->period($this->window($params))->rising(self::Limit);
    }

    /**
     * @param  array{scope: string, hours: int, seasonality: string, samples: int}  $params
     */
    #[ParamProviders(['provideScopes', 'provideWindows', 'provideBaselines'])]
    public function benchAnomalies(array $params): void
    {
        $this->ranker($params)->period($this->window($params))->anomalies(limit: self::Limit, seasonality: Seasonality::from($params['seasonality']), samples: $params['samples']);
    }

    /**
     * @param  array{hours: int, seasonality: string, samples: int}  $params
     */
    #[ParamProviders(['provideWindows', 'provideBaselines'])]
    public function benchDrops(array $params): void
    {
        views(Article::class)->period($this->window($params))->anomalies(threshold: -3.0, limit: self::Limit, seasonality: Seasonality::from($params['seasonality']), samples: $params['samples']);
    }

    /**
     * @param  array{target: string, hours: int}  $params
     */
    #[ParamProviders(['provideArticles', 'provideWindows'])]
    public function benchAgainstBaseline(array $params): void
    {
        views($this->target($params))->period($this->window($params))->againstBaseline();
    }

    /**
     * The hours before the anchor, so every run reads the same rows.
     *
     * @param  array{hours: int}  $params
     */
    private function window(array $params): Period
    {
        return Period::create(Dataset::anchor()->subHours($params['hours']), Dataset::anchor());
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
