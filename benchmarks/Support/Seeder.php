<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Models\Video;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeds a deterministic dataset of articles, videos and views.
 *
 * The same size and seed produce byte-identical rows, so two runs against
 * datasets seeded on different machines compare the same work. The shape is
 * meant to look like production rather than a uniform spray:
 *
 *  - views spread over the articles on a power law, so a handful of articles
 *    are hot and most are cold;
 *  - visitors come back, on a heavy-tailed distribution, so a distinct count
 *    is well below the plain count;
 *  - timestamps run chronologically over two years, with a daily curve, a
 *    quieter weekend and a rising trend, so the clustered primary key orders
 *    the rows by time the way real traffic does;
 *  - one in ten views belongs to a video, so every query has to filter on
 *    the viewable type;
 *  - one in five views is in a named collection.
 */
final class Seeder
{
    public const int SpanDays = 730;

    private const int ChunkRows = 1_000;

    private const int DaysPerTransaction = 25;

    private const string LabelFormat = 'Y-m-d H:i:s';

    /**
     * Relative traffic per hour of the day, midnight first.
     */
    private const array HourlyWeights = [
        0.30, 0.20, 0.15, 0.10, 0.10, 0.15, 0.30, 0.60, 0.90, 1.10, 1.20, 1.20,
        1.10, 1.10, 1.10, 1.10, 1.00, 1.00, 1.10, 1.20, 1.30, 1.20, 0.90, 0.50,
    ];

    private const array Collections = ['homepage', 'rss', 'newsletter', 'search'];

    private Randomizer $random;

    /**
     * Alias table for the article distribution: `$probability[$i]` is the
     * chance of keeping bucket `$i`, else `$alias[$i]` is drawn.
     *
     * @var list<float>
     */
    private array $probability = [];

    /** @var list<int> */
    private array $alias = [];

    /** @var list<int> */
    private array $hourLookup = [];

    private readonly string $table;

    public function __construct(private readonly ConnectionInterface $connection)
    {
        $this->table = Container::getInstance()->make(Config::class)->viewTable() ?? 'views';
    }

    public function seed(DatasetSize $size, int $seed): Dataset
    {
        $this->random = new Randomizer(new Mt19937($seed));

        Output::heading("Seeding the {$size->value} dataset on {$this->connection->getDriverName()} with seed {$seed}");

        $this->createSchema();
        $this->seedViewables($size);

        $this->prepareArticleDistribution($size->articles());
        $this->prepareHourDistribution();

        $this->speedUpSqlite();
        $this->dropSecondaryIndexes();
        $this->seedViews($size);
        $this->createSecondaryIndexes();
        $this->restoreSqlite();
        $this->analyze();

        $dataset = $this->describe($size, $seed);
        $dataset->save($this->connection);

        Output::line();
        Output::line($dataset->describe());

        return $dataset;
    }

    private function createSchema(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->dropIfExists(Dataset::Table);
        $schema->dropIfExists($this->table);
        $schema->dropIfExists('articles');
        $schema->dropIfExists('videos');

        // The package's own migration, so the table and its indexes are
        // what an application gets, apart from the index on `viewed_at`:
        // it is dropped with the other secondary indexes and not built again,
        // which would change the plans of every benchmark on this dataset.
        require_once Application::projectPath('database/migrations/create_views_table.php.stub');

        new \CreateViewsTable()->up();

        foreach (['articles', 'videos'] as $table) {
            $schema->create($table, function (Blueprint $blueprint): void {
                $blueprint->increments('id');
                $blueprint->string('title');
            });
        }

        Output::line('Created the schema.');
    }

    private function seedViewables(DatasetSize $size): void
    {
        $this->insertNumbered('articles', 'Article', $size->articles());
        $this->insertNumbered('videos', 'Video', $size->videos());

        Output::line(sprintf('Inserted %s articles and %s videos.', number_format($size->articles()), number_format($size->videos())));
    }

    private function insertNumbered(string $table, string $label, int $count): void
    {
        for ($from = 1; $from <= $count; $from += self::ChunkRows) {
            $rows = [];

            for ($id = $from; $id < $from + self::ChunkRows && $id <= $count; $id++) {
                $rows[] = ['id' => $id, 'title' => "{$label} #{$id}"];
            }

            $this->connection->table($table)->insert($rows);
        }

        // Explicit ids leave a Postgres sequence where it was, so the next
        // row an application inserts would collide with the first one.
        if ($this->connection->getDriverName() === 'pgsql') {
            $this->connection->statement(
                "select setval(pg_get_serial_sequence('{$table}', 'id'), (select max(id) from {$table}))"
            );
        }
    }

    private function seedViews(DatasetSize $size): void
    {
        $total = $size->views();
        $dayCounts = $this->dayCounts($total);
        $anchor = $this->anchorTimestamp();
        $videoCount = $size->videos();
        $visitorPool = max(1_000, intdiv($total, 3));

        $startedAt = microtime(true);
        $inserted = 0;
        $nextReport = 0.1;
        $rows = [];

        Output::line(sprintf('Inserting %s views over %d days...', number_format($total), self::SpanDays));

        $this->connection->beginTransaction();

        foreach ($dayCounts as $day => $count) {
            $dayStart = $anchor - (self::SpanDays - $day) * 86_400;

            foreach ($this->secondsOfDay($count) as $seconds) {
                $isVideo = $this->random->getInt(0, 9) === 0;

                $rows[] = [
                    'viewable_type' => $isVideo ? Video::class : Article::class,
                    'viewable_id' => $isVideo ? $this->random->getInt(1, $videoCount) : $this->drawArticle(),
                    'visitor' => $this->drawVisitor($visitorPool),
                    'collection' => $this->random->getInt(0, 4) === 0
                        ? self::Collections[$this->random->getInt(0, count(self::Collections) - 1)]
                        : null,
                    'viewed_at' => gmdate(self::LabelFormat, $dayStart + $seconds),
                ];

                if (count($rows) === self::ChunkRows) {
                    $this->connection->table($this->table)->insert($rows);
                    $inserted += count($rows);
                    $rows = [];
                }
            }

            if (($day + 1) % self::DaysPerTransaction === 0) {
                $this->connection->commit();
                $this->connection->beginTransaction();
            }

            if ($inserted >= $total * $nextReport) {
                Output::line(sprintf(
                    '  %3d%%  %s rows, %s rows/s',
                    (int) round($nextReport * 100),
                    number_format($inserted),
                    number_format($inserted / max(microtime(true) - $startedAt, 0.001)),
                ));
                $nextReport += 0.1;
            }
        }

        if ($rows !== []) {
            $this->connection->table($this->table)->insert($rows);
            $inserted += count($rows);
        }

        $this->connection->commit();

        Output::line(sprintf('Inserted %s views in %s.', number_format($inserted), Output::elapsed($startedAt)));
    }

    /**
     * How many views each of the span's days gets. Weekends are quieter, the
     * trend rises over the span and every day gets a little jitter. The
     * counts add up to exactly the total.
     *
     * @return list<int>
     */
    private function dayCounts(int $total): array
    {
        $anchor = $this->anchorTimestamp();
        $weights = [];

        for ($day = 0; $day < self::SpanDays; $day++) {
            $dayStart = $anchor - (self::SpanDays - $day) * 86_400;
            $weekday = (int) gmdate('N', $dayStart);

            $weekend = match ($weekday) {
                6 => 0.75,
                7 => 0.70,
                default => 1.0,
            };
            $trend = 0.6 + 0.8 * $day / (self::SpanDays - 1);
            $jitter = 0.9 + 0.2 * $this->random->nextFloat();

            $weights[] = $weekend * $trend * $jitter;
        }

        $sum = array_sum($weights);
        $counts = array_map(static fn (float $weight): int => (int) floor($total * $weight / $sum), $weights);
        $remainder = $total - array_sum($counts);

        for ($day = 0; $remainder > 0; $day++, $remainder--) {
            $counts[$day % self::SpanDays]++;
        }

        return $counts;
    }

    /**
     * The offsets within one day of the given number of views, in order, so
     * the rows are inserted chronologically.
     *
     * @return list<int>
     */
    private function secondsOfDay(int $count): array
    {
        $seconds = [];

        for ($i = 0; $i < $count; $i++) {
            $hour = $this->hourLookup[$this->random->getInt(0, count($this->hourLookup) - 1)];
            $seconds[] = $hour * 3_600 + $this->random->getInt(0, 3_599);
        }

        sort($seconds);

        return $seconds;
    }

    /**
     * Vose's alias method over Zipf weights, so drawing an article is two
     * random numbers and a lookup. Article `$i` has weight `1 / $i`, which
     * gives the first article roughly a tenth of all views and the last a
     * ten-thousandth of that.
     */
    private function prepareArticleDistribution(int $articles): void
    {
        $weights = [];

        for ($rank = 1; $rank <= $articles; $rank++) {
            $weights[] = 1 / $rank;
        }

        $sum = array_sum($weights);
        $scaled = array_map(static fn (float $weight): float => $weight * $articles / $sum, $weights);

        $small = [];
        $large = [];

        foreach ($scaled as $index => $value) {
            if ($value < 1) {
                $small[] = $index;

                continue;
            }

            $large[] = $index;
        }

        $this->probability = array_fill(0, $articles, 1.0);
        $this->alias = array_fill(0, $articles, 0);

        while ($small !== [] && $large !== []) {
            $less = array_pop($small);
            $more = array_pop($large);

            $this->probability[$less] = $scaled[$less];
            $this->alias[$less] = $more;

            $scaled[$more] = $scaled[$more] + $scaled[$less] - 1;

            if ($scaled[$more] < 1) {
                $small[] = $more;

                continue;
            }

            $large[] = $more;
        }
    }

    /**
     * A thousand slots filled in proportion to the hourly weights, so an
     * hour is drawn with one integer.
     */
    private function prepareHourDistribution(): void
    {
        $sum = array_sum(self::HourlyWeights);
        $this->hourLookup = [];

        foreach (self::HourlyWeights as $hour => $weight) {
            $slots = (int) round(1_000 * $weight / $sum);

            for ($i = 0; $i < $slots; $i++) {
                $this->hourLookup[] = $hour;
            }
        }
    }

    private function drawArticle(): int
    {
        $bucket = $this->random->getInt(0, count($this->probability) - 1);

        return 1 + ($this->random->nextFloat() < $this->probability[$bucket] ? $bucket : $this->alias[$bucket]);
    }

    /**
     * A visitor from a pool a third the size of the dataset, skewed towards
     * the low numbers, so some visitors come back often and most rarely. The
     * id has the eighty characters the package's own visitor ids have.
     */
    private function drawVisitor(int $pool): string
    {
        $fraction = $this->random->nextFloat();

        return self::visitor((int) floor($pool * $fraction * $fraction));
    }

    /**
     * The id of the visitor with the given number. Number 0 is drawn most
     * often, so it is the visitor with the most views in any dataset.
     */
    public static function visitor(int $number): string
    {
        return substr(hash('sha512', "visitor:{$number}"), 0, 80);
    }

    /**
     * Loading into a table without secondary indexes and building them
     * afterwards is several times faster than maintaining them per row.
     */
    private function dropSecondaryIndexes(): void
    {
        $this->connection->getSchemaBuilder()->table($this->table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex('views_viewable_type_viewable_id_index');
            $blueprint->dropIndex('views_viewable_viewed_at_index');
            $blueprint->dropIndex('views_viewed_at_index');
        });
    }

    private function createSecondaryIndexes(): void
    {
        $startedAt = microtime(true);

        $this->connection->getSchemaBuilder()->table($this->table, function (Blueprint $blueprint): void {
            $blueprint->index(['viewable_type', 'viewable_id'], 'views_viewable_type_viewable_id_index');
            $blueprint->index(['viewable_type', 'viewable_id', 'viewed_at'], 'views_viewable_viewed_at_index');
        });

        Output::line(sprintf('Built the indexes in %s.', Output::elapsed($startedAt)));
    }

    /**
     * Refresh the planner statistics, so the query plans the benchmarks and
     * `make bench-explain` get are the ones a production table would get.
     * Postgres also needs a vacuum to set the visibility map, without which
     * it never chooses an index-only scan over a freshly loaded table.
     */
    private function analyze(): void
    {
        $startedAt = microtime(true);

        match ($this->connection->getDriverName()) {
            'sqlite' => $this->connection->statement("analyze {$this->table}"),
            'pgsql' => $this->connection->statement("vacuum analyze {$this->table}"),
            'mysql', 'mariadb' => $this->connection->statement("analyze table {$this->table}"),
            default => null,
        };

        Output::line(sprintf('Refreshed the statistics in %s.', Output::elapsed($startedAt)));
    }

    private function describe(DatasetSize $size, int $seed): Dataset
    {
        $views = $this->connection->table($this->table);

        $byArticle = $this->connection->table($this->table)
            ->selectRaw('viewable_id, count(*) as views')
            ->where('viewable_type', Article::class)
            ->groupBy('viewable_id');

        /** @var object{viewable_id: int|string, views: int|string} $hot */
        $hot = $byArticle->clone()->orderByDesc('views')->orderBy('viewable_id')->first();

        /** @var object{viewable_id: int|string, views: int|string} $cold */
        $cold = $byArticle->clone()->orderBy('views')->orderByDesc('viewable_id')->first();

        return new Dataset(
            size: $size,
            seed: $seed,
            articles: $size->articles(),
            videos: $size->videos(),
            views: (int) $views->clone()->count(),
            maxViewId: (int) $views->clone()->max('id'),
            hotArticleId: (int) $hot->viewable_id,
            hotArticleViews: (int) $hot->views,
            coldArticleId: (int) $cold->viewable_id,
            coldArticleViews: (int) $cold->views,
            indexes: [],
            seededAt: gmdate(self::LabelFormat),
        );
    }

    private function anchorTimestamp(): int
    {
        return Dataset::anchor()->getTimestamp();
    }

    /**
     * SQLite flushes to disk on every commit by default, and the journal
     * doubles the writes. Neither matters for data that can be seeded again.
     */
    private function speedUpSqlite(): void
    {
        if ($this->connection->getDriverName() !== 'sqlite') {
            return;
        }

        $this->connection->statement('pragma journal_mode = wal');
        $this->connection->statement('pragma synchronous = off');
    }

    private function restoreSqlite(): void
    {
        if ($this->connection->getDriverName() !== 'sqlite') {
            return;
        }

        $this->connection->statement('pragma synchronous = full');
    }
}
