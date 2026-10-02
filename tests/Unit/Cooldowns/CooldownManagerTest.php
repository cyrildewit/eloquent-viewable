<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

const COOLDOWN_NAMESPACE = 'cyrildewit.eloquent-viewable.cooldowns.app-models-post';

function cooldownViewable(int $key = 1): Viewable
{
    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn($key);
    $viewable->allows('getMorphClass')->andReturn('App\Models\Post');

    return $viewable;
}

beforeEach(function (): void {
    $config = new Config(new Repository([
        'eloquent-viewable' => require __DIR__.'/../../../config/eloquent-viewable.php',
    ]));

    $this->session = new Store('testing', new ArraySessionHandler(120));
    $this->cooldownManager = new CooldownManager($config, $this->session);
    $this->post = cooldownViewable();
});

test('push can add an item', function (): void {
    $expiresAt = Carbon::tomorrow();

    expect($this->session->has(COOLDOWN_NAMESPACE.'.1'))->toBeFalse()
        ->and($this->cooldownManager->push($this->post, $expiresAt))->toBeTrue()
        ->and($this->session->get(COOLDOWN_NAMESPACE.'.1'))->toBe([
            'viewable_id' => 1,
            'expires_at' => $expiresAt,
        ]);
});

test('push can add an item with collection', function (): void {
    expect($this->session->has(COOLDOWN_NAMESPACE.':some-collection.1'))->toBeFalse();

    $this->cooldownManager->push($this->post, Carbon::tomorrow(), 'some-collection');

    expect($this->session->has(COOLDOWN_NAMESPACE.':some-collection.1'))->toBeTrue();
});

test('push does not add an item if already added', function (): void {
    expect($this->cooldownManager->push($this->post, Carbon::tomorrow()))->toBeTrue()
        ->and($this->cooldownManager->push($this->post, Carbon::tomorrow()))->toBeFalse()
        ->and($this->cooldownManager->push($this->post, Carbon::tomorrow()))->toBeFalse()
        ->and($this->session->get(COOLDOWN_NAMESPACE))->toHaveCount(1);
});

it('keeps separate cooldowns per viewable', function (): void {
    $this->cooldownManager->push($this->post, Carbon::tomorrow());
    $this->cooldownManager->push(cooldownViewable(2), Carbon::tomorrow());

    expect($this->session->get(COOLDOWN_NAMESPACE))->toHaveCount(2);
});

it('can forget expired views', function (): void {
    $this->cooldownManager->push($this->post, Carbon::today());
    $this->cooldownManager->push($this->post, Carbon::today()->addHour());
    $this->cooldownManager->push($this->post, Carbon::today()->addHours(2));

    Carbon::setTestNow(Carbon::tomorrow());

    expect($this->cooldownManager->push($this->post, Carbon::today()->addHours(2)))->toBeTrue()
        ->and($this->session->get(COOLDOWN_NAMESPACE))->toHaveCount(1);
});

it('can forget expired views with collection', function (): void {
    $this->cooldownManager->push($this->post, Carbon::today());
    $this->cooldownManager->push($this->post, Carbon::today(), 'some-collection');
    $this->cooldownManager->push($this->post, Carbon::today()->addHour());
    $this->cooldownManager->push($this->post, Carbon::today()->addHours(2));
    $this->cooldownManager->push($this->post, Carbon::today()->addHours(2), 'some-collection');

    Carbon::setTestNow(Carbon::tomorrow());

    $this->cooldownManager->push($this->post, Carbon::today()->addHours(2));

    expect($this->session->get(COOLDOWN_NAMESPACE))->toHaveCount(1);
});

it('can forget expired views when expires at is stored as a string', function (): void {
    // Simulate what the JSON session serializer produces on a subsequent
    // request: expires_at comes back as an ISO-8601 string, not a Carbon.
    $this->session->put(COOLDOWN_NAMESPACE.'.1', [
        'viewable_id' => 1,
        'expires_at' => Carbon::yesterday()->toJSON(),
    ]);

    expect($this->cooldownManager->push($this->post, Carbon::tomorrow()))->toBeTrue()
        ->and($this->session->get(COOLDOWN_NAMESPACE))->toHaveCount(1);
});

it('only forgets the cooldowns that have expired', function (): void {
    $expired = cooldownViewable(1);
    $active = cooldownViewable(2);

    $this->cooldownManager->push($expired, Carbon::now()->addMinute());
    $this->cooldownManager->push($active, Carbon::now()->addDay());

    Carbon::setTestNow(Carbon::now()->addHour());

    // Any push prunes the expired cooldowns in the namespace first.
    $this->cooldownManager->push(cooldownViewable(3), Carbon::tomorrow());

    expect($this->session->has(COOLDOWN_NAMESPACE.'.1'))->toBeFalse()
        ->and($this->session->has(COOLDOWN_NAMESPACE.'.2'))->toBeTrue()
        ->and($this->session->has(COOLDOWN_NAMESPACE.'.3'))->toBeTrue()
        ->and($this->cooldownManager->push($active, Carbon::tomorrow()))->toBeFalse();
});

it('reports a running cooldown without starting one', function (): void {
    expect($this->cooldownManager->isActive($this->post))->toBeFalse()
        ->and($this->cooldownManager->isActive($this->post))->toBeFalse();

    $this->cooldownManager->start($this->post, Carbon::tomorrow());

    expect($this->cooldownManager->isActive($this->post))->toBeTrue()
        ->and($this->cooldownManager->isActive($this->post, 'some-collection'))->toBeFalse();
});

it('no longer reports a cooldown once it has expired', function (): void {
    $this->cooldownManager->start($this->post, Carbon::now()->addMinute());

    Carbon::setTestNow(Carbon::now()->addMinutes(2));

    expect($this->cooldownManager->isActive($this->post))->toBeFalse()
        ->and($this->session->get(COOLDOWN_NAMESPACE))->toBe([]);
});
