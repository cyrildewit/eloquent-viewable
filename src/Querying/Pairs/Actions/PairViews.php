<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Pairs\Events\ViewsPaired;
use CyrildeWit\EloquentViewable\Querying\Pairs\PairTable;
use CyrildeWit\EloquentViewable\Querying\Sources\CoVisitation;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Events\Dispatcher;
use stdClass;

/**
 * It rewrites the pairs table from the views of the configured period, in
 * one transaction, so a reader sees either the old pairs or the new ones.
 * Only a viewable with at least the minimum of visitors can be in a pair, so
 * only those are read, a chunk at a time.
 *
 * @phpstan-import-type Pair from RanksRecommendations
 */
final readonly class PairViews
{
    private const int Chunk = 100;

    private const int InsertChunk = 500;

    public function __construct(
        private Config $config,
        private View $view,
        private CoVisitation $coVisitation,
        private PairTable $table,
        private Dispatcher $events,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function handle(): ViewsPaired
    {
        $since = CarbonImmutable::instance($this->config->pairsPeriod()->before(CarbonImmutable::now()));
        $query = new ViewsQuery(Period::since($since));
        $minimum = $this->config->alsoViewedMinimumVisitors();
        $maxVisitors = $this->config->alsoViewedMaxVisitors();
        $maxPairs = $this->config->pairsMaxPairs();

        $audiences = $this->audiences($query, $minimum);
        $written = 0;

        $this->view->getConnection()->transaction(function () use ($query, $audiences, $minimum, $maxVisitors, $maxPairs, &$written): void {
            $this->table->query()->delete();

            foreach (array_chunk(array_values($audiences), self::Chunk) as $chunk) {
                $rows = $this->rows($this->coVisitation->pairs($chunk, $query, $minimum, $maxVisitors), $audiences, $maxPairs);

                foreach (array_chunk($rows, self::InsertChunk) as $insert) {
                    $this->table->query()->insert($insert);
                }

                $written += count($rows);
            }
        });

        $event = new ViewsPaired($since, count($audiences), $written);

        $this->events->dispatch($event);

        return $event;
    }

    /**
     * The viewables with at least the minimum of visitors over the period,
     * keyed by type and key.
     *
     * @return array<string, array{type: string, id: int|string, visitors: int}>
     */
    private function audiences(ViewsQuery $query, int $minimum): array
    {
        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');
        $visitor = $this->view->qualifyColumn('visitor');

        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();
        $aggregate = "count(distinct {$grammar->wrap($visitor)})";

        $rows = $builder
            ->whereNotNull($visitor)
            ->selectRaw("{$grammar->wrap($type)} as viewable_type, {$grammar->wrap($id)} as viewable_id, {$aggregate} as visitors") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($type, $id)
            ->havingRaw("{$aggregate} >= ?", [$minimum]) // @phpstan-ignore argument.type (built from wrapped identifiers, the minimum is bound)
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->get();

        $audiences = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, visitors: int|string} $row */
        foreach ($rows as $row) {
            $audiences[$this->key($row->viewable_type, $row->viewable_id)] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'visitors' => (int) $row->visitors];
        }

        return $audiences;
    }

    /**
     * Keep the `$maxPairs` pairs of each viewable that share the most
     * visitors, then by type and key.
     *
     * @param  list<Pair>  $pairs
     * @param  array<string, array{type: string, id: int|string, visitors: int}>  $audiences
     * @return list<array{viewable_type: string, viewable_id: int|string, paired_type: string, paired_id: int|string, visitors: int, viewable_visitors: int, paired_visitors: int}>
     */
    private function rows(array $pairs, array $audiences, int $maxPairs): array
    {
        $grouped = [];

        foreach ($pairs as $pair) {
            $grouped[$this->key($pair['seed_type'], $pair['seed_id'])][] = $pair;
        }

        $rows = [];

        foreach ($grouped as $seed => $group) {
            usort($group, static fn (array $a, array $b): int => [$b['visitors'], $a['type'], $a['id']] <=> [$a['visitors'], $b['type'], $b['id']]);

            foreach (array_slice($group, 0, $maxPairs) as $pair) {
                $rows[] = [
                    'viewable_type' => $pair['seed_type'],
                    'viewable_id' => $pair['seed_id'],
                    'paired_type' => $pair['type'],
                    'paired_id' => $pair['id'],
                    'visitors' => $pair['visitors'],
                    'viewable_visitors' => $audiences[$seed]['visitors'] ?? $pair['visitors'],
                    'paired_visitors' => $audiences[$this->key($pair['type'], $pair['id'])]['visitors'] ?? $pair['visitors'],
                ];
            }
        }

        return $rows;
    }

    private function key(string $type, int|string $id): string
    {
        return "{$type}\0{$id}";
    }
}
