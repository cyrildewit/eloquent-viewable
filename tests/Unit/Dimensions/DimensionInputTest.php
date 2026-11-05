<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\FakeVisitor;

it('builds a fake input for a unit test', function (): void {
    $input = DimensionInput::fake(
        userAgent: 'Mozilla/5.0',
        referrer: 'News.YCombinator.com',
        landing: ['utm_source' => 'newsletter'],
        headers: ['CF-IPCountry' => 'NL'],
        ip: '203.0.113.7',
        collection: 'amp',
        context: ['plan' => 'pro'],
        appHosts: ['example.com'],
    );

    expect($input->visitor)->toBeInstanceOf(FakeVisitor::class)
        ->and($input->visitor->userAgent())->toBe('Mozilla/5.0')
        ->and($input->visitor->ip())->toBe('203.0.113.7')
        ->and($input->viewable)->toBeNull()
        ->and($input->collection)->toBe('amp')
        ->and($input->context)->toBe(['plan' => 'pro'])
        ->and($input->referrer)->toBe('news.ycombinator.com')
        ->and($input->landing('utm_source'))->toBe('newsletter')
        ->and($input->appHosts)->toBe(['example.com']);
});

it('reads a header by any case', function (): void {
    $input = DimensionInput::fake(headers: ['CF-IPCountry' => 'NL']);

    expect($input->header('cf-ipcountry'))->toBe('NL')
        ->and($input->header('CF-IPCOUNTRY'))->toBe('NL')
        ->and($input->header('X-Missing'))->toBeNull();
});

it('reads a landing parameter trimmed, and a blank one as missing', function (): void {
    $input = DimensionInput::fake(landing: ['utm_source' => '  google ', 'utm_medium' => '   ']);

    expect($input->landing('utm_source'))->toBe('google')
        ->and($input->landing('utm_medium'))->toBeNull()
        ->and($input->landing('utm_campaign'))->toBeNull();
});

it('drops a referrer from the application itself', function (?string $referrer, ?string $expected): void {
    expect(DimensionInput::fake(referrer: $referrer, appHosts: ['example.com', 'WWW.Shop.Example'])->externalReferrer())->toBe($expected);
})->with([
    'none' => [null, null],
    'another site' => ['news.ycombinator.com', 'news.ycombinator.com'],
    'the app' => ['example.com', null],
    'the app with www' => ['www.example.com', null],
    'another listed host without www' => ['shop.example', null],
    'a subdomain of the app' => ['blog.example.com', 'blog.example.com'],
]);

it('takes www off a host', function (): void {
    expect(DimensionInput::withoutWww('www.example.com'))->toBe('example.com')
        ->and(DimensionInput::withoutWww('wwwexample.com'))->toBe('wwwexample.com')
        ->and(DimensionInput::withoutWww('example.com'))->toBe('example.com');
});

it('reports what a fake visitor was given and no privacy signals', function (): void {
    $visitor = new FakeVisitor('Mozilla/5.0', '203.0.113.7');

    expect($visitor->id())->toBe('fake-visitor')
        ->and($visitor->viewer())->toBeNull()
        ->and($visitor->hasDoNotTrackHeader())->toBeFalse()
        ->and($visitor->hasGlobalPrivacyControl())->toBeFalse()
        ->and($visitor->isPrefetch())->toBeFalse()
        ->and($visitor->isHeadRequest())->toBeFalse();
});
