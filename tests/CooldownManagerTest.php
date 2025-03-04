<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\CooldownManager;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Session;

class CooldownManagerTest extends TestCase
{
    public function push_can_add_an_item(): void
    {
        $post = Post::factory()->create();
        $cooldownManager = Container::getInstance()->make(CooldownManager::class);
        $postSessionKey = Container::getInstance()
            ->make('config')
            ->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass())).'.'.$post->getKey();

        $this->assertFalse(Session::has($postSessionKey));

        $cooldownManager->push($post, Carbon::tomorrow());

        $this->assertTrue(Session::has($postSessionKey));
    }

    public function push_can_add_an_item_with_collection(): void
    {
        $post = Post::factory()->create();
        $cooldownManager = Container::getInstance()->make(CooldownManager::class);
        $postSessionKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass())).':some-collection'.'.'.$post->getKey();

        $this->assertFalse(Session::has($postSessionKey));

        $cooldownManager->push($post, Carbon::tomorrow(), 'some-collection');

        $this->assertTrue(Session::has($postSessionKey));
    }

    public function push_does_not_add_an_item_if_already_added(): void
    {
        $post = Post::factory()->create();
        $postBaseKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
        $cooldownManager = Container::getInstance()->make(CooldownManager::class);

        $cooldownManager->push($post, Carbon::tomorrow());
        $cooldownManager->push($post, Carbon::tomorrow());
        $cooldownManager->push($post, Carbon::tomorrow());

        $this->assertCount(1, Session::get($postBaseKey));
    }

    public function test_it_can_forget_expired_views(): void
    {
        $post = Post::factory()->create();
        $postNamespaceKey = Container::getInstance()->make('config')->get('eloquent-viewable.cooldown.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
        $cooldownManager = Container::getInstance()->make(CooldownManager::class);

        $cooldownManager->push($post, Carbon::today());
        $cooldownManager->push($post, Carbon::today()->addHour());
        $cooldownManager->push($post, Carbon::today()->addHours(2));

        Carbon::setTestNow(Carbon::tomorrow());

        $cooldownManager->push($post, Carbon::today()->addHours(2));

        $this->assertCount(1, Session::get($postNamespaceKey));
    }

    public function test_it_can_forget_expired_views_with_collection(): void
    {
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

        $this->assertCount(1, Session::get($postNamespacKey));
    }
}
