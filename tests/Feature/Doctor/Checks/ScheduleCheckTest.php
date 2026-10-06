<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\ScheduleCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Maintenance\Jobs\MaintainViewsJob;
use CyrildeWit\EloquentViewable\Recording\Jobs\FlushBufferedViewsJob;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups\NewsletterViews;
use Illuminate\Console\Scheduling\Schedule;

/** @return list<array{Status, string}> */
function scheduleFindings(): array
{
    $findings = iterator_to_array(app()->make(ScheduleCheck::class)->run(), preserve_keys: false);

    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], $findings);
}

function schedule(): Schedule
{
    return app()->make(Schedule::class);
}

it('skips both commands when nothing needs them', function (): void {
    expect(scheduleFindings())->toBe([
        [Status::Skipped, '`views:maintain` has nothing to do: no rollups, retention or counter columns are configured.'],
        [Status::Skipped, '`views:flush` has nothing to do: the `redis` store driver is not in use.'],
    ]);
});

it('warns when maintenance is needed but not scheduled', function (string $key, mixed $value): void {
    config()->set("eloquent-viewable.{$key}", $value);

    expect(scheduleFindings()[0])->toBe([Status::Warning, '`views:maintain` is not scheduled, so rollups, retention and counter columns are not kept up to date.']);
})->with([
    'rollup tiers' => ['retention.rollups.tiers', ['day' => null]],
    'custom rollups' => ['retention.rollups.custom', [NewsletterViews::class]],
    'counter columns' => ['querying.counters', [Post::class => ['cached_views']]],
    'anonymising' => ['retention.anonymise.after', '30d'],
    'pruning' => ['retention.prune.after', '1y'],
]);

it('passes maintenance scheduled on one server', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    schedule()->command('views:maintain')->hourly()->onOneServer();

    expect(scheduleFindings()[0])->toBe([Status::Pass, 'Maintenance is scheduled on one server.']);
});

it('accepts the maintenance commands scheduled one by one', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    schedule()->command('views:prune --chunk=1000')->daily()->onOneServer();

    expect(scheduleFindings()[0][0])->toBe(Status::Pass);
});

it('accepts the maintenance job, which runs one at a time', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    schedule()->job(new MaintainViewsJob)->hourly();

    expect(scheduleFindings()[0])->toBe([Status::Pass, 'Maintenance is scheduled as a job, which runs one at a time.']);
});

it('advises to run maintenance on one server', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    schedule()->command('views:maintain')->hourly();

    expect(scheduleFindings()[0])->toBe([Status::Advice, 'Maintenance is scheduled, but not on one server, so every server runs it.']);
});

it('does not mistake another command for maintenance', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '1y');

    schedule()->command('views:maintainer')->hourly()->onOneServer();
    schedule()->call(fn (): null => null);

    expect(scheduleFindings()[0][0])->toBe(Status::Warning);
});

it('warns when the redis store is not flushed', function (): void {
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    expect(scheduleFindings()[1])->toBe([Status::Warning, '`views:flush` is not scheduled, so buffered views never reach the views table.']);
});

it('passes the flush scheduled without overlapping', function (): void {
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    schedule()->command('views:flush')->everyMinute()->withoutOverlapping();

    expect(scheduleFindings()[1])->toBe([Status::Pass, 'The flush is scheduled without overlapping.']);
});

it('accepts the flush job', function (): void {
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    schedule()->job(new FlushBufferedViewsJob)->everyMinute()->withoutOverlapping();

    expect(scheduleFindings()[1][0])->toBe(Status::Pass);
});

it('advises against an overlapping flush', function (): void {
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    schedule()->command('views:flush --batch=500')->everyMinute();

    expect(scheduleFindings()[1])->toBe([Status::Advice, 'The flush is scheduled, but may overlap a run that has not finished.']);
});
