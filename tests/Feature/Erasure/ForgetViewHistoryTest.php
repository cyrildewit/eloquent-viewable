<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Erasure\Actions\ForgetViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryForgotten;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\TouchedViewables;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
});

function forgetViewHistory(Subject $subject, bool $includeGuestViews = false, int $chunk = 1000): int
{
    return app(ForgetViewHistory::class)->handle($subject, $includeGuestViews, $chunk);
}

it('deletes the views of a viewer and leaves everyone else alone', function (): void {
    $other = User::factory()->create();

    View::factory()->for($this->post, 'viewable')->by($this->user)->count(2)->create();
    View::factory()->for(Post::factory()->create(), 'viewable')->by($this->user)->create();
    $kept = View::factory()->for($this->post, 'viewable')->by($other)->create();
    $guest = View::factory()->for($this->post, 'viewable')->fromVisitor('someone')->create();

    expect($this->user->forgetViewHistory())->toBe(3)
        ->and(View::query()->pluck('id')->sort()->values()->all())->toBe(collect([$kept->id, $guest->id])->sort()->values()->all());
});

it('deletes the views a viewer made under its visitor id after the viewer columns were detached', function (): void {
    $visitor = app(VisitorIdentity::class)->ofViewer($this->user);

    View::factory()->for($this->post, 'viewable')->fromVisitor($visitor)->create();

    expect($this->user->forgetViewHistory())->toBe(1)
        ->and(View::query()->count())->toBe(0);
});

it('deletes a viewer that no longer exists by its type and key', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->create();

    $subject = Subject::viewerKey($this->user->getMorphClass(), $this->user->getKey());
    $this->user->delete();

    expect(forgetViewHistory($subject))->toBe(1)
        ->and(View::query()->count())->toBe(0);
});

it('leaves the guest views of the browsers a viewer used unless asked', function (bool $includeGuestViews, int $deleted, int $left): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->count(2)->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-2')->create();

    expect($this->user->forgetViewHistory($includeGuestViews))->toBe($deleted)
        ->and(View::query()->count())->toBe($left);
})->with([
    'by default' => [false, 1, 3],
    'with guest views' => [true, 3, 1],
]);

it('follows no anonymised visitor id to guest views', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('a:hash')->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('a:hash')->create();

    expect($this->user->forgetViewHistory(includeGuestViews: true))->toBe(1)
        ->and(View::query()->sole()->visitor)->toBe('a:hash');
});

it('deletes the views of a visitor', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->count(2)->create();
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-2')->create();

    expect(forgetViewHistory(Subject::visitor('cookie-1')))->toBe(3)
        ->and(View::query()->sole()->visitor)->toBe('cookie-2');
});

it('finds no guest views for a visitor', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->create();
    View::factory()->for($this->post, 'viewable')->create(['viewer_type' => null, 'viewer_id' => null, 'visitor' => 'cookie-2']);

    expect(forgetViewHistory(Subject::visitor('cookie-1'), includeGuestViews: true))->toBe(1)
        ->and(View::query()->sole()->visitor)->toBe('cookie-2');
});

it('deletes in chunks', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->count(5)->create();

    expect(forgetViewHistory(Subject::viewer($this->user), chunk: 2))->toBe(5)
        ->and(View::query()->count())->toBe(0);
});

it('dispatches ViewHistoryForgotten, also for a viewer without views', function (): void {
    Event::fake([ViewHistoryForgotten::class]);

    View::factory()->for($this->post, 'viewable')->by($this->user)->count(2)->create();

    $this->user->forgetViewHistory();
    User::factory()->create()->forgetViewHistory();

    Event::assertDispatchedTimes(ViewHistoryForgotten::class, 2);
    Event::assertDispatched(ViewHistoryForgotten::class, fn (ViewHistoryForgotten $event): bool => $event->views === 2
        && $event->subject->viewerType === $this->user->getMorphClass()
        && $event->subject->viewerKey === $this->user->getKey());
    Event::assertDispatched(ViewHistoryForgotten::class, fn (ViewHistoryForgotten $event): bool => $event->views === 0);
});

it('forgets the remembered counts of the viewables it touched', function (): void {
    $other = Post::factory()->create();

    View::factory()->for($this->post, 'viewable')->by($this->user)->create();
    View::factory()->for($other, 'viewable')->create();

    expect(views($this->post)->remember(60)->count())->toBe(1)
        ->and(views(Post::class)->remember(60)->count())->toBe(2);

    View::factory()->for($other, 'viewable')->count(2)->create();
    $this->user->forgetViewHistory();

    expect(views($this->post)->remember(60)->count())->toBe(0)
        ->and(views(Post::class)->remember(60)->count())->toBe(3);
});

it('forgets every remembered count once it touched too many viewables', function (): void {
    $untouched = Post::factory()->create();

    View::factory()->for($untouched, 'viewable')->create();
    expect(views($untouched)->remember(60)->count())->toBe(1);

    View::factory()->for($untouched, 'viewable')->create();

    foreach (Post::factory()->count(TouchedViewables::Limit + 2)->create() as $post) {
        View::factory()->for($post, 'viewable')->by($this->user)->create();
    }

    $this->user->forgetViewHistory();

    expect(views($untouched)->remember(60)->count())->toBe(2);
});

it('leaves the rollups as they are', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->create();

    ViewRollup::query()->insert([
        'rollup' => 'views',
        'tier' => 'day',
        'bucket_start' => Carbon::parse('2026-01-01'),
        'grouping' => 'viewable',
        'viewable_type' => $this->post->getMorphClass(),
        'viewable_id' => $this->post->getKey(),
        'views' => 1,
        'unique_visitors' => 1,
    ]);

    $this->user->forgetViewHistory();

    expect((int) ViewRollup::query()->sole()->views)->toBe(1);
});
