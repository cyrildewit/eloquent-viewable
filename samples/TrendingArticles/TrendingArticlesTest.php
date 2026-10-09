<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Samples\TrendingArticles\Article;
use CyrildeWit\EloquentViewable\Samples\TrendingArticles\ShowArticle;
use CyrildeWit\EloquentViewable\Samples\TrendingArticles\TrendingArticles;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // The web middleware encrypts the session and visitor cookies.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::get('/articles/{article}', ShowArticle::class)
        ->middleware(['web', RecordViews::using('article', cooldown: 30)]);
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

/**
 * The titles of the trending articles with their views this week.
 *
 * @return list<array{string, int}>
 */
function trendingArticles(): array
{
    $entries = [];

    foreach (app(TrendingArticles::class)->get() as $entry) {
        $entries[] = [$entry->viewable->title, $entry->count];
    }

    return $entries;
}

it('ranks the articles by their views over the past week', function (): void {
    $quiet = Article::create(['title' => 'Quiet']);
    $popular = Article::create(['title' => 'Popular']);

    readArticle($quiet);
    readArticle($popular, 3);

    expect(trendingArticles())->toBe([['Popular', 3], ['Quiet', 1]]);
});

it('ranks a fresh spike above older, larger traffic', function (): void {
    $evergreen = Article::create(['title' => 'Evergreen']);
    $fresh = Article::create(['title' => 'Fresh']);

    $this->travel(-4)->days();
    readArticle($evergreen, 5);
    $this->travelBack();

    readArticle($fresh, 2);

    expect(trendingArticles())->toBe([['Fresh', 2], ['Evergreen', 5]]);
});

it('leaves out views older than a week', function (): void {
    $evergreen = Article::create(['title' => 'Evergreen']);
    $fresh = Article::create(['title' => 'Fresh']);

    $this->travel(-10)->days();
    readArticle($evergreen, 5);
    $this->travelBack();

    readArticle($fresh);

    expect(trendingArticles())->toBe([['Fresh', 1]]);
});

it('counts a reader who comes back within the cooldown once', function (): void {
    $article = Article::create(['title' => 'Refreshed']);

    $this->get("/articles/{$article->id}")->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get("/articles/{$article->id}")
        ->assertOk();

    expect($article)->toHaveViewsCount(1);
});

it('records nothing for an article that does not exist', function (): void {
    $this->get('/articles/404')->assertNotFound();

    expect(View::count())->toBe(0);
});

it('keeps serving the remembered ranking until it expires', function (): void {
    $first = Article::create(['title' => 'First']);
    $second = Article::create(['title' => 'Second']);

    readArticle($first, 2);
    readArticle($second);
    app(TrendingArticles::class)->get();

    readArticle($second, 5);

    expect(trendingArticles())->toBe([['First', 2], ['Second', 1]]);

    $this->travel(11)->minutes();

    expect(trendingArticles())->toBe([['Second', 6], ['First', 2]]);
});

it('shows an edited title without waiting for the cache', function (): void {
    $article = Article::create(['title' => 'Draft title']);
    readArticle($article);
    app(TrendingArticles::class)->get();

    $article->update(['title' => 'Final title']);

    expect(trendingArticles())->toBe([['Final title', 1]]);
});
