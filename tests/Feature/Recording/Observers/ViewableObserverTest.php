<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\KeepsViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\KeepsViewsSoftDeletablePost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\PropertyKeepsViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\PropertyRemovesViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletableView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('can destroy all views when viewable gets deleted', function (): void {
    View::factory()->for($this->post, 'viewable')->count(3)->create();

    expect(View::count())->toBe(3);

    $this->post->delete();

    expect(View::count())->toBe(0);
});

it('keeps the views when a viewable that opts out gets deleted', function (): void {
    $post = KeepsViewsPost::create(['title' => 'Title', 'body' => 'Body']);

    View::factory()->for($post, 'viewable')->count(3)->create();

    expect(View::count())->toBe(3);

    $post->delete();

    expect(View::count())->toBe(3);
});

/** @return list<string> */
function deprecationsOf(Closure $callback): array
{
    $messages = [];

    set_error_handler(function (int $level, string $message) use (&$messages): bool {
        $messages[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $messages;
}

it('warns that the v8 removeViewsOnDelete property is deprecated', function (): void {
    $deprecations = deprecationsOf(fn (): PropertyKeepsViewsPost => new PropertyKeepsViewsPost);

    expect($deprecations)->toBe([
        'The $removeViewsOnDelete property of '.PropertyKeepsViewsPost::class.' is deprecated and will no longer be read in version 10. Override shouldRemoveViewsOnDelete() instead.',
    ]);
});

it('keeps the views of a viewable that sets the v8 removeViewsOnDelete property to false', function (): void {
    deprecationsOf(fn (): PropertyKeepsViewsPost => new PropertyKeepsViewsPost);

    $post = PropertyKeepsViewsPost::create(['title' => 'Title', 'body' => 'Body']);

    View::factory()->for($post, 'viewable')->count(3)->create();

    $post->delete();

    expect(View::count())->toBe(3);
});

it('destroys the views of a viewable that sets the v8 removeViewsOnDelete property to true', function (): void {
    deprecationsOf(fn (): PropertyRemovesViewsPost => new PropertyRemovesViewsPost);

    $post = PropertyRemovesViewsPost::create(['title' => 'Title', 'body' => 'Body']);

    View::factory()->for($post, 'viewable')->count(3)->create();

    $post->delete();

    expect(View::count())->toBe(0);
});

describe('soft deletable viewable', function (): void {
    beforeEach(function (): void {
        $this->softDeletablePost = SoftDeletablePost::create(['title' => 'Title', 'body' => 'Body']);

        View::factory()->for($this->softDeletablePost, 'viewable')->count(3)->create();
    });

    it('keeps the views when viewable gets soft deleted', function (): void {
        $this->softDeletablePost->delete();

        expect($this->softDeletablePost->trashed())->toBeTrue()
            ->and(View::count())->toBe(3);
    });

    it('keeps the views when viewable gets restored', function (): void {
        $this->softDeletablePost->delete();
        $this->softDeletablePost->restore();

        expect($this->softDeletablePost)->toHaveViewsCount(3);
    });

    it('destroys the views when viewable gets force deleted', function (): void {
        $this->softDeletablePost->forceDelete();

        expect(View::count())->toBe(0);
    });

    it('destroys the views when a soft deleted viewable gets force deleted', function (): void {
        $this->softDeletablePost->delete();
        $this->softDeletablePost->forceDelete();

        expect(View::count())->toBe(0);
    });

    it('keeps the views when a viewable that opts out gets force deleted', function (): void {
        $post = KeepsViewsSoftDeletablePost::create(['title' => 'Title', 'body' => 'Body']);
        View::factory()->for($post, 'viewable')->count(3)->create();

        $post->forceDelete();

        expect(View::query()->whereMorphedTo('viewable', $post)->count())->toBe(3);
    });

    it('soft deletes the views when the view model uses soft deletes', function (): void {
        Schema::table('views', function (Blueprint $table): void {
            $table->softDeletes();
        });

        $this->app['config']->set('eloquent-viewable.models.view.class', SoftDeletableView::class);

        $this->softDeletablePost->forceDelete();

        expect(SoftDeletableView::count())->toBe(0)
            ->and(SoftDeletableView::withTrashed()->count())->toBe(3);
    });
});
