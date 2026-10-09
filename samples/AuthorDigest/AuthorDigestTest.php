<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Samples\AuthorDigest\Author;
use CyrildeWit\EloquentViewable\Samples\AuthorDigest\Essay;
use CyrildeWit\EloquentViewable\Samples\AuthorDigest\SendWeeklyDigests;
use CyrildeWit\EloquentViewable\Samples\AuthorDigest\ViewsDigest;
use CyrildeWit\EloquentViewable\Samples\AuthorDigest\WeeklyDigest;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    // Monday, an hour after the digests of the week of 5 October go out in
    // UTC.
    $this->travelTo(Carbon::parse('2026-10-12 09:00'));

    Notification::fake();
});

function author(string $timezone = 'UTC'): Author
{
    return Author::create(['name' => 'Ada', 'email' => 'ada@example.com', 'timezone' => $timezone]);
}

function essay(Author $author, string $title = 'On indexes'): Essay
{
    return Essay::create(['author_id' => $author->id, 'title' => $title]);
}

/**
 * Stores `$count` views at a chosen time, in UTC, the way the essay pages
 * would have recorded them.
 */
function viewEssay(Essay $essay, string $at, int $count = 1): void
{
    View::factory()->count($count)->for($essay, 'viewable')->viewedAt(Carbon::parse($at))->create();
}

function sendDigests(): int
{
    return app(SendWeeklyDigests::class)();
}

function digest(int $current, int $previous): WeeklyDigest
{
    $week = CarbonImmutable::parse('2026-10-05');

    return new WeeklyDigest(
        week: $week,
        views: ViewComparison::between(
            $current,
            $previous,
            Period::create($week, $week->addWeek()),
            Period::create($week->subWeek(), $week),
        ),
        top: [['title' => 'On indexes', 'views' => 3_100], ['title' => 'On naming', 'views' => 1]],
    );
}

it('sends each author the views of their week against the week before', function (): void {
    $author = author();
    $indexes = essay($author, 'On indexes');
    $naming = essay($author, 'On naming');
    $caching = essay($author, 'On caching');
    $queues = essay($author, 'On queues');
    essay($author, 'On nothing');

    viewEssay($indexes, '2026-10-05 00:00', 2);
    viewEssay($naming, '2026-10-08 12:00', 5);
    viewEssay($caching, '2026-10-11 23:59');
    viewEssay($queues, '2026-10-10 12:00');
    viewEssay($indexes, '2026-09-30 12:00', 4);
    // This week so far and the week before last are in neither count.
    viewEssay($naming, '2026-10-12 08:00', 10);
    viewEssay($naming, '2026-09-27 12:00', 10);
    // Nor are another author's essays.
    viewEssay(essay(author()), '2026-10-06 12:00', 10);

    expect(sendDigests())->toBe(2);

    Notification::assertSentTo($author, function (ViewsDigest $notification): bool {
        expect($notification->digest)
            ->week->toDateString()->toBe('2026-10-05')
            ->views->current->toBe(9)
            ->views->previous->toBe(4)
            ->views->percent->toBe(125.0)
            ->top->toBe([
                ['title' => 'On naming', 'views' => 5],
                ['title' => 'On indexes', 'views' => 2],
                ['title' => 'On caching', 'views' => 1],
            ]);

        return true;
    });
});

it('leaves essays without views out of the top', function (): void {
    $author = author();
    viewEssay(essay($author, 'On indexes'), '2026-10-06 12:00');
    essay($author, 'On naming');

    sendDigests();

    Notification::assertSentTo(
        $author,
        fn (ViewsDigest $notification): bool => $notification->digest->top === [['title' => 'On indexes', 'views' => 1]],
    );
});

it('skips an author whose essays were not viewed that week', function (): void {
    $author = author();
    viewEssay(essay($author), '2026-09-30 12:00');

    expect(sendDigests())->toBe(0);

    Notification::assertNothingSent();
    expect($author->fresh()?->digested_week)->toBe('2026-10-05');
});

it('sends a week only once', function (): void {
    $author = author();
    viewEssay(essay($author), '2026-10-06 12:00');

    sendDigests();
    $this->travel(1)->hour();
    sendDigests();

    Notification::assertSentToTimes($author, ViewsDigest::class, 1);
});

it('sends the next week the Monday after', function (): void {
    $author = author();
    $essay = essay($author);
    viewEssay($essay, '2026-10-06 12:00');
    viewEssay($essay, '2026-10-13 12:00', 3);

    sendDigests();
    $this->travelTo(Carbon::parse('2026-10-19 08:00'));
    sendDigests();

    Notification::assertSentToTimes($author, ViewsDigest::class, 2);
    Notification::assertSentTo(
        $author,
        fn (ViewsDigest $notification): bool => $notification->digest->views->current === 3,
    );
});

it('waits for Monday morning on the author\'s clock', function (): void {
    // 09:00 in UTC is two in the morning in Los Angeles.
    $author = author('America/Los_Angeles');
    viewEssay(essay($author), '2026-10-09 12:00');

    expect(sendDigests())->toBe(0);

    // 08:00 in Los Angeles.
    $this->travelTo(Carbon::parse('2026-10-12 15:00'));

    expect(sendDigests())->toBe(1);
    Notification::assertSentTo($author, ViewsDigest::class);
});

it('counts whole weeks on the author\'s clock when it goes back an hour', function (): void {
    // Amsterdam leaves summer time on 25 October, so the week of 19 October
    // runs from 22:00 UTC on the 18th to 23:00 UTC on the 25th, 169 hours,
    // and the week before it from 22:00 UTC on the 11th.
    $this->travelTo(Carbon::parse('2026-10-26 08:00'));

    $author = author('Europe/Amsterdam');
    $essay = essay($author);
    $author->update(['digested_week' => '2026-10-12']);

    viewEssay($essay, '2026-10-11 21:30');
    viewEssay($essay, '2026-10-18 21:30');
    viewEssay($essay, '2026-10-18 22:30');
    viewEssay($essay, '2026-10-25 22:30', 2);

    sendDigests();

    Notification::assertSentTo($author, function (ViewsDigest $notification): bool {
        expect($notification->digest->views)->current->toBe(3)->previous->toBe(1);

        return true;
    });
});

it('writes the numbers into the mail', function (): void {
    $mail = new ViewsDigest(digest(4_210, 3_759))->toMail(author());

    expect($mail)
        ->subject->toBe('Your week on the blog: 4,210 views')
        ->greeting->toBe('Hi Ada,')
        ->introLines->toBe([
            'Your essays got 4,210 views in the week of 5 October, up 12% on the week before.',
            '1. On indexes: 3,100 views',
            '2. On naming: 1 view',
        ]);
});

it('says how the week compares', function (int $current, int $previous, string $trend): void {
    $mail = new ViewsDigest(digest($current, $previous))->toMail(author());

    expect($mail->introLines[0])->toEndWith($trend);
})->with([
    'down' => [90, 120, 'down 25% on the week before.'],
    'the same' => [12, 12, 'the same as the week before.'],
    'nothing before' => [12, 0, 'and none the week before.'],
]);

it('survives the queue', function (): void {
    $notification = unserialize(serialize(new ViewsDigest(digest(12, 6))));

    expect($notification->digest)
        ->week->toDateString()->toBe('2026-10-05')
        ->views->percent->toBe(100.0);
});
