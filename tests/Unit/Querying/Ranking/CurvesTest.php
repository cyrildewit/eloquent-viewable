<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\Window;

describe('exponential decay', function (): void {
    it('halves the weight every half-life', function (): void {
        $curve = new ExponentialDecay(CarbonInterval::day());

        expect($curve->weight(CarbonInterval::hours(0)))->toBe(1.0)
            ->and($curve->weight(CarbonInterval::day()))->toBe(0.5)
            ->and($curve->weight(CarbonInterval::days(3)))->toBe(0.125)
            ->and($curve->weight(CarbonInterval::hours(1)))->toEqualWithDelta(0.971532, 0.000001);
    });

    it('weighs a view from the future as one from now', function (): void {
        expect(new ExponentialDecay(CarbonInterval::day())->weight(CarbonInterval::hours(-2)))->toBe(1.0);
    });

    it('reaches eight half-lives', function (): void {
        expect(new ExponentialDecay(CarbonInterval::hours(6))->horizon()->totalHours)->toEqual(48);
    });

    it('is identified by its half-life', function (): void {
        expect(new ExponentialDecay(CarbonInterval::day())->identity())
            ->toBe(new ExponentialDecay(CarbonInterval::hours(24))->identity())
            ->not->toBe(new ExponentialDecay(CarbonInterval::week())->identity())
            ->toContain(ExponentialDecay::class);
    });
});

describe('linear decay', function (): void {
    it('loses its weight evenly up to the end of the window', function (): void {
        $curve = new LinearDecay(CarbonInterval::days(4));

        expect($curve->weight(CarbonInterval::hours(0)))->toBe(1.0)
            ->and($curve->weight(CarbonInterval::day()))->toBe(0.75)
            ->and($curve->weight(CarbonInterval::days(4)))->toBe(0.0)
            ->and($curve->weight(CarbonInterval::days(9)))->toBe(0.0)
            ->and($curve->weight(CarbonInterval::hours(-2)))->toBe(1.0);
    });

    it('reaches the end of the window', function (): void {
        expect(new LinearDecay(CarbonInterval::days(4))->horizon()->totalDays)->toEqual(4);
    });

    it('is identified by its window', function (): void {
        expect(new LinearDecay(CarbonInterval::days(4))->identity())
            ->not->toBe(new LinearDecay(CarbonInterval::days(5))->identity())
            ->not->toBe(new Window(CarbonInterval::days(4))->identity());
    });
});

describe('window', function (): void {
    it('weighs every view inside the window fully', function (): void {
        $curve = new Window(CarbonInterval::days(7));

        expect($curve->weight(CarbonInterval::hours(0)))->toBe(1.0)
            ->and($curve->weight(CarbonInterval::days(6)))->toBe(1.0)
            ->and($curve->weight(CarbonInterval::days(7)))->toBe(0.0);
    });

    it('reaches the end of the window', function (): void {
        expect(new Window(CarbonInterval::days(7))->horizon()->totalDays)->toEqual(7);
    });

    it('is identified by its window', function (): void {
        expect(new Window(CarbonInterval::days(7))->identity())
            ->toBe(new Window(CarbonInterval::week())->identity())
            ->not->toBe(new Window(CarbonInterval::days(6))->identity());
    });
});

it('refuses an interval that is not positive', function (Closure $curve): void {
    $curve();
})->throws(InvalidDecay::class)->with([
    'a half-life of zero' => fn (): ExponentialDecay => new ExponentialDecay(CarbonInterval::hours(0)),
    'a negative window' => fn (): LinearDecay => new LinearDecay(CarbonInterval::days(-1)),
    'an empty window' => fn (): Window => new Window(CarbonInterval::seconds(0)),
]);

it('names the interval it refuses', function (): void {
    expect(fn (): ExponentialDecay => new ExponentialDecay(CarbonInterval::hours(0)))
        ->toThrow(InvalidDecay::class, 'The half-life of a trending curve must be longer than zero');
});
