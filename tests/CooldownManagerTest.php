<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\CooldownManager;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Session;

test('push can add an item', function (): void {
    $post = Post::factory()->create();
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);
    $postSessionKey = Container::getInstance()
        ->make('config')
        ->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass())).'.'.$post->getKey();

    expect(Session::has($postSessionKey))->toBeFalse();

    $cooldownManager->push($post, Carbon::tomorrow());

    expect(Session::has($postSessionKey))->toBeTrue();
});

test('push can add an item with collection', function (): void {
    $post = Post::factory()->create();
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);
    $postSessionKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass())).':some-collection'.'.'.$post->getKey();

    expect(Session::has($postSessionKey))->toBeFalse();

    $cooldownManager->push($post, Carbon::tomorrow(), 'some-collection');

    expect(Session::has($postSessionKey))->toBeTrue();
});

test('push does not add an item if already added', function (): void {
    $post = Post::factory()->create();
    $postBaseKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);

    $cooldownManager->push($post, Carbon::tomorrow());
    $cooldownManager->push($post, Carbon::tomorrow());
    $cooldownManager->push($post, Carbon::tomorrow());

    expect(Session::get($postBaseKey))->toHaveCount(1);
});

it('can forget expired views', function (): void {
    $post = Post::factory()->create();
    $postNamespaceKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);

    $cooldownManager->push($post, Carbon::today());
    $cooldownManager->push($post, Carbon::today()->addHour());
    $cooldownManager->push($post, Carbon::today()->addHours(2));

    Carbon::setTestNow(Carbon::tomorrow());

    $cooldownManager->push($post, Carbon::today()->addHours(2));

    expect(Session::get($postNamespaceKey))->toHaveCount(1);
});

it('can forget expired views with collection', function (): void {
    $post = Post::factory()->create();
    $postNamespacKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);

    $cooldownManager->push($post, Carbon::today());
    $cooldownManager->push($post, Carbon::today(), 'some-collection');
    $cooldownManager->push($post, Carbon::today()->addHour());
    $cooldownManager->push($post, Carbon::today()->addHours(2));
    $cooldownManager->push($post, Carbon::today()->addHours(2), 'some-collection');

    Carbon::setTestNow(Carbon::tomorrow());

    $cooldownManager->push($post, Carbon::today()->addHours(2));

    expect(Session::get($postNamespacKey))->toHaveCount(1);
});

it('can forget expired views when expires at is stored as a string', function (): void {
    $post = Post::factory()->create();
    $postNamespaceKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
    $postSessionKey = $postNamespaceKey.'.'.$post->getKey();
    $cooldownManager = Container::getInstance()->make(CooldownManager::class);

    // Simulate what the JSON session serializer produces on a subsequent
    // request: expires_at comes back as an ISO-8601 string, not a Carbon.
    Session::put($postSessionKey, [
        'viewable_id' => $post->getKey(),
        'expires_at' => Carbon::yesterday()->toJSON(),
    ]);

    $cooldownManager->push($post, Carbon::tomorrow());

    expect(Session::get($postNamespaceKey))->toHaveCount(1);
});
