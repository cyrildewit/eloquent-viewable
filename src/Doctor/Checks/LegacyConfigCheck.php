<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use Generator;
use Illuminate\Contracts\Config\Repository;

/**
 * Finds a config that was published for v8 and not published again. Laravel
 * merges a package config one level deep, so a v8 key is kept but never read,
 * and the v8 `cooldown` replaces the v9 one along with its `store`. Reads the
 * repository rather than `Support\Config`, which throws on that missing store.
 */
class LegacyConfigCheck implements Check
{
    /** The top-level keys of the v8 config, and where their values go in v9. */
    public const array Moved = [
        'cache' => 'move its values to `querying.cache`',
        'queue' => 'move its values to `recording.queue`',
        'ignore_bots' => 'remove `IgnoreCrawlers` from `recording.guards` if it was `false`',
        'honor_dnt' => 'add `IgnoreDoNotTrack` to `recording.guards` if it was `true`',
        'visitor_cookie_key' => 'move its value to `visitor.cookie.name`',
        'ignored_ip_addresses' => 'move its values to `recording.ignored_ip_addresses`',
    ];

    public function __construct(
        protected Repository $repository,
    ) {}

    public function name(): string
    {
        return 'Legacy config';
    }

    /** @return Generator<int, Finding> */
    public function run(): Generator
    {
        $config = $this->repository->get('eloquent-viewable');
        $config = is_array($config) ? $config : [];
        $found = false;

        foreach (self::Moved as $key => $move) {
            if (! array_key_exists($key, $config)) {
                continue;
            }

            $found = true;

            yield Finding::failure(
                "`{$key}` is a v8 key, which v9 ignores.",
                "Publish the config again with `--force`, then {$move}.",
            );
        }

        if (is_array($config['cooldown'] ?? null) && ! array_key_exists('store', $config['cooldown'])) {
            $found = true;

            yield Finding::failure(
                '`cooldown` has no `store`, as in a v8 config, so recording a view fails.',
                "Publish the config again with `--force`, or add `'store' => 'session'` to `cooldown`.",
            );
        }

        if (! $found) {
            yield Finding::pass('The config holds no v8 keys.');
        }
    }
}
