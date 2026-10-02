<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Grammars\MySqlGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new MySqlGrammar)->truncate('`viewed_at`', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "date_format(`viewed_at`, '%Y-%m-%d %H:00:00')"],
    'day' => [Granularity::Day, "date_format(`viewed_at`, '%Y-%m-%d 00:00:00')"],
    'week' => [Granularity::Week, "date_format(date_sub(`viewed_at`, interval weekday(`viewed_at`) day), '%Y-%m-%d 00:00:00')"],
    'month' => [Granularity::Month, "date_format(`viewed_at`, '%Y-%m-01 00:00:00')"],
    'year' => [Granularity::Year, "date_format(`viewed_at`, '%Y-01-01 00:00:00')"],
]);

describe('timezone conversion', function (): void {
    function mysqlConversion(string $from, string $to, string $start, string $end): TimezoneConversion
    {
        return new TimezoneConversion(new Timezone($from), new Timezone($to), Carbon::parse($start, 'UTC'), Carbon::parse($end, 'UTC'));
    }

    it('shifts by one fixed offset when nothing transitions', function (): void {
        expect((new MySqlGrammar)->convertTimezone('`viewed_at`', mysqlConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe('date_add(`viewed_at`, interval 36000 second)');
    });

    it('shifts backwards for a zone behind the storage zone', function (): void {
        expect((new MySqlGrammar)->convertTimezone('`viewed_at`', mysqlConversion('UTC', 'America/New_York', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe('date_add(`viewed_at`, interval -14400 second)');
    });

    it('leaves the column alone when the clocks agree', function (): void {
        expect((new MySqlGrammar)->convertTimezone('`viewed_at`', mysqlConversion('Europe/London', 'Europe/Lisbon', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe('`viewed_at`');
    });

    it('picks the offset per segment with a case expression', function (): void {
        expect((new MySqlGrammar)->convertTimezone('`viewed_at`', mysqlConversion('Europe/Amsterdam', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe('(case'
                ." when `viewed_at` < '2026-10-03 18:00:00' then date_add(`viewed_at`, interval 28800 second)"
                ." when `viewed_at` < '2026-10-25 02:00:00' then date_add(`viewed_at`, interval 32400 second)"
                .' else date_add(`viewed_at`, interval 36000 second) end)');
    });

    it('can be truncated like a column', function (): void {
        $grammar = new MySqlGrammar;
        $converted = $grammar->convertTimezone('`viewed_at`', mysqlConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00'));

        expect($grammar->truncate($converted, Granularity::Day))
            ->toBe("date_format(date_add(`viewed_at`, interval 36000 second), '%Y-%m-%d 00:00:00')");
    });
});
