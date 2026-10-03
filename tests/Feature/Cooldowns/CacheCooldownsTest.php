<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Support\Facades\Config;

function cacheCooldownVisitor(string $id): Visitor
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('id')->andReturn($id);
    $visitor->allows('ip')->andReturn('10.0.0.1');
    $visitor->allows('userAgent')->andReturn(null);
    $visitor->allows('isPrefetch')->andReturn(false);

    return $visitor;
}

beforeEach(function (): void {
    Config::set('eloquent-viewable.cooldown.store', 'cache');

    $this->post = Post::factory()->create();
});

it('skips a second view inside the cooldown without touching the session', function (): void {
    $visitor = cacheCooldownVisitor('reader');

    expect(views($this->post)->useVisitor($visitor)->cooldown(10)->record())->toBeTrue()
        ->and(views($this->post)->useVisitor($visitor)->cooldown(10)->record())->toBeFalse()
        ->and(View::count())->toBe(1)
        ->and($this->app['session.store']->has('cyrildewit.eloquent-viewable.cooldowns'))->toBeFalse();
});

it('keeps the cooldowns of different visitors apart', function (): void {
    views($this->post)->useVisitor(cacheCooldownVisitor('reader'))->cooldown(10)->record();

    expect(views($this->post)->useVisitor(cacheCooldownVisitor('someone-else'))->cooldown(10)->record())->toBeTrue()
        ->and(View::count())->toBe(2);
});

it('records the view again once the cooldown has expired', function (): void {
    $visitor = cacheCooldownVisitor('reader');

    views($this->post)->useVisitor($visitor)->cooldown(10)->record();

    Carbon::setTestNow(Carbon::now()->addMinutes(10));

    expect(views($this->post)->useVisitor($visitor)->cooldown(10)->record())->toBeTrue()
        ->and(View::count())->toBe(2);
});
