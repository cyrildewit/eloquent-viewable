<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Erasure\Actions\AnonymiseViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryAnonymised;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
});

function anonymisableView(Post $post, string $viewedAt, ?string $visitor, ?User $viewer = null): View
{
    $factory = View::factory()
        ->for($post, 'viewable')
        ->viewedAt(Carbon::parse($viewedAt))
        ->state(['visitor' => $visitor])
        ->withContext(['source' => 'newsletter']);

    $factory = $viewer instanceof User
        ? $factory->by($viewer)
        : $factory;

    return $factory->create();
}

it('takes the viewer, the context and the visitor id out of the views of a viewer', function (): void {
    $view = anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-1', $this->user);

    expect($this->user->anonymiseViewHistory())->toBe(1);

    $view->refresh();

    expect($view->viewer_type)->toBeNull()
        ->and($view->viewer_id)->toBeNull()
        ->and($view->context)->toBeNull()
        ->and($view->visitor)->toStartWith('a:')
        ->and($view->visitor)->not->toContain('cookie-1')
        ->and($this->user->viewed()->count())->toBe(0)
        ->and($this->post)->toHaveViewsCount(1);
});

it('keeps one visitor id within a day and none across days', function (): void {
    $morning = anonymisableView($this->post, '2026-03-01 09:00:00', 'cookie-1', $this->user);
    $evening = anonymisableView($this->post, '2026-03-01 21:00:00', 'cookie-1', $this->user);
    $otherCookie = anonymisableView($this->post, '2026-03-01 22:00:00', 'cookie-2', $this->user);
    $nextDay = anonymisableView($this->post, '2026-03-02 09:00:00', 'cookie-1', $this->user);

    $this->user->anonymiseViewHistory();

    expect($morning->refresh()->visitor)->toBe($evening->refresh()->visitor)
        ->and($otherCookie->refresh()->visitor)->not->toBe($morning->visitor)
        ->and($nextDay->refresh()->visitor)->not->toBe($morning->visitor)
        ->and($this->post->views()->distinct()->count('visitor'))->toBe(3);
});

it('splits the days on the clock of retention.rollups.timezone', function (): void {
    config()->set('eloquent-viewable.retention.rollups.timezone', 'Pacific/Auckland');

    // 10:00 and 12:00 UTC fall on 1 and 2 March in Auckland.
    $before = anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-1', $this->user);
    $after = anonymisableView($this->post, '2026-03-01 12:00:00', 'cookie-1', $this->user);

    $this->user->anonymiseViewHistory();

    expect($before->refresh()->visitor)->not->toBe($after->refresh()->visitor);
});

it('leaves a visitor id that is missing or anonymised already', function (): void {
    $missing = anonymisableView($this->post, '2026-03-01 10:00:00', null, $this->user);
    $anonymised = anonymisableView($this->post, '2026-03-01 10:00:00', 'a:hash', $this->user);

    expect($this->user->anonymiseViewHistory())->toBe(2)
        ->and($missing->refresh()->visitor)->toBeNull()
        ->and($missing->viewer_id)->toBeNull()
        ->and($anonymised->refresh()->visitor)->toBe('a:hash')
        ->and($anonymised->viewer_id)->toBeNull();
});

it('anonymises the views of a visitor', function (): void {
    $view = anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-1');
    $other = anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-2');

    expect(app(AnonymiseViewHistory::class)->handle(Subject::visitor('cookie-1')))->toBe(1)
        ->and($view->refresh()->visitor)->toStartWith('a:')
        ->and($view->context)->toBeNull()
        ->and($other->refresh()->visitor)->toBe('cookie-2');
});

it('ends on a visitor id that is anonymised already', function (): void {
    anonymisableView($this->post, '2026-03-01 10:00:00', 'a:hash');
    anonymisableView($this->post, '2026-03-01 11:00:00', 'a:hash');

    expect(app(AnonymiseViewHistory::class)->handle(Subject::visitor('a:hash'), chunk: 1))->toBe(2);
});

it('anonymises in chunks', function (): void {
    foreach (range(1, 5) as $hour) {
        anonymisableView($this->post, "2026-03-01 0{$hour}:00:00", 'cookie-1', $this->user);
    }

    expect(app(AnonymiseViewHistory::class)->handle(Subject::viewer($this->user), chunk: 2))->toBe(5)
        ->and($this->user->viewed()->count())->toBe(0)
        ->and($this->post->views()->distinct()->count('visitor'))->toBe(1);
});

it('dispatches ViewHistoryAnonymised', function (): void {
    Event::fake([ViewHistoryAnonymised::class]);

    anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-1', $this->user);

    $this->user->anonymiseViewHistory();

    Event::assertDispatched(ViewHistoryAnonymised::class, fn (ViewHistoryAnonymised $event): bool => $event->views === 1
        && $event->subject->viewerKey === $this->user->getKey());
});

it('forgets the remembered counts of the viewables it touched', function (): void {
    anonymisableView($this->post, '2026-03-01 10:00:00', 'cookie-1', $this->user);

    expect(views($this->post)->viewedBy($this->user)->remember(60)->count())->toBe(1);

    $this->user->anonymiseViewHistory();

    expect(views($this->post)->viewedBy($this->user)->remember(60)->count())->toBe(0);
});
