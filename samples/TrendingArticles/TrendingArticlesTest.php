<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Samples\TrendingArticles\Article;
use CyrildeWit\EloquentViewable\Samples\TrendingArticles\ShowArticle;
use CyrildeWit\EloquentViewable\Samples\TrendingArticles\TrendingArticles;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // The web middleware encrypts the session and visitor cookies.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::get('/articles/{article}', ShowArticle::class)->middleware('web');
});

/**
 * Records `$count` views on the article, each by a different reader. The test
 * session outlives a request, so it is flushed to drop the previous reader's
 * cooldowns.
 */
function readArticle(Article $article, int $count = 1): void
{
    for ($i = 0; $i < $count; $i++) {
        session()->flush();

        test()->get("/articles/{$article->id}")->assertOk();
    }
}

it('ranks the articles by their views over the past week', function (): void {
    $quiet = Article::create(['title' => 'Quiet']);
    $popular = Article::create(['title' => 'Popular']);

    readArticle($quiet);
    readArticle($popular, 3);

    $trending = app(TrendingArticles::class)->get();

    expect($trending->pluck('title')->all())->toBe(['Popular', 'Quiet'])
        ->and($trending->pluck('views_count')->all())->toBe([3, 1]);
});

it('leaves out views older than a week', function (): void {
    $evergreen = Article::create(['title' => 'Evergreen']);
    $fresh = Article::create(['title' => 'Fresh']);

    $this->travel(-10)->days();
    readArticle($evergreen, 5);
    $this->travelBack();

    readArticle($fresh);

    expect(app(TrendingArticles::class)->get()->pluck('title')->all())->toBe(['Fresh']);
});

it('counts a reader who comes back within the cooldown once', function (): void {
    $article = Article::create(['title' => 'Refreshed']);

    $this->get("/articles/{$article->id}")->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get("/articles/{$article->id}")
        ->assertOk();

    expect($article)->toHaveViewsCount(1);
});

it('keeps serving the cached ranking until it expires', function (): void {
    $first = Article::create(['title' => 'First']);
    $second = Article::create(['title' => 'Second']);

    readArticle($first, 2);
    readArticle($second);
    app(TrendingArticles::class)->get();

    readArticle($second, 5);

    expect(app(TrendingArticles::class)->get()->pluck('title')->all())->toBe(['First', 'Second']);

    $this->travel(11)->minutes();

    expect(app(TrendingArticles::class)->get()->pluck('title')->all())->toBe(['Second', 'First']);
});

it('shows an edited title without waiting for the cache', function (): void {
    $article = Article::create(['title' => 'Draft title']);
    readArticle($article);
    app(TrendingArticles::class)->get();

    $article->update(['title' => 'Final title']);

    expect(app(TrendingArticles::class)->get()->pluck('title')->all())->toBe(['Final title']);
});
