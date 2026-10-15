<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Concerns\HasViewHistory;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
});

it('has a relation to the views it made, newest first', function (): void {
    $other = Post::factory()->create();

    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    View::factory()->for($other, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-03 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->by(User::factory()->create())->create();
    View::factory()->for($this->post, 'viewable')->create();

    expect($this->user->viewed())->toBeInstanceOf(MorphMany::class)
        ->and($this->user->viewed()->count())->toBe(2)
        ->and($this->user->viewed()->with('viewable')->get()->pluck('viewable.id'))->toEqual(keysOf($other, $this->post));
});

it('reads the views through the configured view model', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->create();

    expect($this->user->viewed()->sole())->toBeInstanceOf(View::class);
});

it('knows whether it viewed a viewable', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->create();
    View::factory()->for(Post::factory()->create(), 'viewable')->by(User::factory()->create())->create();

    expect($this->user->hasViewed($this->post))->toBeTrue()
        ->and($this->user->hasViewed(Post::factory()->create()))->toBeFalse()
        ->and(User::factory()->create()->hasViewed($this->post))->toBeFalse();
});

it('knows whether it viewed any viewable of a type', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->create();

    expect($this->user->hasViewed(new Post))->toBeTrue()
        ->and($this->user->hasViewed(new Apartment))->toBeFalse();
});

it('narrows hasViewed() to a period and a collection', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->inCollection('sidebar')->create();

    expect($this->user->hasViewed($this->post, Period::create('2026-09-01', '2026-09-02')))->toBeTrue()
        ->and($this->user->hasViewed($this->post, Period::since('2026-09-02')))->toBeFalse()
        ->and($this->user->hasViewed($this->post, collection: 'sidebar'))->toBeTrue()
        ->and($this->user->hasViewed($this->post, collection: 'footer'))->toBeFalse();
});

it('reports when it last viewed a viewable', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-03 12:30:00'))->create();
    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-02 10:00:00'))->inCollection('sidebar')->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-09 10:00:00'))->create();

    expect($this->user->lastViewedAt($this->post)?->toDateTimeString())->toBe('2026-09-03 12:30:00')
        ->and($this->user->lastViewedAt($this->post, 'sidebar')?->toDateTimeString())->toBe('2026-09-02 10:00:00')
        ->and($this->user->lastViewedAt(Post::factory()->create()))->toBeNull();
});

it('works on any model, not only users', function (): void {
    $apartment = new class extends Apartment
    {
        use HasViewHistory;

        protected $table = 'apartments';
    };
    $apartment = $apartment->newQuery()->create(['name' => 'Loft', 'description' => 'Bright']);

    View::factory()->for($this->post, 'viewable')->by($apartment)->create();

    expect($apartment->hasViewed($this->post))->toBeTrue()
        ->and($apartment->viewed()->count())->toBe(1);
});
