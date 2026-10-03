<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

it('is immutable', function (): void {
    $reflection = new ReflectionClass(ViewsQuery::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('defaults to no period, no collection, non-unique, no timezone and no viewer', function (): void {
    $query = new ViewsQuery;

    expect($query->period)->toBeNull()
        ->and($query->collection)->toBeNull()
        ->and($query->unique)->toBeFalse()
        ->and($query->timezone)->toBeNull()
        ->and($query->viewer)->toBeNull();
});

it('exposes what it was constructed with', function (): void {
    $period = Period::create('2026-09-01', '2026-09-02');
    $timezone = new Timezone('Australia/Sydney');
    $viewer = new Post(['id' => 7]);

    $query = new ViewsQuery($period, 'custom', true, $timezone, $viewer);

    expect($query->period)->toBe($period)
        ->and($query->collection)->toBe('custom')
        ->and($query->unique)->toBeTrue()
        ->and($query->timezone)->toBe($timezone)
        ->and($query->viewer)->toBe($viewer);
});

describe('timezone', function (): void {
    beforeEach(function (): void {
        // 23:00 UTC on the 1st is 09:00 on the 2nd in Sydney.
        Carbon::setTestNow('2026-09-01 23:00:00');
    });

    it('re-anchors a relative period on its clock', function (): void {
        $query = new ViewsQuery(Period::pastDays(1), timezone: new Timezone('Australia/Sydney'));

        expect($query->period->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-09-01 00:00:00', 'Australia/Sydney')->timestamp)
            ->and($query->period->cacheSignature())->toBe('past1days@Australia/Sydney');
    });

    it('leaves a relative period with a zone of its own alone', function (): void {
        $period = Period::pastDays(1, 'Europe/Amsterdam');

        expect(new ViewsQuery($period, timezone: new Timezone('Australia/Sydney'))->period)->toBe($period);
    });

    it('leaves an absolute period alone', function (): void {
        $period = Period::create('2026-09-01', '2026-09-02');

        expect(new ViewsQuery($period, timezone: new Timezone('Australia/Sydney'))->period)->toBe($period);
    });

    it('leaves a relative period alone without a timezone', function (): void {
        $period = Period::pastDays(1);

        expect(new ViewsQuery($period)->period)->toBe($period);
    });
});

describe('withPeriod', function (): void {
    it('copies the query with another period', function (): void {
        $timezone = new Timezone('Australia/Sydney');
        $viewer = new Post(['id' => 7]);
        $query = new ViewsQuery(Period::create('2026-09-01', '2026-09-02'), 'custom', true, $timezone, $viewer);
        $period = Period::create('2026-08-31', '2026-09-01');

        $copy = $query->withPeriod($period);

        expect($copy)->not->toBe($query)
            ->and($copy->period)->toBe($period)
            ->and($copy->collection)->toBe('custom')
            ->and($copy->unique)->toBeTrue()
            ->and($copy->timezone)->toBe($timezone)
            ->and($copy->viewer)->toBe($viewer)
            ->and($query->period->getRouteKey())->toBe('2026-09-01..2026-09-02');
    });

    it('re-anchors a relative period on its clock', function (): void {
        Carbon::setTestNow('2026-09-01 23:00:00');

        $query = new ViewsQuery(timezone: new Timezone('Australia/Sydney'));

        expect($query->withPeriod(Period::pastDays(1))->period->cacheSignature())->toBe('past1days@Australia/Sydney')
            ->and($query->withPeriod(null)->period)->toBeNull();
    });
});
