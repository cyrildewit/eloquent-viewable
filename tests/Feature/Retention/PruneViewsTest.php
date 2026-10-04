<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsPruned;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->post = Post::factory()->create();
});

function viewedOn(Post $post, string ...$viewedAt): void
{
    foreach ($viewedAt as $moment) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($moment))->create();
    }
}

function pruneBefore(string $cutoff, int $chunk = 100, bool $dryRun = false): RetentionRun
{
    return app(PruneViews::class)->handle(Carbon::parse($cutoff), $chunk, $dryRun);
}

it('deletes the views before the cutoff', function (): void {
    viewedOn($this->post, '2026-01-01 10:00:00', '2026-02-28 23:59:59', '2026-03-01 00:00:00');

    $run = pruneBefore('2026-03-01 00:00:00');

    expect($run->views)->toBe(2)
        ->and($run->from)->toBeNull()
        ->and($run->until->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and($run->clamped)->toBeFalse()
        ->and(View::query()->pluck('viewed_at')->all())->toBe(['2026-03-01 00:00:00'])
        ->and(app(RetentionState::class)->get(PruneViews::MARK))->toBe('2026-03-01 00:00:00');
});

it('deletes in chunks', function (): void {
    viewedOn($this->post, '2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04', '2026-01-05');

    expect(pruneBefore('2026-03-01', chunk: 2)->views)->toBe(5)
        ->and(View::query()->count())->toBe(0);
});

it('deletes a view that landed late behind the last run', function (): void {
    pruneBefore('2026-03-01');

    viewedOn($this->post, '2026-01-01');

    $run = pruneBefore('2026-03-02');

    expect($run->from?->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and($run->views)->toBe(1);
});

it('keeps the mark where it is when a run asks for less', function (): void {
    pruneBefore('2026-03-01');
    pruneBefore('2026-02-01');

    expect(app(RetentionState::class)->get(PruneViews::MARK))->toBe('2026-03-01 00:00:00');
});

it('counts without deleting on a dry run', function (): void {
    viewedOn($this->post, '2026-01-01', '2026-01-02');

    $run = pruneBefore('2026-03-01', dryRun: true);

    expect($run->views)->toBe(2)
        ->and($run->dryRun)->toBeTrue()
        ->and(View::query()->count())->toBe(2)
        ->and(app(RetentionState::class)->get(PruneViews::MARK))->toBeNull();
});

it('stops where the rollups have captured the views', function (): void {
    app()->instance(Watermarks::class, new class implements Watermarks
    {
        public function clamp(CarbonInterface $cutoff): CarbonInterface
        {
            return Carbon::parse('2026-01-15 00:00:00');
        }
    });

    viewedOn($this->post, '2026-01-01', '2026-02-01');

    $run = pruneBefore('2026-03-01');

    expect($run->clamped)->toBeTrue()
        ->and($run->views)->toBe(1)
        ->and(View::query()->pluck('viewed_at')->all())->toBe(['2026-02-01 00:00:00']);
});

it('dispatches an event with the range and the views it deleted', function (): void {
    Event::fake([ViewsPruned::class]);

    viewedOn($this->post, '2026-01-01');

    pruneBefore('2026-03-01');

    Event::assertDispatched(ViewsPruned::class, fn (ViewsPruned $event): bool => ! $event->from instanceof CarbonInterface
        && $event->until->toDateTimeString() === '2026-03-01 00:00:00'
        && $event->views === 1);
});

it('dispatches nothing when no view was deleted', function (): void {
    Event::fake([ViewsPruned::class]);

    pruneBefore('2026-03-01');

    Event::assertNotDispatched(ViewsPruned::class);
});

it('forgets the remembered counts once views are deleted', function (): void {
    viewedOn($this->post, '2026-01-01', '2026-03-15');

    expect(views($this->post)->remember(3600)->count())->toBe(2);

    pruneBefore('2026-03-01');

    expect(views($this->post)->remember(3600)->count())->toBe(1);
});

it('throws when the retention migration has not run', function (): void {
    config()->set('database.connections.bare', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('eloquent-viewable.models.view.connection', 'bare');

    pruneBefore('2026-03-01');
})->throws(RetentionNotInstalled::class);
