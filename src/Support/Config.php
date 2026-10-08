<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * Typed access to the keys of the package config file.
 *
 * Defaults live in `config/eloquent-viewable.php` and are merged in by the
 * service provider, so they are not repeated here. A key that has to hold a
 * value throws when it is missing or empty, because there is no fallback that
 * would be correct; a boolean or list key falls back to the shipped default,
 * because there is.
 *
 * @internal
 */
final readonly class Config
{
    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @return class-string<View>
     *
     * @throws InvalidConfiguration
     */
    public function viewModel(): string
    {
        $value = $this->get('models.view.class', View::class);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeViewModel('models.view.class', $value);
        }

        if (! is_a($value, View::class, true)) {
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
        $value = $this->get('recording.guards');

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $value);
        }

        foreach ($value as $guard) {
            if (! is_string($guard)) {
                throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $guard);
            }

            if (! class_exists($guard)) {
                throw InvalidConfiguration::mustBeListOfClasses('recording.guards', $guard);
            }
        }

        /** @var list<class-string> */
        return array_values($value);
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
        return (bool) $this->get('recording.queue.enabled', false);
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
        return (bool) $this->get('recording.viewer.enabled', false);
    }

    /** @throws InvalidConfiguration */
    public function viewerGuard(): ?string
    {
        return $this->string('recording.viewer.guard');
    }

    public function beaconEnabled(): bool
    {
        return (bool) $this->get('recording.beacon.enabled', false);
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
     * Each dimension by name, with the class and the options it is built with.
     *
     * @return array<string, array{class: class-string, options: array<string, mixed>}>
     *
     * @throws InvalidConfiguration
     */
    public function dimensions(): array
    {
        $value = $this->get('dimensions.definitions', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeDimensions('dimensions.definitions', $value);
        }

        $dimensions = [];

        foreach ($value as $name => $entry) {
            if (! is_string($name)) {
                throw InvalidConfiguration::mustBeDimensions('dimensions.definitions', $name);
            }

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
        $value = $this->get('dimensions.sources', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $value);
        }

        $sources = [];

        foreach ($value as $host => $source) {
            if (! is_string($host)) {
                throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
            }

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
        $value = $this->get('dimensions.source_aliases', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustMapStrings('dimensions.source_aliases', $value);
        }

        $aliases = [];

        foreach ($value as $alias => $name) {
            if (! is_string($alias)) {
                throw InvalidConfiguration::mustMapStrings('dimensions.source_aliases', $alias);
            }

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
        $value = $this->get('querying.recommendations.similarity', 'cosine');

        if (! in_array($value, ['cosine', 'count'], true)) {
            throw InvalidConfiguration::mustBeOneOf('querying.recommendations.similarity', ['cosine', 'count'], $value);
        }

        return $value;
    }

    public function pairsEnabled(): bool
    {
        return (bool) $this->get('querying.pairs.enabled', false);
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
        $value = $this->get('visitor.identity', 'cookie');

        if (! in_array($value, ['cookie', 'viewer', 'fingerprint'], true)) {
            throw InvalidConfiguration::mustBeOneOf('visitor.identity', ['cookie', 'viewer', 'fingerprint'], $value);
        }

        return $value;
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
        $value = $this->get('querying.counters', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $value);
        }

        $counters = [];

        foreach ($value as $class => $columns) {
            $model = $this->counterModel($class);

            if (! is_array($columns) || $columns === []) {
                throw InvalidConfiguration::mustBeCounters('querying.counters', $class);
            }

            $counters[$model] = $this->counterColumns($columns);
        }

        return $counters;
    }

    /**
     * It returns the counter columns that hold a hot score instead of a
     * count, by class and column. Their count is the one the other options of
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
     * Every column is a counter column without a period or a hot score.
     *
     * @return array<class-string<Model&Viewable>, array<string, non-empty-list<int>>>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function milestones(): array
    {
        $value = $this->get('milestones.thresholds', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $value);
        }

        $counters = $value === [] ? [] : $this->counters();
        $hotScores = $value === [] ? [] : $this->hotScores();
        $milestones = [];

        foreach ($value as $class => $columns) {
            if (! is_string($class)) {
                throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $class);
            }

            if (! is_array($columns)) {
                throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $class);
            }

            if ($columns === []) {
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
        $value = $this->get('spikes.types', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeSpikes('spikes.types', $value);
        }

        $spikes = [];

        foreach ($value as $class => $options) {
            if (! is_string($class)) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            if (! is_a($class, Model::class, true)) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            if (! is_a($class, Viewable::class, true)) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            if (! is_array($options)) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            if (array_diff(array_keys($options), ['window', 'seasonality', 'samples', 'threshold', 'minimum', 'drops', 'cooldown']) !== []) {
                throw InvalidConfiguration::mustBeSpikes('spikes.types', $class);
            }

            /** @var array<string, mixed> $options */
            $spikes[$class] = $options;
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
        $allowed = ['hour', 'day', 'month', 'year'];
        $value = $this->get('retention.rollups.tiers', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeTiers('retention.rollups.tiers', $value);
        }

        $tiers = [];

        foreach ($value as $tier => $keep) {
            if (! in_array($tier, $allowed, true)) {
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
        $value = $this->get('retention.rollups.custom', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeListOfClasses('retention.rollups.custom', $value);
        }

        foreach ($value as $class) {
            if (! is_string($class)) {
                throw InvalidConfiguration::mustBeListOfClasses('retention.rollups.custom', $class);
            }

            if (! class_exists($class)) {
                throw InvalidConfiguration::mustBeListOfClasses('retention.rollups.custom', $class);
            }
        }

        /** @var list<class-string> */
        return array_values($value);
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
        return (bool) $this->get('retention.rollups.strict', false);
    }

    /**
     * @return list<class-string>
     *
     * @throws InvalidConfiguration
     */
    public function doctorChecks(): array
    {
        $value = $this->get('doctor.checks', []);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeListOfClasses('doctor.checks', $value);
        }

        foreach ($value as $check) {
            if (! is_string($check)) {
                throw InvalidConfiguration::mustBeListOfClasses('doctor.checks', $check);
            }

            if (! class_exists($check)) {
                throw InvalidConfiguration::mustBeListOfClasses('doctor.checks', $check);
            }
        }

        /** @var list<class-string> */
        return array_values($value);
    }

    public function sampleEnabled(): bool
    {
        return (bool) $this->get('doctor.sample.enabled', false);
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

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidConfiguration::mustBeShare('doctor.sample.crawler_share', $value);
        }

        if ($value <= 0) {
            throw InvalidConfiguration::mustBeShare('doctor.sample.crawler_share', $value);
        }

        if ($value > 1) {
            throw InvalidConfiguration::mustBeShare('doctor.sample.crawler_share', $value);
        }

        return (float) $value;
    }

    public function presenceEnabled(): bool
    {
        return (bool) $this->get('presence.enabled', false);
    }

    /** @throws InvalidConfiguration */
    public function presenceDriver(): string
    {
        return $this->nonEmptyString('presence.driver');
    }

    /** @throws InvalidConfiguration */
    public function presenceWindow(): int
    {
        return $this->positiveInteger('presence.window');
    }

    /**
     * @return 'exact'|'approximate'
     *
     * @throws InvalidConfiguration
     */
    public function presencePrecision(): string
    {
        $value = $this->get('presence.precision', 'exact');

        if (! in_array($value, ['exact', 'approximate'], true)) {
            throw InvalidConfiguration::mustBeOneOf('presence.precision', ['exact', 'approximate'], $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    public function presenceHeartbeat(): int
    {
        return $this->positiveInteger('presence.heartbeat');
    }

    public function presenceExposesCount(): bool
    {
        return (bool) $this->get('presence.expose_count', false);
    }

    public function presenceTracksViewers(): bool
    {
        return (bool) $this->get('presence.viewers', false);
    }

    /** @throws InvalidConfiguration */
    public function presenceMaxCandidates(): int
    {
        return $this->positiveInteger('presence.max_candidates');
    }

    /** @throws InvalidConfiguration */
    public function presenceRedisConnection(): ?string
    {
        return $this->string('presence.redis.connection');
    }

    /** @throws InvalidConfiguration */
    public function presenceRedisPrefix(): string
    {
        return $this->nonEmptyString('presence.redis.prefix');
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get("eloquent-viewable.{$key}", $default);
    }

    /**
     * A single string is read as a list of one.
     *
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    private function strings(string $key): array
    {
        $value = (array) $this->get($key, []);

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidConfiguration::mustBeListOfStrings($key, $item);
            }
        }

        /** @var list<string> */
        return array_values($value);
    }

    /** @throws InvalidConfiguration */
    private function string(string $key): ?string
    {
        $value = $this->get($key);

        if ($value !== null && ! is_string($value)) {
            throw InvalidConfiguration::mustBeStringOrNull($key, $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    private function nonEmptyString(string $key): string
    {
        $value = $this->get($key);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        if ($value === '') {
            throw InvalidConfiguration::mustBeNonEmptyString($key, $value);
        }

        return $value;
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

        if (! is_string($entry)) {
            throw InvalidConfiguration::invalidDimension($name, 'must name a class, alone or first in an array of options');
        }

        if (! class_exists($entry)) {
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
        if (! is_array($source)) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        if (array_keys($source) !== [0, 1]) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        $name = $source[0] ?? null;
        $medium = $source[1] ?? null;

        if (! is_string($name)) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        if (! is_string($medium)) {
            throw InvalidConfiguration::mustBeSourceList('dimensions.sources', $host);
        }

        return [$name, $medium];
    }

    /**
     * @return class-string<Model&Viewable>
     *
     * @throws InvalidConfiguration
     */
    private function counterModel(mixed $class): string
    {
        if (! is_string($class)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $class);
        }

        if (! is_a($class, Model::class, true)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $class);
        }

        if (! is_a($class, Viewable::class, true)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $class);
        }

        return $class;
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
        if (! is_array($options)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        if (array_diff(array_keys($options), ['unique', 'period', 'collection', 'hot', 'dimensions']) !== []) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        $period = $options['period'] ?? null;
        $collection = $options['collection'] ?? null;

        if ($period !== null && ! is_string($period)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        if ($collection !== null && ! is_string($collection)) {
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
     * Config cannot tell where a dimension is kept, so each filter points at
     * the column named after it until the registry resolves it before the
     * count.
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

        if (! is_array($values)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        if (! array_is_list($values)) {
            throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
        }

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw InvalidConfiguration::mustBeCounters('querying.counters', $column);
            }
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
        if (! is_array($thresholds)) {
            throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $thresholds);
        }

        if (! array_is_list($thresholds)) {
            throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $thresholds);
        }

        if ($thresholds === []) {
            throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $thresholds);
        }

        $previous = 0;

        foreach ($thresholds as $threshold) {
            if (! is_int($threshold)) {
                throw InvalidConfiguration::mustBeMilestones('milestones.thresholds', $threshold);
            }

            if ($threshold <= $previous) {
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

        if (! is_array($hot)) {
            throw InvalidConfiguration::mustBeHotScore($column, $hot);
        }

        if (array_diff(array_keys($hot), ['from', 'every']) !== []) {
            throw InvalidConfiguration::mustBeHotScore($column, $hot);
        }

        $from = $hot['from'] ?? 'created_at';
        $every = $hot['every'] ?? '12h';
        $duration = is_string($every) ? Duration::tryParse($every) : null;

        if (! is_string($from)) {
            throw InvalidConfiguration::mustBeHotScore($column, $from);
        }

        if ($from === '') {
            throw InvalidConfiguration::mustBeHotScore($column, $from);
        }

        if (! $duration instanceof Duration) {
            throw InvalidConfiguration::mustBeHotScore($column, $every);
        }

        return new HotScore($from, $duration);
    }

    /** @throws InvalidConfiguration */
    private function duration(string $key): ?Duration
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeDuration($key, $value);
        }

        return Duration::tryParse($value) ?? throw InvalidConfiguration::mustBeDuration($key, $value);
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $default
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    private function subsetOf(string $key, array $allowed, array $default): array
    {
        $value = $this->get($key, $default);

        if (! is_array($value)) {
            throw InvalidConfiguration::mustBeSubsetOf($key, $allowed, $value);
        }

        if ($value === []) {
            throw InvalidConfiguration::mustBeSubsetOf($key, $allowed, $value);
        }

        foreach ($value as $item) {
            if (! in_array($item, $allowed, true)) {
                throw InvalidConfiguration::mustBeSubsetOf($key, $allowed, $item);
            }
        }

        return array_values(array_intersect($allowed, $value));
    }

    /** @throws InvalidConfiguration */
    private function requiredDuration(string $key): Duration
    {
        $value = $this->get($key);

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeDuration($key, $value, nullable: false);
        }

        return Duration::tryParse($value) ?? throw InvalidConfiguration::mustBeDuration($key, $value, nullable: false);
    }

    /** @throws InvalidConfiguration */
    private function positiveIntegerOrNull(string $key): ?int
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw InvalidConfiguration::mustBePositiveIntegerOrNull($key, $value);
        }

        if ($integer < 1) {
            throw InvalidConfiguration::mustBePositiveIntegerOrNull($key, $value);
        }

        return $integer;
    }

    /** @throws InvalidConfiguration */
    private function positiveInteger(string $key): int
    {
        $value = $this->get($key);
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        if ($integer < 1) {
            throw InvalidConfiguration::mustBePositiveInteger($key, $value);
        }

        return $integer;
    }
}
