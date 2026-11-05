<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-10 12:34:56');

    // The `web` and `api` groups of an application apply this middleware;
    // a bare test route has to ask for it.
    Route::middleware(SubstituteBindings::class)
        ->get('/stats/{period}', fn (Period $period): string => $period->getStartDateTime()?->format('Y-m-d H:i:s').'|'.$period->getRouteKey())
        ->name('stats');
});

it('binds a {period} route parameter implicitly', function (): void {
    $this->get('/stats/7d')->assertOk()->assertSee('2026-09-03 00:00:00|7d');
    $this->get('/stats/2026-01-01..2026-02-01')->assertOk()->assertSee('2026-01-01 00:00:00|2026-01-01..2026-02-01');
});

it('responds with a 404 to a value that is not a period', function (): void {
    $this->get('/stats/nonsense')->assertNotFound();
});

it('renders a period into a URL', function (): void {
    expect(route('stats', ['period' => Period::pastDays(7)], false))->toBe('/stats/7d')
        ->and(route('stats', ['period' => Period::create('2026-01-01', '2026-02-01')], false))->toBe('/stats/2026-01-01..2026-02-01');
});
