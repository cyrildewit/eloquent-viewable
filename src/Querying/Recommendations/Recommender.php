<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Support\Carbon;

/**
 * Turns the configured recommendation options into a request for the
 * source and a scorer for what it reads, the way `DecayFactory` turns the
 * trending options into a decay.
 *
 * @phpstan-import-type Scored from Scorer
 *
 * @internal
 */
final readonly class Recommender
{
    public function __construct(
        private Config $config,
    ) {}

    /**
     * The score of every candidate for the recipient, highest first. The
     * query is matched on every side; a viewer it names is the recipient,
     * not a narrowing.
     *
     * @return list<Scored>
     *
     * @throws InvalidConfiguration
     */
    public function score(RanksRecommendations $source, Recipient $recipient, ?Viewable $among, ViewsQuery $query, bool $includeSeen, ?int $limit = null): array
    {
        $request = new RecommendationRequest(
            $recipient,
            $among,
            $this->config->recommendationsMaxSeeds(),
            $this->config->alsoViewedMinimumVisitors(),
            $this->config->recommendationsMaxVisitors(),
            $includeSeen,
        );

        $scorer = new Scorer(
            new ExponentialDecay($this->config->recommendationsHalfLife()->toInterval()),
            Similarity::from($this->config->recommendationsSimilarity()),
        );

        return $scorer->score(
            $source->recommendationPairs($request, $query->withViewer(null)),
            $query->period?->getEndDateTime() ?? Carbon::now(),
            $limit,
        );
    }
}
