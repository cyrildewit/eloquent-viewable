<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\HotScore;
use Illuminate\Support\Carbon;

it('adds the logarithm of the count to a term that grows with time', function (): void {
    $score = new HotScore('created_at', Duration::tryParse('12h') ?? throw new LogicException);
    $at = Carbon::parse('2026-10-08 12:00:00');

    expect($score->score(1_000, $at))->toEqualWithDelta(3 + $at->getTimestamp() / 43_200, 0.000001)
        ->and($score->score(0, $at))->toEqualWithDelta($at->getTimestamp() / 43_200, 0.000001);
});

it('scores a model every newer the same with a tenth of the views', function (): void {
    $score = new HotScore('created_at', Duration::tryParse('1d') ?? throw new LogicException);

    expect($score->score(10, Carbon::parse('2026-10-08')))->toEqualWithDelta($score->score(100, Carbon::parse('2026-10-07')), 0.000001);
});

it('scores a model without a timestamp on its views alone', function (): void {
    expect(new HotScore('published_at', Duration::tryParse('12h') ?? throw new LogicException))
        ->score(100, null)->toBe(2.0)
        ->signature()->toBe('published_at:12h');
});
