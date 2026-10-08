<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use Illuminate\Http\Request;

it('reads the referring host and the query of a page request', function (): void {
    $arrival = Arrival::fromRequest(Request::create('https://example.com/posts/1?utm_source=fb&tags[]=a&page=2', server: ['HTTP_REFERER' => 'https://News.YCombinator.com/item?id=1']));

    expect($arrival->referrer)->toBe('news.ycombinator.com')
        ->and($arrival->landing)->toBe(['utm_source' => 'fb', 'page' => '2']);
});

it('reads a request without a referrer', function (): void {
    expect(Arrival::fromRequest(Request::create('https://example.com/'))->referrer)->toBeNull();
});

it('keeps only ref and the utm parameters of what the beacon posts', function (): void {
    $arrival = Arrival::fromUrls('https://t.co/abc', 'utm_source=newsletter&utm_campaign=launch&ref=hn&token=secret&utm_term[]=nested');

    expect($arrival->referrer)->toBe('t.co')
        ->and($arrival->landing)->toBe(['utm_source' => 'newsletter', 'utm_campaign' => 'launch', 'ref' => 'hn']);
});

it('reads nothing posted as nothing', function (): void {
    $arrival = Arrival::fromUrls(null, null);

    expect($arrival->referrer)->toBeNull()
        ->and($arrival->landing)->toBeEmpty();
});

it('reads the host of a referrer', function (?string $url, ?string $expected): void {
    expect(Arrival::hostOf($url))->toBe($expected);
})->with([
    'a URL' => ['https://www.Example.com/path?q=1', 'www.example.com'],
    'with a port' => ['https://example.com:8443/', 'example.com'],
    'an Android app' => ['android-app://com.google.android.gm/', 'com.google.android.gm'],
    'empty' => ['', null],
    'a path alone' => ['/posts/1', null],
    'malformed' => ['http:///example.com', null],
    'none' => [null, null],
]);
