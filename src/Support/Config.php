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
        $value = $this->get('querying.also_viewed.max_visitors');

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw InvalidConfiguration::mustBePositiveIntegerOrNull('querying.also_viewed.max_visitors', $value);
        }

        return $integer;
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
        $value = $this->get('querying.trending.half_life');

        if (! is_string($value)) {
            throw InvalidConfiguration::mustBeDuration('querying.trending.half_life', $value, nullable: false);
        }

        return Duration::tryParse($value) ?? throw InvalidConfiguration::mustBeDuration('querying.trending.half_life', $value, nullable: false);
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

    /** @throws InvalidConfiguration */
    public function anonymiseAfter(): ?Duration
    {
        return $this->duration('retention.anonymise.after');
    }

    /**
     * @return list<'visitor'|'viewer'|'context'>
     *
     * @throws InvalidConfiguration
     */
    public function anonymiseColumns(): array
    {
        $columns = ['visitor', 'viewer', 'context'];

        /** @var list<'visitor'|'viewer'|'context'> */
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

    public function rollupsStrict(): bool
    {
        return (bool) $this->get('retention.rollups.strict', false);
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

        if (array_diff(array_keys($options), ['unique', 'period', 'collection']) !== []) {
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
        );
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
