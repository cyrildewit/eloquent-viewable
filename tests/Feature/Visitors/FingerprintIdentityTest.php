<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-03 12:00:00');

    $this->post = Post::factory()->create();

    Route::middleware(AddQueuedCookiesToResponse::class)
        ->get('/posts/{post}', fn (string $post): bool => views(Post::query()->findOrFail($post))->record());
});

function visitPost(Post $post, string $ip = '192.0.2.10', string $userAgent = 'Mozilla/5.0'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeader('User-Agent', $userAgent)
        ->get('/posts/'.$post->getKey());
}

it('sets the visitor cookie with the cookie identity', function (): void {
    visitPost($this->post)->assertOk()->assertCookie('eloquent_viewable', encrypted: false);
});

it('sets no cookie with the fingerprint identity', function (): void {
    Config::set('eloquent-viewable.visitor.identity', 'fingerprint');

    visitPost($this->post)->assertOk()->assertCookieMissing('eloquent_viewable');

    expect(View::sole()->visitor)->toHaveLength(64);
});

it('counts a guest once a day', function (): void {
    Config::set('eloquent-viewable.visitor.identity', 'fingerprint');

    visitPost($this->post);
    visitPost($this->post, ip: '192.0.2.99');
    visitPost($this->post, userAgent: 'Mozilla/5.0 (iPhone)');

    Carbon::setTestNow('2026-10-04 09:00:00');
    visitPost($this->post);

    expect(views($this->post)->count())->toBe(4)
        ->and(views($this->post)->unique()->count())->toBe(3);
});
