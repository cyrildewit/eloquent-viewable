<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;

it('names a referring host and its medium', function (string $host, ?array $expected): void {
    expect(SourceList::shipped()->find($host))->toBe($expected);
})->with([
    'a listed host' => ['duckduckgo.com', ['DuckDuckGo', 'organic']],
    'with www' => ['www.bing.com', ['Bing', 'organic']],
    'in another case' => ['News.YCombinator.com', ['Hacker News', 'referral']],
    'a subdomain of a listed host' => ['l.facebook.com', ['Facebook', 'social']],
    'a deeper subdomain' => ['m.old.reddit.com', ['Reddit', 'social']],
    'a country domain' => ['www.google.nl', ['Google', 'organic']],
    'a second-level country domain' => ['google.co.uk', ['Google', 'organic']],
    'a subdomain of a country domain' => ['news.google.co.uk', ['Google', 'organic']],
    'a full host before its parent' => ['mail.google.com', ['Gmail', 'email']],
    'a full host before a wildcard' => ['gemini.google.com', ['Gemini', 'referral']],
    'an Android app' => ['com.google.android.gm', ['Gmail', 'email']],
    'a short link' => ['t.co', ['X', 'social']],
    'an unknown host' => ['blog.example.org', null],
    'a top-level domain alone' => ['nl', null],
]);

it('reads a utm_source spelling as a source name', function (): void {
    $sources = SourceList::shipped();

    expect($sources->alias('fb'))->toBe('Facebook')
        ->and($sources->alias('HN'))->toBe('Hacker News')
        ->and($sources->alias('newsletter'))->toBeNull();
});

it('lays the hosts and aliases in config over the shipped list', function (): void {
    $sources = SourceList::fromConfig(new Config(new Repository(['eloquent-viewable' => ['dimensions' => [
        'sources' => ['News.Example.com' => ['Example News', 'referral'], 'bing.com' => ['Microsoft', 'organic']],
        'source_aliases' => ['NL' => 'Newsletter'],
    ]]])));

    expect($sources->find('news.example.com'))->toBe(['Example News', 'referral'])
        ->and($sources->find('bing.com'))->toBe(['Microsoft', 'organic'])
        ->and($sources->find('duckduckgo.com'))->toBe(['DuckDuckGo', 'organic'])
        ->and($sources->alias('nl'))->toBe('Newsletter')
        ->and($sources->alias('fb'))->toBe('Facebook');
});

it('ships a well-formed list', function (): void {
    /** @var array{hosts: array<string, mixed>, aliases: array<string, mixed>} $list */
    $list = require __DIR__.'/../../../../resources/dimensions/sources.php';

    expect($list['hosts'])->not->toBeEmpty();

    foreach ($list['hosts'] as $host => $source) {
        expect($host)->toBe(strtolower($host))
            ->and($host)->not->toStartWith('www.')
            ->and($source)->toHaveCount(2)
            ->and($source[1])->toBeIn(['organic', 'social', 'email', 'referral']);
    }

    foreach ($list['aliases'] as $alias => $name) {
        expect($alias)->toBe(strtolower($alias))
            ->and($name)->toBeString();
    }
});
