<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
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

#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(1)]
#[Iterations(5)]
final class TopViewedBench extends BenchCase
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
     * @param  array{scope: string, days: int|null}  $params
     */
    #[ParamProviders(['provideScopes', 'providePeriods'])]
    public function benchTop(array $params): void
    {
        $this->ranker($params)->period($this->period($params))->top(self::Limit);
    }

    /**
     * @param  array{scope: string, days: int|null}  $params
     */
    #[ParamProviders(['provideScopes', 'providePeriods'])]
    public function benchUniqueTop(array $params): void
    {
        $this->ranker($params)->period($this->period($params))->unique()->top(self::Limit);
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
