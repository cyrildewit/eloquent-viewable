<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Period;
use Generator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base class for the benchmarks that hit the database. Subclasses list
 * `setUp` in their `BeforeMethods`, which boots the application and loads
 * the dataset description once per benchmark process, outside the timed
 * region.
 */
abstract class BenchCase
{
    protected Dataset $dataset;

    public function setUp(): void
    {
        $this->guardAgainstXdebug();

        Application::boot();

        $this->dataset = Dataset::load($this->connection());
    }

    /**
     * @return Generator<string, array{target: string}>
     */
    public function provideTargets(): Generator
    {
        yield 'hot article' => ['target' => 'hot'];
        yield 'cold article' => ['target' => 'cold'];
        yield 'all articles' => ['target' => 'type'];
    }

    /**
     * @return Generator<string, array{days: int|null}>
     */
    public function providePeriods(): Generator
    {
        yield 'all time' => ['days' => null];
        yield 'past year' => ['days' => 365];
        yield 'past 30 days' => ['days' => 30];
        yield 'past day' => ['days' => 1];
    }

    protected function connection(): ConnectionInterface
    {
        return DB::connection(Application::CONNECTION);
    }

    /**
     * The viewable a `target` parameter names: a model for `hot` and `cold`,
     * the class name for `type`, which counts across every article.
     *
     * @param  array{target: string}  $params
     */
    protected function target(array $params): Viewable|string
    {
        return match ($params['target']) {
            'hot' => $this->dataset->hotArticle(),
            'cold' => $this->dataset->coldArticle(),
            'type' => Article::class,
            default => throw new RuntimeException("Unknown target [{$params['target']}]."),
        };
    }

    /**
     * @param  array{days: int|null}  $params
     */
    protected function period(array $params): ?Period
    {
        return $params['days'] === null ? null : $this->dataset->pastDays($params['days']);
    }

    /**
     * Xdebug slows PHP down several times over even when it only collects
     * coverage, and the containers enable it by default. The Make targets
     * turn it off; a run that forgot would produce numbers that compare with
     * nothing.
     */
    private function guardAgainstXdebug(): void
    {
        if (! extension_loaded('xdebug')) {
            return;
        }

        // The environment variable overrides the ini setting when present.
        $mode = Env::get('XDEBUG_MODE') ?? ini_get('xdebug.mode');

        if ($mode !== 'off' && $mode !== '') {
            throw new RuntimeException(
                'Xdebug is active. Run the benchmarks through `make bench`, or with XDEBUG_MODE=off.'
            );
        }
    }
}
