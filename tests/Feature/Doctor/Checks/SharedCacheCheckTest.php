<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\SharedCacheCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;

/** @return list<array{Status, string}> */
function cacheFindings(): array
{
    $findings = iterator_to_array(app()->make(SharedCacheCheck::class)->run(), preserve_keys: false);

    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], $findings);
}

beforeEach(function (): void {
    config()->set('cache.default', 'redis');
    config()->set('cache.stores.redis', ['driver' => 'redis']);
    config()->set('cache.stores.local', ['driver' => 'file']);
    config()->set('cache.stores.memory', ['driver' => 'array']);
    config()->set('session.driver', 'database');
});

it('passes the stores the default config relies on', function (): void {
    expect(cacheFindings())->toBe([
        [Status::Pass, 'Cooldowns: kept in the `database` session.'],
        [Status::Pass, 'Remembered counts: the `redis` cache store can be shared between servers.'],
    ]);
});

it('fails session cooldowns under the array session driver', function (): void {
    config()->set('session.driver', 'array');

    expect(cacheFindings()[0])->toBe([Status::Failure, 'Cooldowns: kept in the session, whose `array` driver forgets them after every request.']);
});

it('leaves session cooldowns alone without a session driver', function (): void {
    config()->set('session.driver');

    expect(cacheFindings())->toHaveCount(1);
});

it('leaves a cooldown store of your own alone', function (): void {
    config()->set('eloquent-viewable.cooldown.store', 'custom');

    expect(cacheFindings())->toBe([
        [Status::Pass, 'Remembered counts: the `redis` cache store can be shared between servers.'],
    ]);
});

it('judges the store cache cooldowns are kept in', function (?string $store, Status $status, string $summary): void {
    config()->set('eloquent-viewable.cooldown.store', 'cache');
    config()->set('eloquent-viewable.cooldown.cache.store', $store);

    expect(cacheFindings()[0])->toBe([$status, $summary]);
})->with([
    'the default' => [null, Status::Pass, 'Cooldowns: the `redis` cache store can be shared between servers.'],
    'one on disk' => ['local', Status::Warning, 'Cooldowns: the `local` cache store uses the `file` driver, which is not shared between servers.'],
    'one in memory' => ['memory', Status::Failure, 'Cooldowns: the `memory` cache store uses the `array` driver, which forgets everything after the request.'],
    'one that is not defined' => ['missing', Status::Failure, 'Cooldowns: the `missing` cache store is not defined in `config/cache.php`.'],
]);

it('judges the throttle store once the guard is listed', function (): void {
    config()->set('eloquent-viewable.recording.guards', [ThrottleVisitors::class]);
    config()->set('eloquent-viewable.recording.throttle.store', 'local');

    expect(cacheFindings())->toContain([Status::Warning, 'Throttle: the `local` cache store uses the `file` driver, which is not shared between servers.']);
});

it('judges the fingerprint salt store under the fingerprint identity', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');
    config()->set('eloquent-viewable.visitor.fingerprint.store', 'memory');

    expect(cacheFindings())->toContain([Status::Failure, 'Fingerprint salt: the `memory` cache store uses the `array` driver, which forgets everything after the request.']);
});

it('only warns or advises about the store remembered counts are kept in', function (string $store, Status $status): void {
    config()->set('eloquent-viewable.querying.cache.store', $store);

    expect(array_last(cacheFindings())[0])->toBe($status);
})->with([
    ['memory', Status::Warning],
    ['local', Status::Advice],
]);

it('says how to fix a store that is not shared', function (): void {
    config()->set('eloquent-viewable.querying.cache.store', 'local');

    $finding = array_last(iterator_to_array(app()->make(SharedCacheCheck::class)->run(), preserve_keys: false));

    expect($finding->fix)->toBe('Ignore this on a single server. Name a store every server shares, such as `redis` or `database`, in `eloquent-viewable.querying.cache.store`.');
});

it('falls back to the file store without a default', function (): void {
    config()->set('cache.default');
    config()->set('cache.stores.file', ['driver' => 'file']);

    expect(array_last(cacheFindings()))->toBe([Status::Advice, 'Remembered counts: the `file` cache store uses the `file` driver, which is not shared between servers.']);
});
