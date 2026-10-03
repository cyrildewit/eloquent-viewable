<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('starts every call from a fresh builder', function (): void {
    expect(Views::forViewable($this->post))->not->toBe(Views::forViewable($this->post));
});

it('leaves nothing set in one chain for the next', function (): void {
    $other = Post::factory()->create();

    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
    View::factory()->for($this->post, 'viewable')->inCollection('custom')->create();
    View::factory()->for($other, 'viewable')->fromVisitor('visitor_one')->count(2)->create();

    expect(Views::forViewable($this->post)->collection('custom')->count())->toBe(1)
        ->and(Views::forViewable($this->post)->count())->toBe(3)
        ->and(Views::forViewables([$this->post, $other])->unique()->counts()->all())
        ->toBe([$this->post->getKey() => 2, $other->getKey() => 1])
        ->and(Views::forViewables([$this->post, $other])->counts()->all())
        ->toBe([$this->post->getKey() => 3, $other->getKey() => 2]);
});

it('calls macros registered through the facade', function (): void {
    Views::macro('facadeTotal', fn (): int => $this->count());

    View::factory()->for($this->post, 'viewable')->count(2)->create();

    expect(Views::forViewable($this->post)->facadeTotal())->toBe(2);
});

it('records into the fake once faked', function (): void {
    $fake = Views::fake();

    Views::forViewable($this->post)->record();

    $fake->assertRecorded($this->post, 1);
    expect(View::query()->count())->toBe(0);
});

it('can still be mocked', function (): void {
    Views::shouldReceive('count')->once()->andReturn(42);

    expect(Views::count())->toBe(42);
});
