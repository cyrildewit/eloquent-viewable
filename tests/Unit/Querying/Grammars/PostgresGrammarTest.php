<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Grammars\PostgresGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new PostgresGrammar)->truncate('"viewed_at"', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "to_char(date_trunc('hour', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'day' => [Granularity::Day, "to_char(date_trunc('day', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'week' => [Granularity::Week, "to_char(date_trunc('week', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'month' => [Granularity::Month, "to_char(date_trunc('month', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'year' => [Granularity::Year, "to_char(date_trunc('year', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
]);

describe('timezone conversion', function (): void {
    function postgresConversion(string $from, string $to, string $start, string $end): TimezoneConversion
    {
        return new TimezoneConversion(new Timezone($from), new Timezone($to), Carbon::parse($start, 'UTC'), Carbon::parse($end, 'UTC'));
    }

    it('shifts by one fixed offset when nothing transitions', function (): void {
        expect((new PostgresGrammar)->convertTimezone('"viewed_at"', postgresConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe("(\"viewed_at\" + interval '36000 seconds')");
    });

    it('shifts backwards for a zone behind the storage zone', function (): void {
        expect((new PostgresGrammar)->convertTimezone('"viewed_at"', postgresConversion('UTC', 'America/New_York', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe("(\"viewed_at\" + interval '-14400 seconds')");
    });

    it('leaves the column alone when the clocks agree', function (): void {
        expect((new PostgresGrammar)->convertTimezone('"viewed_at"', postgresConversion('Europe/London', 'Europe/Lisbon', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe('"viewed_at"');
    });

    it('picks the offset per segment with a case expression', function (): void {
        expect((new PostgresGrammar)->convertTimezone('"viewed_at"', postgresConversion('Europe/Amsterdam', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe('(case'
                ." when \"viewed_at\" < '2026-10-03 18:00:00' then (\"viewed_at\" + interval '28800 seconds')"
                ." when \"viewed_at\" < '2026-10-25 02:00:00' then (\"viewed_at\" + interval '32400 seconds')"
                ." else (\"viewed_at\" + interval '36000 seconds') end)");
    });

    it('can be truncated like a column', function (): void {
        $grammar = new PostgresGrammar;
        $converted = $grammar->convertTimezone('"viewed_at"', postgresConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00'));

        expect($grammar->truncate($converted, Granularity::Day))
            ->toBe("to_char(date_trunc('day', (\"viewed_at\" + interval '36000 seconds')), 'YYYY-MM-DD HH24:MI:SS')");
    });
});
