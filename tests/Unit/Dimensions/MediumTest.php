<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\Medium;
use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;

it('names the kind of source', function (?string $referrer, array $landing, ?string $expected): void {
    expect(new Medium(SourceList::shipped())->resolve(DimensionInput::fake(referrer: $referrer, landing: $landing, appHosts: ['example.com'])))->toBe($expected);
})->with([
    'a search engine' => ['www.bing.com', [], 'organic'],
    'a social network' => ['l.facebook.com', [], 'social'],
    'webmail' => ['mail.google.com', [], 'email'],
    'an aggregator' => ['news.ycombinator.com', [], 'referral'],
    'an unknown host' => ['blog.example.org', [], 'referral'],
    'no referrer' => [null, [], 'direct'],
    'the app itself' => ['example.com', [], 'direct'],
    'a utm_medium, lowercased' => ['www.bing.com', ['utm_medium' => 'CPC'], 'cpc'],
    'a utm_source without a medium' => ['www.bing.com', ['utm_source' => 'newsletter'], null],
    'a utm_source that is a known host' => [null, ['utm_source' => 'chatgpt.com'], 'referral'],
    'a ref that is a known host' => [null, ['ref' => 'news.ycombinator.com'], 'referral'],
]);
