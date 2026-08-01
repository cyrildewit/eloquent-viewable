<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\View;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('can destroy all views when viewable gets deleted', function (): void {
    TestHelper::createView($this->post);
    TestHelper::createView($this->post);
    TestHelper::createView($this->post);

    expect(View::count())->toBe(3);

    $this->post->delete();

    expect(View::count())->toBe(0);
});

it('does not destroy all views when viewable gets deleted and remove views on delete is set to false', function (): void {
    $this->post->removeViewsOnDelete = false;

    TestHelper::createView($this->post);
    TestHelper::createView($this->post);
    TestHelper::createView($this->post);

    expect(View::count())->toBe(3);

    $this->post->delete();

    expect(View::count())->toBe(3);
});
