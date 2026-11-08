<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\LegacyConfigCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use Illuminate\Config\Repository;

/**
 * @param  array<string, mixed>|null  $config
 * @return list<array{Status, string, ?string}>
 */
function legacyConfigFindings(?array $config): array
{
    $check = new LegacyConfigCheck(new Repository(['eloquent-viewable' => $config]));

    return array_map(
        fn (Finding $finding): array => [$finding->status, $finding->summary, $finding->fix],
        iterator_to_array($check->run(), preserve_keys: false),
    );
}

it('passes a v9 config', function (): void {
    $config = require __DIR__.'/../../../config/eloquent-viewable.php';

    expect(legacyConfigFindings($config))->toBe([
        [Status::Pass, 'The config holds no v8 keys.', null],
    ]);
});

it('passes without a config', function (): void {
    expect(legacyConfigFindings(null))->toBe([
        [Status::Pass, 'The config holds no v8 keys.', null],
    ]);
});

it('names where each v8 key went', function (string $key, string $move): void {
    expect(legacyConfigFindings([$key => null, 'cooldown' => ['store' => 'session']]))->toBe([
        [Status::Failure, "`{$key}` is a v8 key, which v9 ignores.", "Publish the config again with `--force`, then {$move}."],
    ]);
})->with([
    'cache' => ['cache', 'move its values to `querying.cache`'],
    'queue' => ['queue', 'move its values to `recording.queue`'],
    'ignore_bots' => ['ignore_bots', 'remove `IgnoreCrawlers` from `recording.guards` if it was `false`'],
    'honor_dnt' => ['honor_dnt', 'add `IgnoreDoNotTrack` to `recording.guards` if it was `true`'],
    'visitor_cookie_key' => ['visitor_cookie_key', 'move its value to `visitor.cookie.name`'],
    'ignored_ip_addresses' => ['ignored_ip_addresses', 'move its values to `recording.ignored_ip_addresses`'],
]);

it('fails on a cooldown without a store', function (): void {
    expect(legacyConfigFindings(['cooldown' => ['key' => 'cyrildewit.eloquent-viewable.cooldowns']]))->toBe([
        [Status::Failure, '`cooldown` has no `store`, as in a v8 config, so recording a view fails.', "Publish the config again with `--force`, or add `'store' => 'session'` to `cooldown`."],
    ]);
});

it('reports every v8 key of a whole v8 config', function (): void {
    $findings = legacyConfigFindings([
        'models' => ['view' => ['table_name' => 'views', 'connection' => null]],
        'cache' => ['lifetime_in_minutes' => 60 * 24],
        'queue' => ['enabled' => false],
        'cooldown' => ['key' => 'cyrildewit.eloquent-viewable.cooldowns'],
        'ignore_bots' => true,
        'honor_dnt' => false,
        'visitor_cookie_key' => 'eloquent_viewable',
        'ignored_ip_addresses' => [],
    ]);

    expect(array_column($findings, 0))->each->toBe(Status::Failure)
        ->and($findings)->toHaveCount(7);
});
