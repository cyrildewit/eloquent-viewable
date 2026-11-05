<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Scorer;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Similarity;

function scorer(Similarity $similarity = Similarity::Cosine): Scorer
{
    return new Scorer(new ExponentialDecay(CarbonInterval::days(1)), $similarity);
}

function scoringNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-01-10 00:00:00');
}

it('weighs a seed by how recently it was viewed', function (): void {
    $scored = scorer(Similarity::Count)->score([
        'seeds' => [
            ['type' => 'posts', 'id' => 1, 'viewed_at' => '2026-01-10 00:00:00'],
            ['type' => 'posts', 'id' => 2, 'viewed_at' => '2026-01-09 00:00:00'],
        ],
        'pairs' => [
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 10, 'visitors' => 4],
            ['seed_type' => 'posts', 'seed_id' => 2, 'type' => 'posts', 'id' => 10, 'visitors' => 2],
            ['seed_type' => 'posts', 'seed_id' => 2, 'type' => 'posts', 'id' => 11, 'visitors' => 6],
        ],
        'audiences' => [],
    ], scoringNow());

    expect($scored)->toBe([
        ['type' => 'posts', 'id' => 10, 'score' => 5.0, 'because' => [['type' => 'posts', 'id' => 1], ['type' => 'posts', 'id' => 2]]],
        ['type' => 'posts', 'id' => 11, 'score' => 3.0, 'because' => [['type' => 'posts', 'id' => 2]]],
    ]);
});

it('treats a seed viewed after the moment of scoring as viewed just now', function (): void {
    $scored = scorer(Similarity::Count)->score([
        'seeds' => [['type' => 'posts', 'id' => 1, 'viewed_at' => '2026-01-11 00:00:00']],
        'pairs' => [['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 10, 'visitors' => 3]],
        'audiences' => [],
    ], scoringNow());

    expect($scored[0]['score'])->toBe(3.0);
});

it('divides by the visitors of both sides with cosine similarity', function (): void {
    $scored = scorer()->score([
        'seeds' => [['type' => 'posts', 'id' => 1, 'viewed_at' => '2026-01-10 00:00:00']],
        'pairs' => [
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 10, 'visitors' => 4],
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 11, 'visitors' => 3],
        ],
        'audiences' => [
            ['type' => 'posts', 'id' => 1, 'visitors' => 4],
            ['type' => 'posts', 'id' => 10, 'visitors' => 100],
            ['type' => 'posts', 'id' => 11, 'visitors' => 4],
        ],
    ], scoringNow());

    expect(array_column($scored, 'id'))->toBe([11, 10])
        ->and($scored[0]['score'])->toBe(0.75)
        ->and($scored[1]['score'])->toBe(0.2);
});

it('falls back to the shared visitors when an audience is missing', function (): void {
    $scored = scorer()->score([
        'seeds' => [['type' => 'posts', 'id' => 1, 'viewed_at' => '2026-01-10 00:00:00']],
        'pairs' => [['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 10, 'visitors' => 4]],
        'audiences' => [],
    ], scoringNow());

    expect($scored[0]['score'])->toBe(1.0);
});

it('breaks ties on the type, then the key, and stops at the limit', function (): void {
    $pairs = [
        'seeds' => [['type' => 'posts', 'id' => 1, 'viewed_at' => '2026-01-10 00:00:00']],
        'pairs' => [
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'videos', 'id' => 1, 'visitors' => 3],
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 12, 'visitors' => 3],
            ['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 11, 'visitors' => 3],
        ],
        'audiences' => [],
    ];

    expect(array_map(static fn (array $row): string => "{$row['type']}:{$row['id']}", scorer(Similarity::Count)->score($pairs, scoringNow())))
        ->toBe(['posts:11', 'posts:12', 'videos:1'])
        ->and(scorer(Similarity::Count)->score($pairs, scoringNow(), 1))->toHaveCount(1);
});

it('gives at most three reasons, the largest first and the more recent seed on a tie', function (): void {
    $seeds = [];
    $pairs = [];

    foreach ([1, 2, 3, 4] as $id) {
        $seeds[] = ['type' => 'posts', 'id' => $id, 'viewed_at' => '2026-01-10 00:00:00'];
        $pairs[] = ['seed_type' => 'posts', 'seed_id' => $id, 'type' => 'posts', 'id' => 10, 'visitors' => $id === 3 ? 9 : 3];
    }

    $scored = scorer(Similarity::Count)->score(['seeds' => $seeds, 'pairs' => $pairs, 'audiences' => []], scoringNow());

    expect($scored[0]['because'])->toBe([['type' => 'posts', 'id' => 3], ['type' => 'posts', 'id' => 1], ['type' => 'posts', 'id' => 2]]);
});

it('gives no reason for a pair whose seed it was not given', function (): void {
    $scored = scorer(Similarity::Count)->score([
        'seeds' => [],
        'pairs' => [['seed_type' => 'posts', 'seed_id' => 1, 'type' => 'posts', 'id' => 10, 'visitors' => 3]],
        'audiences' => [],
    ], scoringNow());

    expect($scored)->toBe([['type' => 'posts', 'id' => 10, 'score' => 0.0, 'because' => []]]);
});

it('returns nothing without pairs', function (): void {
    expect(scorer()->score(['seeds' => [], 'pairs' => [], 'audiences' => []], scoringNow()))->toBeEmpty();
});
