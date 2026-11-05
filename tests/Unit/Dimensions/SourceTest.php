<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Dimensions\Sources\SourceList;

beforeEach(function (): void {
    $this->source = new Source(SourceList::shipped());
});

it('names where the visitor came from', function (?string $referrer, array $landing, string $expected): void {
    expect($this->source->resolve(DimensionInput::fake(referrer: $referrer, landing: $landing, appHosts: ['example.com'])))->toBe($expected);
})->with([
    'a search engine' => ['www.google.com', [], 'Google'],
    'a country domain' => ['www.google.de', [], 'Google'],
    'an aggregator' => ['news.ycombinator.com', [], 'Hacker News'],
    'a short link' => ['t.co', [], 'X'],
    'an unknown host' => ['www.blog.example.org', [], 'blog.example.org'],
    'no referrer' => [null, [], 'Direct'],
    'the app itself' => ['www.example.com', [], 'Direct'],
    'a utm_source' => ['www.google.com', ['utm_source' => 'newsletter'], 'newsletter'],
    'a ref' => [null, ['ref' => 'producthunt'], 'Product Hunt'],
    'utm_source before ref' => [null, ['utm_source' => 'fb', 'ref' => 'hn'], 'Facebook'],
    'a utm_source that is a host' => [null, ['utm_source' => 'chatgpt.com'], 'ChatGPT'],
    'a blank utm_source' => ['t.co', ['utm_source' => '  '], 'X'],
]);

it('takes the shared options', function (): void {
    $source = new Source(SourceList::shipped(), personal: true, maxValues: 5, json: 'context->source');

    expect($source->personal())->toBeTrue()
        ->and($source->maxValues())->toBe(5)
        ->and($source->storage()->target('source'))->toBe('context->source');
});
