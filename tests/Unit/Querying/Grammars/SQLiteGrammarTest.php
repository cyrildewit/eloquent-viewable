<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Grammars\SQLiteGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new SQLiteGrammar)->truncate('"viewed_at"', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "strftime('%Y-%m-%d %H:00:00', \"viewed_at\")"],
    'day' => [Granularity::Day, "strftime('%Y-%m-%d 00:00:00', \"viewed_at\")"],
    'week' => [Granularity::Week, "strftime('%Y-%m-%d 00:00:00', \"viewed_at\", '-' || ((strftime('%w', \"viewed_at\") + 6) % 7) || ' days')"],
    'month' => [Granularity::Month, "strftime('%Y-%m-01 00:00:00', \"viewed_at\")"],
    'year' => [Granularity::Year, "strftime('%Y-01-01 00:00:00', \"viewed_at\")"],
]);

describe('timezone conversion', function (): void {
    function sqliteConversion(string $from, string $to, string $start, string $end): TimezoneConversion
    {
        return new TimezoneConversion(new Timezone($from), new Timezone($to), Carbon::parse($start, 'UTC'), Carbon::parse($end, 'UTC'));
    }

    it('shifts by one fixed offset when nothing transitions', function (): void {
        expect((new SQLiteGrammar)->convertTimezone('"viewed_at"', sqliteConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe("datetime(\"viewed_at\", '+36000 seconds')");
    });

    it('shifts backwards for a zone behind the storage zone', function (): void {
        expect((new SQLiteGrammar)->convertTimezone('"viewed_at"', sqliteConversion('UTC', 'America/New_York', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe("datetime(\"viewed_at\", '-14400 seconds')");
    });

    it('leaves the column alone when the clocks agree', function (): void {
        expect((new SQLiteGrammar)->convertTimezone('"viewed_at"', sqliteConversion('Europe/London', 'Europe/Lisbon', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe('"viewed_at"');
    });

    it('picks the offset per segment with a case expression', function (): void {
        expect((new SQLiteGrammar)->convertTimezone('"viewed_at"', sqliteConversion('Europe/Amsterdam', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe('(case'
                ." when \"viewed_at\" < '2026-10-03 18:00:00' then datetime(\"viewed_at\", '+28800 seconds')"
                ." when \"viewed_at\" < '2026-10-25 02:00:00' then datetime(\"viewed_at\", '+32400 seconds')"
                ." else datetime(\"viewed_at\", '+36000 seconds') end)");
    });

    it('can be truncated like a column', function (): void {
        $grammar = new SQLiteGrammar;
        $converted = $grammar->convertTimezone('"viewed_at"', sqliteConversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00'));

        expect($grammar->truncate($converted, Granularity::Day))
            ->toBe("strftime('%Y-%m-%d 00:00:00', datetime(\"viewed_at\", '+36000 seconds'))");
    });
});
