<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

const DocsPath = __DIR__.'/../../../docs/src/content/docs';

/**
 * The pages written by hand. The recipes, the upgrade guide and the config and
 * commands references are rendered from their sources by docs/scripts/sync.mjs.
 *
 * @return array<string, array{string}>
 */
function docsPages(): array
{
    $pages = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DocsPath, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = (string) $file;

        if (preg_match('/\.mdx?$/', $path) === 1 && ! docsIsGenerated($path)) {
            $pages[substr($path, strlen(DocsPath) + 1)] = [$path];
        }
    }

    ksort($pages);

    return $pages;
}

function docsIsGenerated(string $path): bool
{
    $page = substr($path, strlen(DocsPath) + 1);

    return in_array($page, ['project/upgrading.md', 'reference/configuration.md', 'reference/commands.md'], true)
        || (str_starts_with($page, 'recipes/') && $page !== 'recipes/index.md');
}

/** @return list<string> the public events, as their class names under the package namespace */
function publicEvents(): array
{
    $events = [];

    $src = __DIR__.'/../../../src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = (string) $file;

        if (basename(dirname($path)) === 'Events' && str_ends_with($path, '.php') && ! str_contains((string) file_get_contents($path), '@internal')) {
            $events[] = str_replace('/', '\\', substr($path, strlen($src) + 1, -4));
        }
    }

    sort($events);

    return $events;
}

it('finds the pages written by hand', function (): void {
    expect(docsPages())->toHaveKeys(['index.mdx', 'getting-started/quick-start.md', 'reference/events.md'])
        ->not->toHaveKey('reference/configuration.md');
});

it('only names config keys that exist', function (string $file): void {
    $config = require __DIR__.'/../../../config/eloquent-viewable.php';

    preg_match_all('/`([a-z_]+(?:\.[a-z_]+)+)`/', (string) file_get_contents($file), $keys);

    // Laravel's own keys, such as `database.redis`, are not the package's to check.
    $ours = array_filter($keys[1], fn (string $key): bool => array_key_exists(strtok($key, '.'), $config));

    expect(array_values(array_filter($ours, fn (string $key): bool => ! Arr::has($config, $key))))->toBeEmpty();
})->with(fn (): array => docsPages());

it('finds the public events in nested folders and leaves out the internal ones', function (): void {
    expect(publicEvents())->toContain('Recording\\Events\\ViewRecorded', 'Querying\\Counters\\Events\\CountersRecounted')
        ->not->toContain('Erasure\\Events\\CountsChanged');
});

it('lists every public event on the events page', function (): void {
    $page = (string) file_get_contents(DocsPath.'/reference/events.md');

    $missing = array_filter(publicEvents(), fn (string $event): bool => ! str_contains($page, "`{$event}`"));

    expect(array_values($missing))->toBeEmpty();
});

it('only lists events that exist', function (): void {
    preg_match_all('/`(\w+\\\\Events\\\\\w+)`/', (string) file_get_contents(DocsPath.'/reference/events.md'), $events);

    expect($events[1])->not->toBeEmpty()
        ->and(array_values(array_diff($events[1], publicEvents())))->toBeEmpty();
});
