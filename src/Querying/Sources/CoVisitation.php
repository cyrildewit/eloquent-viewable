<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use stdClass;

/**
 * It reads the pairs a recommendation is scored from straight off the views
 * table, in three statements: the seeds of the recipient, the pairs their
 * visitors make, and the visitors of every viewable in a pair.
 *
 * @phpstan-import-type Seed from RanksRecommendations
 * @phpstan-import-type Pair from RanksRecommendations
 * @phpstan-import-type Audience from RanksRecommendations
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 *
 * @internal
 */
final readonly class CoVisitation
{
    private const int Chunk = 500;

    public function __construct(
        private View $view,
    ) {}

    /** @return RecommendationPairs */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array
    {
        $seeds = $this->seeds($request, $query);

        if ($seeds === []) {
            return ['seeds' => [], 'pairs' => [], 'audiences' => []];
        }

        $pairs = $this->pairs($seeds, $query, $request->minimum, $request->maxVisitors, $request->among, $request->recipient, $request->includeSeen);

        if ($pairs === []) {
            return ['seeds' => $seeds, 'pairs' => [], 'audiences' => []];
        }

        return ['seeds' => $seeds, 'pairs' => $pairs, 'audiences' => $this->audiences($query, [...$seeds, ...$pairs])];
    }

    /**
     * Read the viewables the recipient viewed most recently, newest first.
     *
     * @return list<Seed>
     */
    public function seeds(RecommendationRequest $request, ViewsQuery $query): array
    {
        $builder = $this->ofRecipient($this->view->newQuery()->matching($query), $request->recipient)->toBase();
        $grammar = $builder->getGrammar();

        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');
        $viewedAt = $this->view->qualifyColumn('viewed_at');

        $rows = $builder
            ->selectRaw("{$grammar->wrap($type)} as viewable_type, {$grammar->wrap($id)} as viewable_id, max({$grammar->wrap($viewedAt)}) as last_viewed_at") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($type, $id)
            ->orderByDesc('last_viewed_at')
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->limit($request->seeds)
            ->get();

        $seeds = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, last_viewed_at: string} $row */
        foreach ($rows as $row) {
            $seeds[] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'viewed_at' => $row->last_viewed_at];
        }

        return $seeds;
    }

    /**
     * Joins the views table to the visitors of the seeds, read as a derived
     * table. With a cap on the visitors, a window function numbers the
     * visitors of each seed from the most recent one, which every supported
     * driver runs.
     *
     * A seed never pairs with itself. Given a recipient, no seed is a
     * candidate, its visitors never count towards a pair, and unless what it
     * has seen is included, a candidate it ever viewed is left out.
     *
     * @param  non-empty-list<array{type: string, id: int|string}>  $seeds
     * @return list<Pair>
     */
    public function pairs(array $seeds, ViewsQuery $query, int $minimum, ?int $maxVisitors, ?Viewable $among = null, ?Recipient $recipient = null, bool $includeSeen = true): array
    {
        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');
        $visitor = $this->view->qualifyColumn('visitor');

        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();
        $aggregate = "count(distinct {$grammar->wrap($visitor)})";

        $builder
            ->joinSub($this->anchors($seeds, $query, $maxVisitors, $recipient), 'anchors', 'anchors.anchor_visitor', '=', $visitor)
            ->where(static fn (Builder $nested): Builder => $nested
                ->whereColumn($type, '!=', 'anchors.seed_type')
                ->orWhereColumn($id, '!=', 'anchors.seed_id'));

        if ($recipient instanceof Recipient) {
            $builder->whereNot(fn (Builder $nested): Builder => $this->amongSeeds($nested, $seeds));
        }

        if ($among instanceof Viewable) {
            $builder->where($type, $among->getMorphClass());
        }

        // Use an anti-join so that what the recipient has seen is read once. A
        // correlated `not exists` runs once per view the join reads, and without
        // an index that leads with the visitor, each run reads every view of a
        // viewable.
        if ($recipient instanceof Recipient && ! $includeSeen) {
            $builder
                ->leftJoinSub($this->seen($recipient), 'seen', static fn (JoinClause $join): JoinClause => $join
                    ->on('seen.viewable_type', '=', $type)
                    ->on('seen.viewable_id', '=', $id))
                ->whereNull('seen.viewable_type');
        }

        $rows = $builder
            ->selectRaw("anchors.seed_type, anchors.seed_id, {$grammar->wrap($type)} as viewable_type, {$grammar->wrap($id)} as viewable_id, {$aggregate} as visitors") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy('anchors.seed_type', 'anchors.seed_id', $type, $id)
            ->havingRaw("{$aggregate} >= ?", [$minimum]) // @phpstan-ignore argument.type (built from wrapped identifiers, the minimum is bound)
            ->orderBy('anchors.seed_type')
            ->orderBy('anchors.seed_id')
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->get();

        $pairs = [];

        /** @var stdClass&object{seed_type: string, seed_id: int|string, viewable_type: string, viewable_id: int|string, visitors: int|string} $row */
        foreach ($rows as $row) {
            $pairs[] = [
                'seed_type' => $row->seed_type,
                'seed_id' => $row->seed_id,
                'type' => $row->viewable_type,
                'id' => $row->viewable_id,
                'visitors' => (int) $row->visitors,
            ];
        }

        return $pairs;
    }

    /**
     * The visitors of each seed, apart from those of the recipient: a visitor
     * it was signed in as, or its visitor id.
     *
     * @param  non-empty-list<array{type: string, id: int|string}>  $seeds
     */
    private function anchors(array $seeds, ViewsQuery $query, ?int $maxVisitors, ?Recipient $recipient): Builder
    {
        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');
        $visitor = $this->view->qualifyColumn('visitor');

        $anchors = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $anchors->getGrammar();

        $anchors
            ->whereNotNull($visitor)
            ->where(fn (Builder $nested): Builder => $this->amongSeeds($nested, $seeds))
            ->selectRaw("{$grammar->wrap($type)} as seed_type, {$grammar->wrap($id)} as seed_id, {$grammar->wrap($visitor)} as anchor_visitor") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($type, $id, $visitor);

        if ($recipient instanceof Recipient) {
            $this->withoutVisitorsOf($anchors, $recipient, $visitor);
        }

        if ($maxVisitors === null) {
            return $anchors;
        }

        $viewedAt = $grammar->wrap($this->view->qualifyColumn('viewed_at'));

        $anchors->selectRaw("row_number() over (partition by {$grammar->wrap($type)}, {$grammar->wrap($id)} order by max({$viewedAt}) desc, {$grammar->wrap($visitor)}) as position"); // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)

        return $this->view->getConnection()->query()
            ->fromSub($anchors, 'ranked')
            ->select('seed_type', 'seed_id', 'anchor_visitor')
            ->where('position', '<=', $maxVisitors);
    }

    /**
     * Count the distinct visitors of every viewable given, one statement per
     * type and chunk of keys.
     *
     * @param  list<array{type: string, id: int|string}>  $viewables
     * @return list<Audience>
     */
    public function audiences(ViewsQuery $query, array $viewables): array
    {
        $keys = [];

        foreach ($viewables as $viewable) {
            $keys[$viewable['type']][(string) $viewable['id']] = $viewable['id'];
        }

        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');
        $visitor = $this->view->qualifyColumn('visitor');

        $audiences = [];

        foreach ($keys as $viewableType => $ids) {
            foreach (array_chunk(array_values($ids), self::Chunk) as $chunk) {
                $builder = $this->view->newQuery()->matching($query)->toBase();
                $grammar = $builder->getGrammar();

                $rows = $builder
                    ->whereNotNull($visitor)
                    ->where($type, $viewableType)
                    ->whereIn($id, $chunk)
                    ->selectRaw("{$grammar->wrap($type)} as viewable_type, {$grammar->wrap($id)} as viewable_id, count(distinct {$grammar->wrap($visitor)}) as visitors") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
                    ->groupBy($type, $id)
                    ->get();

                /** @var stdClass&object{viewable_type: string, viewable_id: int|string, visitors: int|string} $row */
                foreach ($rows as $row) {
                    $audiences[] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'visitors' => (int) $row->visitors];
                }
            }
        }

        return $audiences;
    }

    /** @param  non-empty-list<array{type: string, id: int|string}>  $seeds */
    private function amongSeeds(Builder $builder, array $seeds): Builder
    {
        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');

        foreach ($seeds as $seed) {
            $builder->orWhere(static fn (Builder $one): Builder => $one->where($type, $seed['type'])->where($id, $seed['id']));
        }

        return $builder;
    }

    /**
     * Every viewable the recipient viewed, over all time and every
     * collection, once each.
     */
    private function seen(Recipient $recipient): Builder
    {
        $seen = $this->view->getConnection()
            ->table($this->view->getTable())
            ->select(['viewable_type', 'viewable_id'])
            ->distinct();

        if (! $recipient->isViewer()) {
            return $seen->where('visitor', $recipient->visitor);
        }

        return $seen->where('viewer_type', $recipient->viewer->getMorphClass())->where('viewer_id', $recipient->viewerKey);
    }

    /**
     * The views the recipient made of the viewable the two columns of the
     * outer query name, read under an alias of their own, over all time and
     * every collection. With its visitors, the views of the browsers a viewer
     * was signed in on count as well.
     */
    public function seenBy(Recipient $recipient, string $typeColumn, string $idColumn, bool $withVisitors = false): Builder
    {
        $seen = $this->view->getConnection()
            ->table("{$this->view->getTable()} as seen")
            ->whereColumn('seen.viewable_type', $typeColumn)
            ->whereColumn('seen.viewable_id', $idColumn)
            ->selectRaw('1');

        if (! $recipient->isViewer()) {
            return $seen->where('seen.visitor', $recipient->visitor);
        }

        $viewer = $recipient->viewer;
        $key = $recipient->viewerKey;

        if (! $withVisitors) {
            return $seen->where('seen.viewer_type', $viewer->getMorphClass())->where('seen.viewer_id', $key);
        }

        return $seen->where(fn (Builder $nested): Builder => $nested
            ->where(static fn (Builder $own): Builder => $own->where('seen.viewer_type', $viewer->getMorphClass())->where('seen.viewer_id', $key))
            ->orWhereIn('seen.visitor', $this->visitorsOf($viewer)));
    }

    /**
     * Leave out the visitors of the recipient: a visitor id a viewer was
     * signed in as, or the visitor id itself.
     */
    private function withoutVisitorsOf(Builder $builder, Recipient $recipient, string $column): void
    {
        if ($recipient->isViewer()) {
            $builder->whereNotIn($column, $this->visitorsOf($recipient->viewer));

            return;
        }

        $builder->where($column, '!=', $recipient->visitor);
    }

    private function visitorsOf(Model $viewer): Builder
    {
        $visitor = $this->view->qualifyColumn('visitor');

        return $this->view->newQuery()
            ->byViewer($viewer)
            ->whereNotNull($visitor)
            ->select($visitor)
            ->distinct()
            ->toBase();
    }

    /**
     * @param  EloquentBuilder<View>  $builder
     * @return EloquentBuilder<View>
     */
    private function ofRecipient(EloquentBuilder $builder, Recipient $recipient): EloquentBuilder
    {
        if ($recipient->isViewer()) {
            return $builder->byViewer($recipient->viewer);
        }

        return $builder->byVisitor($recipient->visitor);
    }
}
