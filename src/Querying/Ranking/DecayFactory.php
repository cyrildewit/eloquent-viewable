<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Builds the `Decay` of a trending call from its arguments and the
 * `querying.trending` config. Day steps are floored in the rollup timezone,
 * so the buckets of a day tier line up with them.
 *
 * @internal
 */
final readonly class DecayFactory
{
    public function __construct(
        private Config $config,
        private Container $container,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws InvalidConfiguration
     * @throws InvalidDecay
     * @throws InvalidTimezone
     */
    public function make(ViewsQuery $query, ?CarbonInterval $halfLife = null, ?DecayCurve $curve = null): Decay
    {
        if ($halfLife instanceof CarbonInterval && $curve instanceof DecayCurve) {
            throw InvalidDecay::halfLifeAndCurve();
        }

        if ($halfLife instanceof CarbonInterval) {
            $curve = new ExponentialDecay($halfLife);
        }

        return Decay::for(
            $query,
            $curve ?? $this->curve(),
            $this->config->trendingStep(),
            $this->config->trendingMaxSteps(),
            Timezone::from($this->config->rollupTimezone() ?? Timezone::application()),
        );
    }

    /**
     * @throws BindingResolutionException
     * @throws InvalidConfiguration
     * @throws InvalidDecay
     */
    private function curve(): DecayCurve
    {
        $class = $this->config->trendingCurve();

        if ($class === null) {
            return new ExponentialDecay($this->config->trendingHalfLife()->toInterval());
        }

        $curve = $this->container->make($class);

        if (! $curve instanceof DecayCurve) {
            throw InvalidConfiguration::mustNameClassImplementing('querying.trending.curve', DecayCurve::class, $class);
        }

        return $curve;
    }
}
