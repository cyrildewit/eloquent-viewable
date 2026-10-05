<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Support\OptionalIndex;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

/**
 * Describes the seeded dataset the benchmarks run against. The seeder writes
 * it to the `benchmark_dataset` table next to the data, and every benchmark
 * reads it back, so a run always knows which article is hot, where the data
 * ends and which optional indexes are in place.
 */
final readonly class Dataset
{
    public const string Table = 'benchmark_dataset';

    /**
     * Bump when the seeder changes what it generates. A dataset seeded by an
     * older seeder is then refused instead of silently compared against runs
     * on a newer one.
     */
    public const int SchemaVersion = 2;

    /**
     * Every view is recorded before this moment. The benchmarks build their
     * periods relative to it, so "the past 30 days" means the same rows on
     * every run no matter when it happens.
     */
    public const string Anchor = '2026-01-01 00:00:00';

    /**
     * @param  list<OptionalIndex>  $indexes
     */
    public function __construct(
        public DatasetSize $size,
        public int $seed,
        public int $articles,
        public int $videos,
        public int $views,
        public int $maxViewId,
        public int $hotArticleId,
        public int $hotArticleViews,
        public int $coldArticleId,
        public int $coldArticleViews,
        public array $indexes,
        public string $seededAt,
    ) {}

    /**
     * @throws RuntimeException when nothing was seeded, or by an older seeder
     */
    public static function load(ConnectionInterface $connection): self
    {
        if (! $connection->getSchemaBuilder()->hasTable(self::Table)) {
            throw new RuntimeException(
                'No benchmark dataset found on the ['.$connection->getDriverName().'] connection. Seed one first with `make bench-seed`.'
            );
        }

        /** @var array<string, string> $attributes */
        $attributes = $connection->table(self::Table)->pluck('value', 'key')->all();

        if ((int) ($attributes['schema_version'] ?? 0) !== self::SchemaVersion) {
            throw new RuntimeException(
                'The benchmark dataset was seeded by an older seeder. Seed it again with `make bench-seed`.'
            );
        }

        return new self(
            size: DatasetSize::from($attributes['size']),
            seed: (int) $attributes['seed'],
            articles: (int) $attributes['articles'],
            videos: (int) $attributes['videos'],
            views: (int) $attributes['views'],
            maxViewId: (int) $attributes['max_view_id'],
            hotArticleId: (int) $attributes['hot_article_id'],
            hotArticleViews: (int) $attributes['hot_article_views'],
            coldArticleId: (int) $attributes['cold_article_id'],
            coldArticleViews: (int) $attributes['cold_article_views'],
            indexes: OptionalIndex::fromList($attributes['indexes']),
            seededAt: $attributes['seeded_at'],
        );
    }

    public function save(ConnectionInterface $connection): void
    {
        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable(self::Table)) {
            $schema->create(self::Table, function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->text('value');
            });
        }

        $connection->table(self::Table)->delete();

        $rows = [];

        foreach ($this->toAttributes() as $key => $value) {
            $rows[] = ['key' => $key, 'value' => $value];
        }

        $connection->table(self::Table)->insert($rows);
    }

    /**
     * @param  list<OptionalIndex>  $indexes
     */
    public function withIndexes(array $indexes): self
    {
        return new self(
            $this->size,
            $this->seed,
            $this->articles,
            $this->videos,
            $this->views,
            $this->maxViewId,
            $this->hotArticleId,
            $this->hotArticleViews,
            $this->coldArticleId,
            $this->coldArticleViews,
            $indexes,
            $this->seededAt,
        );
    }

    public static function anchor(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', self::Anchor, 'UTC');
    }

    /**
     * The period of the given number of days ending at the anchor.
     */
    public function pastDays(int $days): Period
    {
        return Period::create(self::anchor()->subDays($days), self::anchor());
    }

    /**
     * The article with the most views.
     */
    public function hotArticle(): Article
    {
        return Article::query()->findOrFail($this->hotArticleId);
    }

    /**
     * The article with the fewest views that still has some.
     */
    public function coldArticle(): Article
    {
        return Article::query()->findOrFail($this->coldArticleId);
    }

    /**
     * A one-line summary for the console.
     */
    public function describe(): string
    {
        $indexes = $this->indexes === [] ? 'none' : OptionalIndex::toList($this->indexes);

        return sprintf(
            '%s dataset, seed %d: %s views over %s articles and %s videos, hot article #%d has %s views, cold article #%d has %s, optional indexes: %s',
            $this->size->value,
            $this->seed,
            number_format($this->views),
            number_format($this->articles),
            number_format($this->videos),
            $this->hotArticleId,
            number_format($this->hotArticleViews),
            $this->coldArticleId,
            number_format($this->coldArticleViews),
            $indexes,
        );
    }

    /**
     * The description with its values typed, the shape `bench:describe` prints. The results repository reads it, so
     * a key is only ever added, never renamed or removed.
     *
     * @return array<string, int|string|list<string>>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SchemaVersion,
            'size' => $this->size->value,
            'seed' => $this->seed,
            'anchor' => self::Anchor,
            'articles' => $this->articles,
            'videos' => $this->videos,
            'views' => $this->views,
            'max_view_id' => $this->maxViewId,
            'hot_article_id' => $this->hotArticleId,
            'hot_article_views' => $this->hotArticleViews,
            'cold_article_id' => $this->coldArticleId,
            'cold_article_views' => $this->coldArticleViews,
            'indexes' => array_map(static fn (OptionalIndex $index): string => $index->value, $this->indexes),
            'seeded_at' => $this->seededAt,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function toAttributes(): array
    {
        return array_map(
            static fn (int|string|array $value): string => is_array($value) ? implode(',', $value) : (string) $value,
            $this->toArray(),
        );
    }
}
