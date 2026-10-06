<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\ConfigurationCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;

/** @return list<array{Status, string}> */
function configurationFindings(): array
{
    $findings = iterator_to_array(app()->make(ConfigurationCheck::class)->run(), preserve_keys: false);

    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], $findings);
}

it('passes the default config', function (): void {
    expect(configurationFindings())->toBe([
        [Status::Pass, 'No settings undo each other.'],
    ]);
});

it('advises against queueing on the sync driver', function (?string $connection): void {
    config()->set('queue.default', 'sync');
    config()->set('queue.connections.sync', ['driver' => 'sync']);
    config()->set('eloquent-viewable.recording.queue.enabled', true);
    config()->set('eloquent-viewable.recording.queue.connection', $connection);

    expect(configurationFindings())->toBe([
        [Status::Advice, 'Views are queued on the `sync` connection, whose `sync` driver stores them during the request anyway.'],
    ]);
})->with([
    'the default connection' => [null],
    'a named connection' => ['sync'],
]);

it('leaves queueing alone without a queue connection', function (): void {
    config()->set('queue.default');
    config()->set('eloquent-viewable.recording.queue.enabled', true);

    expect(configurationFindings()[0][0])->toBe(Status::Pass);
});

it('accepts queueing on a connection a worker processes', function (): void {
    config()->set('queue.connections.redis', ['driver' => 'redis']);
    config()->set('eloquent-viewable.recording.queue.enabled', true);
    config()->set('eloquent-viewable.recording.queue.connection', 'redis');

    expect(configurationFindings()[0][0])->toBe(Status::Pass);
});

it('warns about a beacon without the web middleware', function (): void {
    config()->set('eloquent-viewable.recording.beacon.enabled', true);
    config()->set('eloquent-viewable.recording.beacon.middleware', ['api']);

    expect(configurationFindings()[0][0])->toBe(Status::Warning);

    config()->set('eloquent-viewable.recording.beacon.middleware', ['web']);

    expect(configurationFindings()[0][0])->toBe(Status::Pass);
});

it('warns about the rollup source without rollups', function (): void {
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    expect(configurationFindings())->toBe([
        [Status::Warning, 'Counts read through the `rollup` source, but no rollups are configured, so every count reads the views table.'],
    ]);

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    expect(configurationFindings()[0][0])->toBe(Status::Pass);
});

it('advises to list EnforceCooldown', function (): void {
    config()->set('eloquent-viewable.recording.guards', [IgnoreCrawlers::class]);

    expect(configurationFindings())->toBe([
        [Status::Advice, '`EnforceCooldown` is not listed in `recording.guards`, so `cooldown()` does nothing.'],
    ]);
});
