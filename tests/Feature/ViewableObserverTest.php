<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('can destroy all views when viewable gets deleted', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->count(3)->create();

    expect(View::count())->toBe(3);

    $this->post->delete();

    expect(View::count())->toBe(0);
});

it('does not destroy all views when viewable gets deleted and remove views on delete is set to false', function (): void {
    $this->post->removeViewsOnDelete = false;

    ViewFactory::new()->for($this->post, 'viewable')->count(3)->create();

    expect(View::count())->toBe(3);

    $this->post->delete();

    expect(View::count())->toBe(3);
});
