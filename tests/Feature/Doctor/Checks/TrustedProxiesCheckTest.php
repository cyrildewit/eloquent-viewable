<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\TrustedProxiesCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;

/** @return list<Finding> */
function proxyFindings(): array
{
    return iterator_to_array(app()->make(TrustedProxiesCheck::class)->run(), preserve_keys: false);
}

beforeEach(function (): void {
    config()->set('eloquent-viewable.recording.guards', []);
});

afterEach(function (): void {
    TrustProxies::flushState();
});

it('skips when nothing reads the address', function (): void {
    expect(proxyFindings()[0]->status)->toBe(Status::Skipped);
});

it('skips IgnoreIpAddresses without addresses', function (): void {
    config()->set('eloquent-viewable.recording.guards', [IgnoreIpAddresses::class]);
    config()->set('eloquent-viewable.recording.ignored_ip_addresses', []);

    expect(proxyFindings()[0]->status)->toBe(Status::Skipped);
});

it('warns when the fingerprint reads the address and no proxy is trusted', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');

    $finding = proxyFindings()[0];

    expect($finding->status)->toBe(Status::Warning)
        ->and($finding->summary)->toBe('The IP address of the visitor is read by the `fingerprint` identity, but no proxy is trusted. Behind a load balancer or CDN, every visitor then has the address of the proxy.')
        ->and($finding->fix)->toContain('trustProxies(at: [...])');
});

it('names every reader of the address', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');
    config()->set('eloquent-viewable.recording.guards', [IgnoreIpAddresses::class, IgnoreBursts::class]);
    config()->set('eloquent-viewable.recording.ignored_ip_addresses', ['10.0.0.0/8']);

    expect(proxyFindings()[0]->summary)->toStartWith('The IP address of the visitor is read by the `fingerprint` identity, `IgnoreIpAddresses` and `IgnoreBursts`,');
});

it('leaves the burst guard out when it keys on the visitor only', function (): void {
    config()->set('eloquent-viewable.recording.guards', [IgnoreBursts::class]);
    config()->set('eloquent-viewable.recording.bursts.by', ['visitor']);

    expect(proxyFindings()[0]->status)->toBe(Status::Skipped);
});

it('passes when the proxies are trusted', function (): void {
    config()->set('eloquent-viewable.recording.guards', [IgnoreIpAddresses::class]);
    config()->set('eloquent-viewable.recording.ignored_ip_addresses', ['10.0.0.1']);

    TrustProxies::at(['10.0.0.2']);

    expect(proxyFindings()[0])
        ->status->toBe(Status::Pass)
        ->summary->toBe('The IP address of the visitor is read by `IgnoreIpAddresses`, and the proxies in front of the app are trusted.');
});

it('advises against trusting every proxy', function (string $proxies): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');

    TrustProxies::at($proxies);

    expect(proxyFindings()[0]->status)->toBe(Status::Advice);
})->with(['*', '**']);

it('reads the proxies the HTTP kernel configures', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');

    app()->forgetInstance(Kernel::class);
    app()->afterResolving(Kernel::class, function (): void {
        TrustProxies::at('10.0.0.2');
    });

    expect(proxyFindings()[0]->status)->toBe(Status::Pass);
});

it('reads the proxies without an HTTP kernel', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');

    app()->offsetUnset(Kernel::class);

    TrustProxies::at('10.0.0.2');

    expect(proxyFindings()[0]->status)->toBe(Status::Pass);
});
