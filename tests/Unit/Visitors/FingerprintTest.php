<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use CyrildeWit\EloquentViewable\Visitors\Fingerprint;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepositoryContract;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-03 12:00:00');

    $this->cache = new CacheRepository(new ArrayStore);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function fingerprint(CacheRepositoryContract $cache, ?string $store = null): Fingerprint
{
    $config = new Config(new Repository(['eloquent-viewable' => ['visitor' => ['fingerprint' => ['store' => $store, 'key' => 'salt']]]]));

    $factory = Mockery::mock(CacheFactory::class);
    $factory->allows('store')->with($store)->andReturn($cache);

    return new Fingerprint($config, $factory);
}

function fingerprintVisitor(?string $ip = '192.0.2.10', ?string $userAgent = 'Mozilla/5.0'): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->shouldNotReceive('id');
    $visitor->allows('ip')->andReturn($ip);
    $visitor->allows('userAgent')->andReturn($userAgent);

    return $visitor;
}

it('hashes the network and user agent under the salt of the day', function (): void {
    $this->cache->put('salt:2026-10-03', 'the-salt');

    expect(fingerprint($this->cache)->of(fingerprintVisitor()))
        ->toBe(hash_hmac('sha256', '192.0.2.0|Mozilla/5.0', 'the-salt'));
});

it('gives the same visitor the same id all day', function (): void {
    $first = fingerprint($this->cache)->of(fingerprintVisitor());

    Carbon::setTestNow('2026-10-03 23:59:59');

    expect(fingerprint($this->cache)->of(fingerprintVisitor()))->toBe($first)->toHaveLength(64);
});

it('gives the same visitor a new id the next day', function (): void {
    $today = fingerprint($this->cache)->of(fingerprintVisitor());

    Carbon::setTestNow('2026-10-04 00:00:00');

    expect(fingerprint($this->cache)->of(fingerprintVisitor()))->not->toBe($today);
});

it('lets the salt of the day expire at midnight', function (): void {
    fingerprint($this->cache)->of(fingerprintVisitor());

    expect($this->cache->get('salt:2026-10-03'))->toBeString()->toHaveLength(64);

    Carbon::setTestNow('2026-10-04 00:00:00');

    expect($this->cache->has('salt:2026-10-03'))->toBeFalse();
});

it('ignores the last byte of an IPv4 address and the last ten of an IPv6 address', function (string $ip, string $sameNetwork, string $otherNetwork): void {
    $fingerprint = fingerprint($this->cache);

    expect($fingerprint->of(fingerprintVisitor($ip)))
        ->toBe($fingerprint->of(fingerprintVisitor($sameNetwork)))
        ->not->toBe($fingerprint->of(fingerprintVisitor($otherNetwork)));
})->with([
    'IPv4' => ['192.0.2.10', '192.0.2.250', '192.0.3.10'],
    'IPv6' => ['2001:db8:1::1', '2001:db8:1:ffff::2', '2001:db8:2::1'],
    'IPv4 mapped into IPv6' => ['::ffff:192.0.2.10', '::ffff:192.0.2.250', '::ffff:192.0.3.10'],
]);

it('hashes an IPv4 address mapped into IPv6 as the IPv4 address', function (): void {
    $fingerprint = fingerprint($this->cache);

    expect($fingerprint->of(fingerprintVisitor('::ffff:192.0.2.10')))->toBe($fingerprint->of(fingerprintVisitor('192.0.2.10')));
});

it('tells user agents apart', function (): void {
    $fingerprint = fingerprint($this->cache);

    expect($fingerprint->of(fingerprintVisitor(userAgent: 'Mozilla/5.0')))
        ->not->toBe($fingerprint->of(fingerprintVisitor(userAgent: 'curl/8.0')));
});

it('hashes a missing or unreadable address and user agent as empty', function (): void {
    $this->cache->put('salt:2026-10-03', 'the-salt');
    $fingerprint = fingerprint($this->cache);

    expect($fingerprint->of(fingerprintVisitor(null, null)))
        ->toBe(hash_hmac('sha256', '|', 'the-salt'))
        ->toBe($fingerprint->of(fingerprintVisitor('not an address', null)));
});

it('reads the salt from the configured cache store', function (): void {
    $this->cache->put('salt:2026-10-03', 'the-salt');

    expect(fingerprint($this->cache, 'redis')->of(fingerprintVisitor()))
        ->toBe(hash_hmac('sha256', '192.0.2.0|Mozilla/5.0', 'the-salt'));
});

it('hashes under the salt another request stored first', function (): void {
    $cache = Mockery::mock(CacheRepositoryContract::class);
    $cache->expects('get')->with('salt:2026-10-03')->twice()->andReturn(null, 'their-salt');
    $cache->expects('add')->withArgs(fn (string $key, string $salt, Carbon $expires): bool => $key === 'salt:2026-10-03'
        && $expires->toDateTimeString() === '2026-10-04 00:00:00')->andReturnFalse();

    expect(fingerprint($cache)->of(fingerprintVisitor()))
        ->toBe(hash_hmac('sha256', '192.0.2.0|Mozilla/5.0', 'their-salt'));
});

it('hashes under its own salt when the cache keeps none', function (): void {
    $cache = Mockery::mock(CacheRepositoryContract::class);
    $cache->allows('get')->andReturnNull();
    $cache->allows('add')->andReturnFalse();

    expect(fingerprint($cache)->of(fingerprintVisitor()))->toHaveLength(64);
});
