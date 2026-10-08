<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\PeriodInterval;
use Illuminate\Database\Eloquent\Model;

/**
 * The settings `views:detect-spikes` watches one model class with. Every
 * option of `spikes.types` that is left out takes its default.
 *
 * @internal
 */
final readonly class SpikeSettings
{
    /**
     * @param  class-string<Model&Viewable>  $class
     * @param  int  $hours  how wide the window is
     * @param  Seasonality  $seasonality  what the window is compared with
     * @param  int  $samples  how many past windows it is compared with
     * @param  float  $threshold  the z-score a spike starts at, and minus it a drop
     * @param  int  $minimum  the views below which nothing counts
     * @param  bool  $drops  whether drops are watched too
     * @param  Duration  $cooldown  how long a model stays normal before it settles
     */
    public function __construct(
        public string $class,
        public int $hours,
        public Seasonality $seasonality,
        public int $samples,
        public float $threshold,
        public int $minimum,
        public bool $drops,
        public Duration $cooldown,
    ) {}

    /**
     * @return list<self>
     *
     * @throws InvalidConfiguration
     */
    public static function fromConfig(Config $config): array
    {
        $settings = [];

        foreach ($config->spikes() as $class => $options) {
            $settings[] = self::fromOptions($class, $options);
        }

        return $settings;
    }

    /**
     * @param  class-string<Model&Viewable>  $class
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidConfiguration
     */
    public static function fromOptions(string $class, array $options): self
    {
        $seasonality = self::seasonality($class, $options['seasonality'] ?? 'week');
        $hours = self::hours($class, $options['window'] ?? '1h');

        if ($hours > self::seasonHours($seasonality)) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'window', "no longer than a {$seasonality->value}", $options['window'] ?? null);
        }

        return new self(
            $class,
            $hours,
            $seasonality,
            self::positiveInteger($class, 'samples', $options['samples'] ?? 4),
            self::threshold($class, $options['threshold'] ?? 3.0),
            self::positiveInteger($class, 'minimum', $options['minimum'] ?? 10),
            self::boolean($class, $options['drops'] ?? false),
            self::cooldown($class, $options['cooldown'] ?? '6h'),
        );
    }

    /** @throws InvalidConfiguration */
    private static function seasonality(string $class, mixed $value): Seasonality
    {
        $seasonality = is_string($value) ? Seasonality::tryFrom($value) : null;

        if (! $seasonality instanceof Seasonality) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'seasonality', '`day` or `week`', $value);
        }

        return $seasonality;
    }

    /** @throws InvalidConfiguration */
    private static function hours(string $class, mixed $window): int
    {
        $duration = is_string($window) ? Duration::tryParse($window) : null;

        if ($duration?->interval === PeriodInterval::Hours) {
            return $duration->value;
        }

        if ($duration?->interval === PeriodInterval::Days) {
            return $duration->value * 24;
        }

        throw InvalidConfiguration::invalidSpikeOption($class, 'window', 'whole hours or days, such as `1h` or `1d`', $window);
    }

    private static function seasonHours(Seasonality $seasonality): int
    {
        return match ($seasonality) {
            Seasonality::Day => 24,
            Seasonality::Week => 168,
        };
    }

    /** @throws InvalidConfiguration */
    private static function positiveInteger(string $class, string $option, mixed $value): int
    {
        if (! is_int($value)) {
            throw InvalidConfiguration::invalidSpikeOption($class, $option, 'a positive integer', $value);
        }

        if ($value < 1) {
            throw InvalidConfiguration::invalidSpikeOption($class, $option, 'a positive integer', $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    private static function threshold(string $class, mixed $value): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'threshold', 'a number above 0', $value);
        }

        if ($value <= 0) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'threshold', 'a number above 0', $value);
        }

        return (float) $value;
    }

    /** @throws InvalidConfiguration */
    private static function boolean(string $class, mixed $value): bool
    {
        if (! is_bool($value)) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'drops', 'true or false', $value);
        }

        return $value;
    }

    /** @throws InvalidConfiguration */
    private static function cooldown(string $class, mixed $value): Duration
    {
        $duration = is_string($value) ? Duration::tryParse($value) : null;

        if (! $duration instanceof Duration) {
            throw InvalidConfiguration::invalidSpikeOption($class, 'cooldown', 'a duration such as `6h`', $value);
        }

        return $duration;
    }
}
