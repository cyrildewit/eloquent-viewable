<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Cooldowns\Stores\SessionStore;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

const SESSION_COOLDOWN_KEY = 'cyrildewit.eloquent-viewable.cooldowns';

beforeEach(function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $this->session = new Store('testing', new ArraySessionHandler(120));
    $this->store = new SessionStore($this->session, SESSION_COOLDOWN_KEY);
});

it('has no cooldown that was never started', function (): void {
    expect($this->store->has('post-1'))->toBeFalse();
});

it('keeps a started cooldown under the configured key', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));

    expect($this->store->has('post-1'))->toBeTrue()
        ->and($this->store->has('post-2'))->toBeFalse()
        ->and($this->session->get(SESSION_COOLDOWN_KEY))->toBe([
            'post-1' => Carbon::now()->addMinutes(10)->getTimestamp(),
        ]);
});

it('keeps every cooldown in one array', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));
    $this->store->put('post-2', Carbon::now()->addMinutes(10));

    expect($this->session->get(SESSION_COOLDOWN_KEY))->toHaveKeys(['post-1', 'post-2']);
});

it('replaces the expiry of a cooldown started again', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));
    $this->store->put('post-1', Carbon::now()->addMinutes(20));

    expect($this->session->get(SESSION_COOLDOWN_KEY))->toBe([
        'post-1' => Carbon::now()->addMinutes(20)->getTimestamp(),
    ]);
});

it('ends a cooldown once its expiry is reached', function (): void {
    $this->store->put('post-1', Carbon::now()->addMinutes(10));

    Carbon::setTestNow(Carbon::now()->addMinutes(10)->subSecond());

    expect($this->store->has('post-1'))->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addSecond());

    expect($this->store->has('post-1'))->toBeFalse();
});

it('drops every expired cooldown from the session when read', function (): void {
    $this->store->put('expired', Carbon::now()->addMinute());
    $this->store->put('running', Carbon::now()->addDay());

    Carbon::setTestNow(Carbon::now()->addHour());

    expect($this->store->has('something-else'))->toBeFalse()
        ->and($this->session->get(SESSION_COOLDOWN_KEY))->toBe([
            'running' => Carbon::now()->subHour()->addDay()->getTimestamp(),
        ]);
});

it('drops entries it did not write', function (mixed $stored): void {
    $this->session->put(SESSION_COOLDOWN_KEY, $stored);

    expect($this->store->has('post-1'))->toBeFalse();

    $this->store->put('post-2', Carbon::now()->addMinute());

    expect($this->session->get(SESSION_COOLDOWN_KEY))->toBe([
        'post-2' => Carbon::now()->addMinute()->getTimestamp(),
    ]);
})->with([
    'a v8 cooldown' => [['post-1' => ['viewable_id' => 1, 'expires_at' => '2030-01-01 00:00:00']]],
    'not an array' => ['cooldowns'],
]);

it('leaves the session alone when nothing expired', function (): void {
    $session = Mockery::mock(Session::class);
    $session->expects('get')->with(SESSION_COOLDOWN_KEY, [])->andReturn(['post-1' => Carbon::now()->addMinute()->getTimestamp()]);
    $session->shouldNotReceive('put');

    expect(new SessionStore($session, SESSION_COOLDOWN_KEY)->has('post-1'))->toBeTrue();
});
