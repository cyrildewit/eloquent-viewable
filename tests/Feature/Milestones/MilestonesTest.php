<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Maintenance\Actions\MaintainViews;
use CyrildeWit\EloquentViewable\Milestones\Events\ViewMilestoneReached;
use CyrildeWit\EloquentViewable\Milestones\Exceptions\MilestonesNotInstalled;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post as PlainPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost as Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'cached_unique_views' => ['unique' => true],
    ]]);

    config()->set('eloquent-viewable.milestones.thresholds', [Post::class => [
        'cached_views' => [2, 5, 10],
    ]]);

    $this->post = Post::query()->create(['title' => 'Post', 'body' => 'Body']);
});

function viewPost(Post $post, int $views): void
{
    View::factory()->count($views)->for($post, 'viewable')->create();
}

function recount(): void
{
    app(RecountViews::class)->handle(chunk: 100);
}

/** @return list<array{string, int, int, list<int>}> */
function reached(): array
{
    $events = [];

    Event::assertDispatched(ViewMilestoneReached::class, function (ViewMilestoneReached $event) use (&$events): bool {
        $events[] = [$event->column, $event->milestone, $event->count, $event->passed];

        return true;
    });

    return $events;
}

it('marks what every model already passed without dispatching on the first recount', function (): void {
    viewPost($this->post, 6);

    Event::fake([ViewMilestoneReached::class]);

    recount();

    Event::assertNotDispatched(ViewMilestoneReached::class);

    viewPost($this->post, 3);
    recount();

    Event::assertNotDispatched(ViewMilestoneReached::class);
});

it('dispatches once for the highest threshold a model crossed since the last recount', function (): void {
    recount();

    Event::fake([ViewMilestoneReached::class]);

    viewPost($this->post, 6);
    recount();

    expect(reached())->toBe([['cached_views', 5, 6, [2, 5]]]);

    recount();

    Event::assertDispatchedTimes(ViewMilestoneReached::class, 1);
});

it('names the model the event is about', function (): void {
    recount();

    Event::fake([ViewMilestoneReached::class]);

    viewPost($this->post, 2);
    recount();

    Event::assertDispatched(ViewMilestoneReached::class, fn (ViewMilestoneReached $event): bool => $event->is($this->post)
        && ! $event->is(Post::query()->create(['title' => 'Other', 'body' => 'Body']))
        && ! $event->is(new PlainPost(['id' => $this->post->getKey()]))
        && $event->viewable()?->is($this->post) === true);
});

it('fires for a trashed model, which the event cannot load', function (): void {
    recount();

    Event::fake([ViewMilestoneReached::class]);

    viewPost($this->post, 2);
    $this->post->delete();
    recount();

    Event::assertDispatched(ViewMilestoneReached::class, fn (ViewMilestoneReached $event): bool => $event->is($this->post)
        && $event->viewable() === null);
});

it('cannot load a model whose type is not a model', function (): void {
    expect(new ViewMilestoneReached('missing-type', 1, 'cached_views', 2, 2, [2]))->viewable()->toBeNull()
        ->and(new ViewMilestoneReached(View::class, 1, 'cached_views', 2, 2, [2]))->viewable()->toBeNull();
});

it('does not fire again when a count drops and climbs back', function (): void {
    recount();
    viewPost($this->post, 5);
    recount();

    Event::fake([ViewMilestoneReached::class]);

    View::query()->limit(4)->delete();
    recount();

    viewPost($this->post, 4);
    recount();

    Event::assertNotDispatched(ViewMilestoneReached::class);
});

it('fires the thresholds it was armed with but covers a new one silently when they change', function (): void {
    $other = Post::query()->create(['title' => 'Other', 'body' => 'Body']);

    recount();
    viewPost($this->post, 4);
    viewPost($other, 1);

    config()->set('eloquent-viewable.milestones.thresholds', [Post::class => [
        'cached_views' => [1, 2, 5, 10],
    ]]);

    Event::fake([ViewMilestoneReached::class]);

    recount();

    Event::assertDispatchedTimes(ViewMilestoneReached::class, 1);
    Event::assertDispatched(ViewMilestoneReached::class, fn (ViewMilestoneReached $event): bool => $event->is($this->post)
        && $event->passed === [2]);

    viewPost($other, 1);
    recount();

    Event::assertDispatched(ViewMilestoneReached::class, fn (ViewMilestoneReached $event): bool => $event->is($other)
        && $event->passed === [2]);
});

it('checks only the models a recount wrote', function (): void {
    recount();

    $other = Post::query()->create(['title' => 'Other', 'body' => 'Body']);
    DB::table('posts')->where('id', $other->getKey())->update(['cached_views' => 7]);
    viewPost($this->post, 2);

    Event::fake([ViewMilestoneReached::class]);

    app(RecountViews::class)->recount(new Post, [$this->post->getKey()]);

    expect(reached())->toBe([['cached_views', 2, 2, [2]]]);
});

it('fires through views:maintain', function (): void {
    recount();
    viewPost($this->post, 2);

    Event::fake([ViewMilestoneReached::class]);

    app(MaintainViews::class)->handle(100);

    expect(reached())->toBe([['cached_views', 2, 2, [2]]]);
});

it('keeps the mark it moved when a listener throws', function (): void {
    recount();
    viewPost($this->post, 2);

    $calls = 0;

    Event::listen(ViewMilestoneReached::class, function () use (&$calls): void {
        $calls++;

        throw new RuntimeException('The mail server is down.');
    });

    expect(fn () => recount())->toThrow(RuntimeException::class, 'The mail server is down.');

    recount();

    expect($calls)->toBe(1);
});

it('leaves classes without thresholds alone', function (): void {
    config()->set('eloquent-viewable.milestones.thresholds', []);
    config()->set('eloquent-viewable.milestones.table', 'missing_milestones');

    recount();

    expect(DB::table('view_milestones')->count())->toBe(0);
});

it('fails when the table is missing', function (): void {
    config()->set('eloquent-viewable.milestones.table', 'missing_milestones');

    expect(fn () => recount())->toThrow(MilestonesNotInstalled::class, 'Milestones are configured, but the `missing_milestones` table does not exist.');
});

describe('views:seed-milestones', function (): void {
    it('is registered', function (): void {
        expect(Artisan::all())->toHaveKey('views:seed-milestones');
    });

    it('marks every model at its count, so nothing it passed fires', function (): void {
        recount();
        viewPost($this->post, 6);

        DB::table('posts')->update(['cached_views' => 6]);

        $this->artisan('views:seed-milestones')
            ->expectsOutputToContain('Marked 1 SoftDeletablePost at their current count.')
            ->assertSuccessful();

        Event::fake([ViewMilestoneReached::class]);

        recount();

        Event::assertNotDispatched(ViewMilestoneReached::class);
    });

    it('seeds one model class', function (): void {
        $this->artisan('views:seed-milestones', ['model' => Post::class])
            ->expectsOutputToContain('Marked 0 SoftDeletablePosts at their current count.')
            ->assertSuccessful();
    });

    it('refuses a model without thresholds', function (): void {
        $this->artisan('views:seed-milestones', ['model' => PlainPost::class])
            ->expectsOutputToContain('The `'.PlainPost::class.'` model has no thresholds in `milestones.thresholds`.')
            ->assertFailed();
    });

    it('says when there is nothing to seed', function (): void {
        config()->set('eloquent-viewable.milestones.thresholds', []);

        $this->artisan('views:seed-milestones')
            ->expectsOutputToContain('Nothing to seed, `milestones.thresholds` is empty.')
            ->assertSuccessful();
    });
});
