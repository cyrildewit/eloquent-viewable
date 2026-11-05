<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Typed access to the keys of the package config file that no module reads
 * through a config of its own.
 *
 * @internal
 */
final readonly class Config extends ConfigReader
{
    /**
     * @return class-string<View>
     *
     * @throws InvalidConfiguration
     */
    public function viewModel(): string
    {
        $value = $this->get('models.view.class', View::class);

        if (! is_string($value) || ! is_a($value, View::class, true)) {
            throw InvalidConfiguration::mustBeViewModel('models.view.class', $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    public function viewTable(): ?string
    {
        return $this->string('models.view.table_name');
    }

    /** @throws InvalidConfiguration */
    public function viewConnection(): ?string
    {
        return $this->string('models.view.connection');
    }

    /** @throws InvalidConfiguration */
    public function storeDriver(): string
    {
        return $this->nonEmptyString('recording.store.driver');
    }

    /** @throws InvalidConfiguration */
    public function redisConnection(): ?string
    {
        return $this->string('recording.store.redis.connection');
    }

    /** @throws InvalidConfiguration */
    public function redisStream(): string
    {
        return $this->nonEmptyString('recording.store.redis.stream');
    }

    /** @throws InvalidConfiguration */
    public function redisGroup(): string
    {
        return $this->nonEmptyString('recording.store.redis.group');
    }

    /** @throws InvalidConfiguration */
    public function redisLandingDriver(): string
    {
        return $this->nonEmptyString('recording.store.redis.landing');
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    public function guards(): array
    {
        return $this->classes('recording.guards');
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    public function ignoredIpAddresses(): array
    {
        return $this->strings('recording.ignored_ip_addresses');
    }

    /** @throws InvalidConfiguration */
    public function throttleMaxPerMinute(): int
    {
        return $this->positiveInteger('recording.throttle.max_per_minute');
    }

    /** @throws InvalidConfiguration */
    public function throttleKey(): string
    {
        return $this->nonEmptyString('recording.throttle.key');
    }

    /** @throws InvalidConfiguration */
    public function throttleCacheStore(): ?string
    {
        return $this->string('recording.throttle.store');
    }

    /** @throws InvalidConfiguration */
    public function burstMax(): int
    {
        return $this->positiveInteger('recording.bursts.max');
    }

    /** @throws InvalidConfiguration */
    public function burstSeconds(): int
    {
        return $this->positiveInteger('recording.bursts.seconds');
    }

    /** @throws InvalidConfiguration */
    public function burstBlockFor(): int
    {
        return $this->positiveInteger('recording.bursts.block_for');
    }

    /**
     * @return list<'visitor'|'network'>
     *
     * @throws InvalidConfiguration
     */
    public function burstKeys(): array
    {
        /** @var list<'visitor'|'network'> */
        return $this->subsetOf('recording.bursts.by', ['visitor', 'network'], ['visitor', 'network']);
    }

    /** @throws InvalidConfiguration */
    public function burstKey(): string
    {
        return $this->nonEmptyString('recording.bursts.key');
    }

    /** @throws InvalidConfiguration */
    public function burstCacheStore(): ?string
    {
        return $this->string('recording.bursts.store');
    }

    public function queueEnabled(): bool
    {
        return $this->bool('recording.queue.enabled');
    }

    /** @throws InvalidConfiguration */
    public function queueConnection(): ?string
    {
        return $this->string('recording.queue.connection');
    }

    /** @throws InvalidConfiguration */
    public function queueName(): ?string
    {
        return $this->string('recording.queue.queue');
    }

    public function viewerEnabled(): bool
    {
        return $this->bool('recording.viewer.enabled');
    }

    /** @throws InvalidConfiguration */
    public function viewerGuard(): ?string
    {
        return $this->string('recording.viewer.guard');
    }

    public function beaconEnabled(): bool
    {
        return $this->bool('recording.beacon.enabled');
    }

    /** @throws InvalidConfiguration */
    public function beaconPrefix(): string
    {
        return $this->nonEmptyString('recording.beacon.prefix');
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    public function beaconMiddleware(): array
    {
        return $this->strings('recording.beacon.middleware');
    }

    /**
     * @return array<string, array{class: class-string, options: array<string, mixed>}>
     *
     * @throws InvalidConfiguration
     */
    public function dimensions(): array
    {
        $dimensions = [];

        foreach ($this->map('dimensions.definitions', InvalidConfiguration::mustBeDimensions(...)) as $name => $entry) {
            $dimensions[$name] = $this->dimension($name, $entry);
        }

        return $dimensions;
    }

    /**
     * The `app.url` of the application, outside the package config.
     */
    public function applicationUrl(): ?string
    {
        $value = $this->config->get('app.url');

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    public function internalHosts(): array
    {
        return $this->strings('dimensions.internal_hosts');
    }

    /**
     * @return array<string, array{string, string}>
     *
     * @throws InvalidConfiguration
     */
    public function sourceHosts(): array
    {
        $sources = [];

        foreach ($this->map('dimensions.sources', InvalidConfiguration::mustBeSourceList(...)) as $host => $source) {
            $sources[strtolower($host)] = $this->source($host, $source);
        }

        return $sources;
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidConfiguration
     */
    public function sourceAliases(): array
    {
        $aliases = [];

        foreach ($this->map('dimensions.source_aliases', InvalidConfiguration::mustMapStrings(...)) as $alias => $name) {
            if (! is_string($name)) {
                throw InvalidConfiguration::mustMapStrings('dimensions.source_aliases', $alias);
            }

            $aliases[strtolower($alias)] = $name;
        }

        return $aliases;
    }

    /** @throws InvalidConfiguration */
    public function sourceDriver(): string
    {
        return $this->nonEmptyString('querying.source.driver');
    }

    /** @throws InvalidConfiguration */
    public function cacheKey(): string
    {
        return $this->nonEmptyString('querying.cache.key');
    }

    /** @throws InvalidConfiguration */
    public function cacheStore(): ?string
    {
        return $this->string('querying.cache.store');
    }

    /** @throws InvalidConfiguration */
    public function maxIntervals(): int
    {
        return $this->positiveInteger('querying.max_intervals');
    }

    /** @throws InvalidConfiguration */
    public function alsoViewedMinimumVisitors(): int
    {
        return $this->positiveInteger('querying.also_viewed.minimum_visitors');
    }

    /** @throws InvalidConfiguration */
    public function alsoViewedMaxVisitors(): ?int
    {
        return $this->positiveIntegerOrNull('querying.also_viewed.max_visitors');
    }

    /** @throws InvalidConfiguration */
    public function recommendationsMaxSeeds(): int
    {
        return $this->positiveInteger('querying.recommendations.max_seeds');
    }

    /** @throws InvalidConfiguration */
    public function recommendationsMaxVisitors(): ?int
    {
        return $this->positiveIntegerOrNull('querying.recommendations.max_visitors');
    }

    /** @throws InvalidConfiguration */
    public function recommendationsHalfLife(): Duration
    {
        return $this->requiredDuration('querying.recommendations.half_life');
    }

    /**
     * @return 'cosine'|'count'
     *
     * @throws InvalidConfiguration
     */
    public function recommendationsSimilarity(): string
    {
        return $this->oneOf('querying.recommendations.similarity', ['cosine', 'count'], 'cosine');
    }

    public function pairsEnabled(): bool
    {
        return $this->bool('querying.pairs.enabled');
    }

    /** @throws InvalidConfiguration */
    public function pairsTable(): string
    {
        return $this->nonEmptyString('querying.pairs.table');
    }

    /** @throws InvalidConfiguration */
    public function pairsPeriod(): Duration
    {
        return $this->requiredDuration('querying.pairs.period');
    }

    /** @throws InvalidConfiguration */
    public function pairsMaxPairs(): int
    {
        return $this->positiveInteger('querying.pairs.max_pairs');
    }

    /**
     * The class of the trending curve, or null for exponential decay with
     * the configured half-life.
     *
     * @return ?class-string
     *
     * @throws InvalidConfiguration
     */
    public function trendingCurve(): ?string
    {
        $value = $this->get('querying.trending.curve');

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! class_exists($value)) {
            throw InvalidConfiguration::mustBeClassOrNull('querying.trending.curve', $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    public function trendingHalfLife(): Duration
    {
        return $this->requiredDuration('querying.trending.half_life');
    }

    /**
     * Null for `auto`, which weighs per hour while the window fits under
     * `max_steps` and per day otherwise.
     *
     * @throws InvalidConfiguration
     */
    public function trendingStep(): ?Granularity
    {
        $value = $this->get('querying.trending.step', 'auto');

        return match ($value) {
            'auto' => null,
            '1h' => Granularity::Hour,
            '1d' => Granularity::Day,
            default => throw InvalidConfiguration::mustBeOneOf('querying.trending.step', ['auto', '1h', '1d'], $value),
        };
    }

    /** @throws InvalidConfiguration */
    public function trendingMaxSteps(): int
    {
        return $this->positiveInteger('querying.trending.max_steps');
    }

    /** @throws InvalidConfiguration */
    public function visitorCookieName(): string
    {
        return $this->nonEmptyString('visitor.cookie.name');
    }

    /** @throws InvalidConfiguration */
    public function visitorCookieLifetime(): int
    {
        return $this->positiveInteger('visitor.cookie.lifetime');
    }

    /**
     * @return 'cookie'|'viewer'|'fingerprint'
     *
     * @throws InvalidConfiguration
     */
    public function visitorIdentity(): string
    {
        return $this->oneOf('visitor.identity', ['cookie', 'viewer', 'fingerprint'], 'cookie');
    }

    /** @throws InvalidConfiguration */
    public function fingerprintKey(): string
    {
        return $this->nonEmptyString('visitor.fingerprint.key');
    }

    /** @throws InvalidConfiguration */
    public function fingerprintCacheStore(): ?string
    {
        return $this->string('visitor.fingerprint.store');
    }

    /**
     * @return 'day'|'week'|'month'
     *
     * @throws InvalidConfiguration
     */
    public function fingerprintRotation(): string
    {
        return $this->oneOf('visitor.fingerprint.rotation', ['day', 'week', 'month'], 'day');
    }

    /** @throws InvalidConfiguration */
    public function cooldownStore(): string
    {
        return $this->nonEmptyString('cooldown.store');
    }

    /** @throws InvalidConfiguration */
    public function cooldownKey(): string
    {
        return $this->nonEmptyString('cooldown.key');
    }

    /** @throws InvalidConfiguration */
    public function cooldownCacheStore(): ?string
    {
        return $this->string('cooldown.cache.store');
    }

    /**
     * @return array<class-string<Model&Viewable>, array<string, ViewsQuery>>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function counters(): array
    {
        $counters = [];

        foreach ($this->map('querying.counters', InvalidConfiguration::mustBeCounters(...)) as $class => $columns) {
            $model = $this->viewableClass($class) ?? throw InvalidConfiguration::mustBeCounters('querying.counters', $class);

            if (! is_array($columns) || $columns === []) {
                throw InvalidConfiguration::mustBeCounters('querying.counters', $class);
            }

            $counters[$model] = $this->counterColumns($columns);
        }

        return $counters;
    }

    /**
     * It returns the counter columns with the `hot` option, by class and
     * column. The score is computed from the count that the other options of
     * the column describe.
     *
     * @return array<class-string<Model&Viewable>, array<string, HotScore>>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function hotScores(): array
    {
        $counters = $this->counters();

        /** @var array<string, array<int|string, mixed>> $value */
        $value = $this->get('querying.counters', []);
        $scores = [];

        foreach ($counters as $class => $columns) {
            foreach (array_keys($columns) as $column) {
                $options = $value[$class][$column] ?? null;
                $hot = is_array($options) ? $options['hot'] ?? null : null;

                if ($hot === null) {
                    continue;
                }

                $scores[$class][$column] = $this->hotScore($column, $hot);
            }
        }

        return $scores;
    }

    /** @throws InvalidConfiguration */
    public function milestonesTable(): string
    {
        return $this->nonEmptyString('milestones.table');
    }

    /**
     * It returns the thresholds of each counter column, in ascending order.
     * It throws when a column is not a counter column, or when the column
     * counts a period or holds a hot score.
     *
     * @return array<class-string<Model&Viewable>, array<string, non-empty-list<int>>>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function milestones(): array
    {
        $value = $this->map('milestones.thresholds', InvalidConfiguration::mustBeMilestones(...));
        $counters = $value === [] ? [] : $this->counters();
        $hotScores = $value === [] ? [] : $this->hotScores();
        $milestones = [];

        foreach ($value as $class => $columns) {
            if (! is_array($columns) || $columns === []) {
                throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $class);
            }

            foreach ($columns as $column => $thresholds) {
                $query = $counters[$class][$column] ?? null;

                if (! $query instanceof ViewsQuery) {
                    throw InvalidConfiguration::milestoneWithoutCounter($class, (string) $column);
                }

                if ($query->period instanceof Period) {
                    throw InvalidConfiguration::milestoneOnPeriod($class, (string) $column);
                }

                if (isset($hotScores[$class][$column])) {
                    throw InvalidConfiguration::milestoneOnHotScore($class, (string) $column);
                }

                /** @var class-string<Model&Viewable> $class */
                $milestones[$class][(string) $column] = $this->thresholds($thresholds);
            }
        }

        return $milestones;
    }

    /** @throws InvalidConfiguration */
    public function spikesTable(): string
    {
        return $this->nonEmptyString('spikes.table');
    }

    /**
     * It returns the options of each model class to watch for spikes, as
     * given. `Spikes\SpikeSettings` reads them.
     *
     * @return array<class-string<Model&Viewable>, array<string, mixed>>
     *
     * @throws InvalidConfiguration
     */
    public function spikes(): array
    {
        $spikes = [];

        foreach ($this->map('spikes.types', InvalidConfiguration::mustBeSpikes(...)) as $class => $options) {
            $model = $this->viewableClass($class) ?? throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);

            if (! is_array($options) || ! $this->optionsAmong($options, ['window', 'seasonality', 'samples', 'threshold', 'minimum', 'drops', 'cooldown'])) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            /** @var array<string, mixed> $options */
            $spikes[$model] = $options;
        }

        return $spikes;
    }

    /** @throws InvalidConfiguration */
    public function anonymiseAfter(): ?Duration
    {
        return $this->duration('retention.anonymise.after');
    }

    /**
     * @return list<'visitor'|'viewer'|'context'|'dimensions'>
     *
     * @throws InvalidConfiguration
     */
    public function anonymiseColumns(): array
    {
        $columns = ['visitor', 'viewer', 'context', 'dimensions'];

        /** @var list<'visitor'|'viewer'|'context'|'dimensions'> */
        return $this->subsetOf('retention.anonymise.columns', $columns, $columns);
    }

    /** @throws InvalidConfiguration */
    public function pruneAfter(): ?Duration
    {
        return $this->duration('retention.prune.after');
    }

    /** @throws InvalidConfiguration */
    public function retentionChunk(): int
    {
        return $this->positiveInteger('retention.chunk');
    }

    /** @throws InvalidConfiguration */
    public function rollupTable(): string
    {
        return $this->nonEmptyString('retention.rollups.table');
    }

    /** @throws InvalidConfiguration */
    public function rollupTimezone(): ?string
    {
        return $this->string('retention.rollups.timezone');
    }

    /** @throws InvalidConfiguration */
    public function rollupSettle(): ?Duration
    {
        return $this->duration('retention.rollups.settle');
    }

    /**
     * @return array<'hour'|'day'|'month'|'year', ?Duration>
     *
     * @throws InvalidConfiguration
     */
    public function rollupTiers(): array
    {
        $tiers = [];

        foreach (array_keys($this->map('retention.rollups.tiers', InvalidConfiguration::mustBeTiers(...))) as $tier) {
            if (! in_array($tier, ['hour', 'day', 'month', 'year'], true)) {
                throw InvalidConfiguration::mustBeTiers('retention.rollups.tiers', $tier);
            }

            $tiers[$tier] = $this->duration("retention.rollups.tiers.{$tier}");
        }

        return $tiers;
    }

    /**
     * @return list<'viewable'|'viewable_collection'|'type'|'type_collection'>
     *
     * @throws InvalidConfiguration
     */
    public function rollupGroupings(): array
    {
        /** @var list<'viewable'|'viewable_collection'|'type'|'type_collection'> */
        return $this->subsetOf(
            'retention.rollups.groupings',
            ['viewable', 'viewable_collection', 'type', 'type_collection'],
            ['viewable', 'viewable_collection', 'type'],
        );
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    public function customRollups(): array
    {
        return $this->classes('retention.rollups.custom', []);
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    public function rollupDimensions(): array
    {
        return $this->strings('retention.rollups.dimensions');
    }

    public function rollupsStrict(): bool
    {
        return $this->bool('retention.rollups.strict');
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    public function doctorChecks(): array
    {
        return $this->classes('doctor.checks', []);
    }

    public function sampleEnabled(): bool
    {
        return $this->bool('doctor.sample.enabled');
    }

    /** @throws InvalidConfiguration */
    public function sampleCacheStore(): ?string
    {
        return $this->string('doctor.sample.store');
    }

    /** @throws InvalidConfiguration */
    public function sampleKey(): string
    {
        return $this->nonEmptyString('doctor.sample.key');
    }

    /** @throws InvalidConfiguration */
    public function sampleCrawlerShare(): float
    {
        $value = $this->get('doctor.sample.crawler_share');

        if ((! is_int($value) && ! is_float($value)) || $value <= 0 || $value > 1) {
            throw InvalidConfiguration::mustBeShare('doctor.sample.crawler_share', $value);
        }

        return (float) $value;
    }

    /**
     * A class name alone, or an array of the class name and options by name.
     *
     * @return array{class: class-string, options: array<string, mixed>}
     *
     * @throws InvalidConfiguration
     */
    private function dimension(string $name, mixed $entry): array
    {
        $options = [];

        if (is_array($entry)) {
            $options = $entry;
            $entry = $options[0] ?? null;

            unset($options[0]);
        }

        if (! is_string($entry) || ! class_exists($entry)) {
            throw InvalidConfiguration::invalidDimension($name, 'must name a class, alone or first in an array of options');
        }

        foreach (array_keys($options) as $option) {
            if (! is_string($option)) {
                throw InvalidConfiguration::invalidDimension($name, 'must give every option after the class a name');
            }
        }

        /** @var array<string, mixed> $options */
        return ['class' => $entry, 'options' => $options];
    }

    /**
     * @return array{string, string}
     *
     * @throws InvalidConfiguration
     */
    private function source(string $host, mixed $source): array
    {
        if (! is_array($source) || array_keys($source) !== [0, 1]) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        $name = $source[0] ?? null;
        $medium = $source[1] ?? null;

        if (! is_string($name) || ! is_string($medium)) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        return [$name, $medium];
    }

    /**
     * @param  array<mixed>  $columns
     * @return array<string, ViewsQuery>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    private function counterColumns(array $columns): array
    {
        $counters = [];

        foreach ($columns as $column => $options) {
            if (is_int($column) && is_string($options)) {
                [$column, $options] = [$options, []];
            }

            if (! is_string($column) || $column === '') {
                throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
            }

            $counters[$column] = $this->counterQuery($column, $options);
        }

        return $counters;
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    private function counterQuery(string $column, mixed $options): ViewsQuery
    {
        if (! is_array($options) || ! $this->optionsAmong($options, ['unique', 'period', 'collection', 'hot', 'dimensions'])) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        $period = $options['period'] ?? null;
        $collection = $options['collection'] ?? null;

        if (($period !== null && ! is_string($period)) || ($collection !== null && ! is_string($collection))) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        return new ViewsQuery(
            $period === null ? null : Period::parse($period),
            $collection,
            (bool) ($options['unique'] ?? false),
            dimensions: $this->counterDimensions($column, $options['dimensions'] ?? []),
        );
    }

    /**
     * The config does not know where a dimension is stored. Each filter
     * targets the column with the dimension's name, and the dimension
     * registry replaces that target before the count runs.
     *
     * @return list<DimensionFilter>
     *
     * @throws InvalidConfiguration
     */
    private function counterDimensions(string $column, mixed $dimensions): array
    {
        if (! is_array($dimensions)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        $filters = [];

        foreach ($dimensions as $name => $values) {
            if (! is_string($name)) {
                throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
            }

            $filters[] = new DimensionFilter($name, $name, $this->dimensionValues($column, $values));
        }

        return $filters;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    private function dimensionValues(string $column, mixed $values): array
    {
        if (is_string($values)) {
            return [$values];
        }

        if (! is_array($values) || ! array_is_list($values) || array_any($values, static fn (mixed $value): bool => ! is_string($value))) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * @return non-empty-list<int>
     *
     * @throws InvalidConfiguration
     */
    private function thresholds(mixed $thresholds): array
    {
        if (! is_array($thresholds) || ! array_is_list($thresholds) || $thresholds === []) {
            throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $thresholds);
        }

        $previous = 0;

        foreach ($thresholds as $threshold) {
            if (! is_int($threshold) || $threshold <= $previous) {
                throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $threshold);
            }

            $previous = $threshold;
        }

        return $thresholds;
    }

    /**
     * The option is `true` for the defaults, the name of the timestamp
     * column, or `from` and `every` options.
     *
     * @throws InvalidConfiguration
     */
    private function hotScore(string $column, mixed $hot): HotScore
    {
        if ($hot === true) {
            $hot = [];
        }

        if (is_string($hot)) {
            $hot = ['from' => $hot];
        }

        if (! is_array($hot) || ! $this->optionsAmong($hot, ['from', 'every'])) {
            throw InvalidConfiguration::mustBeHotScore($column, $hot);
        }

        $from = $hot['from'] ?? 'created_at';
        $every = $hot['every'] ?? '12h';
        $duration = is_string($every) ? Duration::tryParse($every) : null;

        if (! is_string($from) || $from === '') {
            throw InvalidConfiguration::mustBeHotScore($column, $from);
        }

        if (! $duration instanceof Duration) {
            throw InvalidConfiguration::mustBeHotScore($column, $every);
        }

        return new HotScore($from, $duration);
    }
}
