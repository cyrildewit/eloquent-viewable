<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Recording;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchmarkVisitor;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Buffering\Flusher;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/**
 * The `redis` store: `record()` as one `XADD` instead of an insert, to set
 * against `RecordViewBench::benchRecord`, and `flush()` landing the buffer
 * in the full table a thousand rows per statement. The stream and the rows
 * a run adds are removed afterwards.
 */
#[Groups(['write'])]
#[BeforeMethods(['setUp', 'useRedisStore'])]
#[AfterMethods('removeBufferedViews')]
final class BufferViewsBench extends BenchCase
{
    private Article $article;

    private BenchmarkVisitor $visitor;

    private RedisStreamStore $store;

    public function useRedisStore(): void
    {
        $this->article = $this->dataset->hotArticle();
        $this->visitor = new BenchmarkVisitor(str_repeat('b', 80));

        config()->set('eloquent-viewable.recording.store.driver', 'redis');
        app(StoreManager::class)->forgetDrivers();

        $store = app(ViewStore::class);

        if (! $store instanceof RedisStreamStore) {
            throw new RuntimeException('The redis store driver did not resolve to a RedisStreamStore.');
        }

        $this->store = $store;
        $this->clearStream();
    }

    public function removeBufferedViews(): void
    {
        $this->clearStream();
        $this->connection()->table('views')->where('id', '>', $this->dataset->maxViewId)->delete();
    }

    /**
     * @return Generator<string, array{views: int}>
     */
    public function provideCounts(): Generator
    {
        yield '100 views' => ['views' => 100];
        yield '1,000 views' => ['views' => 1_000];
        yield '10,000 views' => ['views' => 10_000];
    }

    /**
     * @param  array{views: int}  $params
     */
    public function bufferViewsToFlush(array $params): void
    {
        $anchor = Dataset::anchor();
        $records = [];

        for ($i = 0; $i < $params['views']; $i++) {
            $records[] = new ViewRecord(
                viewableId: $this->article->getKey(),
                viewableType: Article::class,
                visitor: str_repeat('f', 80),
                collection: null,
                viewedAt: $anchor->subMinutes($i),
            );

            if (count($records) === 1_000) {
                $this->store->storeMany($records);
                $records = [];
            }
        }

        if ($records !== []) {
            $this->store->storeMany($records);
        }
    }

    #[Warmup(5)]
    #[Revs(50)]
    #[Iterations(5)]
    public function benchRecord(): void
    {
        views($this->article)->useVisitor($this->visitor)->record();
    }

    /**
     * @param  array{views: int}  $params
     */
    #[BeforeMethods('bufferViewsToFlush')]
    #[ParamProviders('provideCounts')]
    #[Revs(1)]
    #[Iterations(5)]
    public function benchFlush(array $params): void
    {
        app(Flusher::class)->flush();
    }

    private function clearStream(): void
    {
        $config = app(Config::class);

        app(RedisFactory::class)->connection($config->redisConnection())->command('del', [$config->redisStream()]);
    }
}
