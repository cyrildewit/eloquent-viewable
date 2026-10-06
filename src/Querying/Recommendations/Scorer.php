<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

/**
 * The scorer weighs the pairs a source read into one score per candidate:
 * the sum over its seeds of the weight of the seed, by how long ago the
 * recipient viewed it, times how closely the candidate follows the seed. The
 * seeds that add the most to a score are its reasons.
 *
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 *
 * @phpstan-type Scored array{type: string, id: int|string, score: float, because: list<array{type: string, id: int|string}>}
 *
 * @internal
 */
final readonly class Scorer
{
    private const int Reasons = 3;

    public function __construct(
        private DecayCurve $curve,
        private Similarity $similarity,
    ) {}

    /**
     * Ranked by score, highest first, then by type and key.
     *
     * @param  RecommendationPairs  $pairs
     * @return list<Scored>
     */
    public function score(array $pairs, CarbonInterface $now, ?int $limit = null): array
    {
        $weights = [];
        $seeds = [];

        foreach ($pairs['seeds'] as $seed) {
            $key = $this->key($seed['type'], $seed['id']);

            $weights[$key] = $this->curve->weight($this->age(Carbon::parse($seed['viewed_at']), $now));
            $seeds[$key] = ['type' => $seed['type'], 'id' => $seed['id']];
        }

        $audiences = [];

        foreach ($pairs['audiences'] as $audience) {
            $audiences[$this->key($audience['type'], $audience['id'])] = $audience['visitors'];
        }

        $candidates = [];

        foreach ($pairs['pairs'] as $pair) {
            $seed = $this->key($pair['seed_type'], $pair['seed_id']);
            $candidate = $this->key($pair['type'], $pair['id']);

            $contribution = ($weights[$seed] ?? 0.0) * $this->similarity->between(
                $pair['visitors'],
                $audiences[$seed] ?? $pair['visitors'],
                $audiences[$candidate] ?? $pair['visitors'],
            );

            $candidates[$candidate] ??= ['type' => $pair['type'], 'id' => $pair['id'], 'score' => 0.0, 'reasons' => []];
            $candidates[$candidate]['score'] += $contribution;
            $candidates[$candidate]['reasons'][$seed] = $contribution;
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['score'], $a['type'], $a['id']] <=> [$a['score'], $b['type'], $b['id']]);

        $scored = array_map(fn (array $candidate): array => [
            'type' => $candidate['type'],
            'id' => $candidate['id'],
            'score' => $candidate['score'],
            'because' => $this->because($candidate['reasons'], $seeds),
        ], $candidates);

        return $limit === null ? $scored : array_slice($scored, 0, $limit);
    }

    /**
     * The seeds that added the most, the more recent one first on a tie.
     *
     * @param  array<string, float>  $reasons
     * @param  array<string, array{type: string, id: int|string}>  $seeds
     * @return list<array{type: string, id: int|string}>
     */
    private function because(array $reasons, array $seeds): array
    {
        $order = array_flip(array_keys($seeds));
        $ranked = [];

        foreach ($reasons as $key => $contribution) {
            $ranked[] = ['key' => $key, 'contribution' => $contribution, 'order' => $order[$key] ?? 0];
        }

        usort($ranked, static fn (array $a, array $b): int => [$b['contribution'], $a['order']] <=> [$a['contribution'], $b['order']]);

        $because = [];

        foreach (array_slice($ranked, 0, self::Reasons) as $reason) {
            if (isset($seeds[$reason['key']])) {
                $because[] = $seeds[$reason['key']];
            }
        }

        return $because;
    }

    private function age(CarbonInterface $viewedAt, CarbonInterface $now): CarbonInterval
    {
        return CarbonInterval::seconds(max(0, $now->getTimestamp() - $viewedAt->getTimestamp()));
    }

    private function key(string $type, int|string $id): string
    {
        return "{$type}\0{$id}";
    }
}
