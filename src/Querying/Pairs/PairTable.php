<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Querying\Sources\CoVisitation;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * The pairs table holds, for every configured period and viewable, the
 * viewables that share the most visitors with it over that period, as
 * `views:pairs` last wrote them. Both directions of a pair are stored, so a
 * viewable finds its pairs on one side of the index.
 *
 * @phpstan-import-type Seed from RanksRecommendations
 * @phpstan-import-type Pair from RanksRecommendations
 * @phpstan-import-type Audience from RanksRecommendations
 *
 * @internal
 */
final readonly class PairTable
{
    public function __construct(
        private Config $config,
        private View $view,
        private CoVisitation $coVisitation,
    ) {}

    /**
     * The period of the pairs that answer a call, while
     * `querying.pairs.enabled` is on: the configured period as long as the
     * relative period of the call, or the longest for a call over all time.
     * Null when the views table answers: any other period, a collection or
     * a filter.
     *
     * @throws InvalidConfiguration
     */
    public function periodFor(ViewsQuery $query): ?string
    {
        if (! $this->config->pairsEnabled() || $query->collection !== null || $query->filter instanceof FiltersViews) {
            return null;
        }

        $periods = array_map(static fn (Duration $period): string => $period->shorthand(), $this->config->pairsPeriods());

        if (! $query->period instanceof Period) {
            return $periods[0];
        }

        $length = $query->period->length()?->shorthand();

        return in_array($length, $periods, true) ? $length : null;
    }

    /**
     * @return list<array{type: string, id: int|string, count: int}>
     *
     * @throws InvalidConfiguration
     */
    public function alsoViewed(string $period, Viewable $viewable, ?Viewable $among, int $limit, int $minimum): array
    {
        $builder = $this->query()
            ->where('period', $period)
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', ViewableKey::of($viewable))
            ->where('visitors', '>=', $minimum);

        if ($among instanceof Viewable) {
            $builder->where('paired_type', $among->getMorphClass());
        }

        $rows = $builder
            ->select('paired_type', 'paired_id', 'visitors')
            ->orderByDesc('visitors')
            ->orderBy('paired_type')
            ->orderBy('paired_id')
            ->limit($limit)
            ->get();

        $ranking = [];

        /** @var stdClass&object{paired_type: string, paired_id: int|string, visitors: int|string} $row */
        foreach ($rows as $row) {
            $ranking[] = ['type' => $row->paired_type, 'id' => $row->paired_id, 'count' => (int) $row->visitors];
        }

        return $ranking;
    }

    /**
     * The table cannot tell whether the recipient is one of the visitors of a
     * pair, so a candidate it viewed in any way counts one visitor fewer. The
     * pairs never fall below the minimum the live query would keep.
     *
     * @param  non-empty-list<Seed>  $seeds
     * @return array{pairs: list<Pair>, audiences: list<Audience>}
     *
     * @throws InvalidConfiguration
     */
    public function recommendationPairs(string $period, RecommendationRequest $request, array $seeds): array
    {
        $type = "{$this->config->pairsTable()}.paired_type";

        $builder = $this->query()
            ->where('period', $period)
            ->where(fn (Builder $nested): Builder => $this->matchingAny($nested, $seeds, 'viewable_type', 'viewable_id'))
            ->whereNot(fn (Builder $nested): Builder => $this->matchingAny($nested, $seeds, 'paired_type', 'paired_id'));

        if ($request->among instanceof Viewable) {
            $builder->where($type, $request->among->getMorphClass());
        }

        $rows = $builder
            ->select('viewable_type', 'viewable_id', 'paired_type', 'paired_id', 'visitors', 'viewable_visitors', 'paired_visitors')
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->orderBy('paired_type')
            ->orderBy('paired_id')
            ->get();

        $candidates = [];

        /** @var stdClass&object{paired_type: string, paired_id: int|string} $row */
        foreach ($rows as $row) {
            $candidates[] = ['type' => $row->paired_type, 'id' => $row->paired_id];
        }

        $seen = $this->coVisitation->seenAmong($request->recipient, $candidates);

        $pairs = [];
        $audiences = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, paired_type: string, paired_id: int|string, visitors: int|string, viewable_visitors: int|string, paired_visitors: int|string} $row */
        foreach ($rows as $row) {
            $key = "{$row->paired_type}\0{$row->paired_id}";

            if (! $request->includeSeen && ($seen[$key] ?? false)) {
                continue;
            }

            $visitors = (int) $row->visitors - (isset($seen[$key]) ? 1 : 0);

            if ($visitors < $request->minimum) {
                continue;
            }

            $pairs[] = [
                'seed_type' => $row->viewable_type,
                'seed_id' => $row->viewable_id,
                'type' => $row->paired_type,
                'id' => $row->paired_id,
                'visitors' => $visitors,
            ];

            $audiences["{$row->viewable_type}\0{$row->viewable_id}"] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'visitors' => (int) $row->viewable_visitors];
            $audiences["{$row->paired_type}\0{$row->paired_id}"] = ['type' => $row->paired_type, 'id' => $row->paired_id, 'visitors' => (int) $row->paired_visitors];
        }

        return ['pairs' => $pairs, 'audiences' => array_values($audiences)];
    }

    /** @throws InvalidConfiguration */
    public function query(): Builder
    {
        return $this->view->getConnection()->table($this->config->pairsTable());
    }

    /** @param  non-empty-list<array{type: string, id: int|string}>  $viewables */
    private function matchingAny(Builder $builder, array $viewables, string $typeColumn, string $idColumn): Builder
    {
        foreach ($viewables as $viewable) {
            $builder->orWhere(static fn (Builder $one): Builder => $one->where($typeColumn, $viewable['type'])->where($idColumn, $viewable['id']));
        }

        return $builder;
    }
}
