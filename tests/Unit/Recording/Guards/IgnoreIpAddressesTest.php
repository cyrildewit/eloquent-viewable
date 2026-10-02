<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Config\Repository;

function ipAttempt(?string $ip): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('ip')->andReturn($ip);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('refuses listed addresses only', function (): void {
    $guard = new IgnoreIpAddresses(new Config(new Repository(['eloquent-viewable' => ['recording' => ['ignored_ip_addresses' => ['127.20.22.6', '10.10.30.40']]]])));

    expect($guard->allows(ipAttempt('127.20.22.6')))->toBeFalse()
        ->and($guard->allows(ipAttempt('10.10.30.40')))->toBeFalse()
        ->and($guard->allows(ipAttempt('192.168.1.1')))->toBeTrue()
        ->and($guard->allows(ipAttempt(null)))->toBeTrue();
});

it('allows everything when the list is empty', function (): void {
    $guard = new IgnoreIpAddresses(new Config(new Repository(['eloquent-viewable' => []])));

    expect($guard->allows(ipAttempt('127.20.22.6')))->toBeTrue();
});
