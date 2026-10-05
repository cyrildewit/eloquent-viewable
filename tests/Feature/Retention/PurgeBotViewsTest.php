<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Refolder;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Retention\Actions\PurgeBotViews;
use CyrildeWit\EloquentViewable\Retention\Data\PurgeRun;
use CyrildeWit\EloquentViewable\Retention\Events\BotViewsPurged;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->posts = Post::factory()->count(4)->create()->all();
});

function purgeView(Post $post, string $viewedAt, string $visitor, ?Apartment $viewer = null): View
{
    $factory = View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor);

    return ($viewer instanceof Apartment ? $factory->by($viewer) : $factory)->create();
}

/** @param  list<Post>  $posts */
function burstOf(array $posts, string $viewedAt, string $visitor): void
{
    foreach ($posts as $post) {
        purgeView($post, $viewedAt, $visitor);
    }
}

function purgeBots(string $since = '2026-03-30 00:00:00', int $chunk = 100, bool $includeViewers = false, ?int $minBursts = null, bool $dryRun = false): PurgeRun
{
    return app(PurgeBotViews::class)->handle(Carbon::parse($since), 2, 2, $chunk, $includeViewers, $minBursts, $dryRun);
}

it('deletes only the views inside a burst', function (): void {
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');
    $slow = purgeView($this->posts[0], '2026-03-31 11:00:00', 'bot');
    $person = purgeView($this->posts[1], '2026-03-31 10:00:00', 'person');

    $run = purgeBots();

    expect($run->views)->toBe(4)
        ->and($run->visitors)->toBe(1)
        ->and($run->clamped)->toBeFalse()
        ->and($run->dryRun)->toBeFalse()
        ->and(View::query()->pluck('id')->sort()->values()->all())->toBe([$slow->getKey(), $person->getKey()]);
});

it('reads and deletes in chunks, across views viewed in the same second', function (): void {
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');
    burstOf($this->posts, '2026-03-31 10:00:01', 'another bot');

    expect(purgeBots(chunk: 3)->views)->toBe(8)
        ->and(View::query()->count())->toBe(0);
});

it('counts the views it would delete on a dry run', function (): void {
    Event::fake([BotViewsPurged::class]);
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');

    $run = purgeBots(dryRun: true);

    expect($run->views)->toBe(4)
        ->and($run->dryRun)->toBeTrue()
        ->and(View::query()->count())->toBe(4);

    Event::assertNotDispatched(BotViewsPurged::class);
});

it('dispatches BotViewsPurged once views are deleted', function (): void {
    Event::fake([BotViewsPurged::class]);
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');

    purgeBots();

    Event::assertDispatched(BotViewsPurged::class, fn (BotViewsPurged $event): bool => $event->views === 4
        && $event->visitors === 1
        && $event->from->toDateTimeString() === '2026-03-30 00:00:00'
        && $event->until->toDateTimeString() === '2026-03-31 12:00:00');
});

it('dispatches nothing when no burst is found', function (): void {
    Event::fake([BotViewsPurged::class]);
    purgeView($this->posts[0], '2026-03-31 10:00:00', 'person');

    expect(purgeBots()->views)->toBe(0);

    Event::assertNotDispatched(BotViewsPurged::class);
});

it('leaves views before the start alone', function (): void {
    burstOf($this->posts, '2026-03-29 10:00:00', 'bot');

    expect(purgeBots()->views)->toBe(0)
        ->and(View::query()->count())->toBe(4);
});

it('leaves views of a signed-in viewer alone unless asked', function (): void {
    $viewer = Apartment::factory()->create();

    foreach ($this->posts as $post) {
        purgeView($post, '2026-03-31 10:00:00', 'signed in', $viewer);
    }

    expect(purgeBots()->views)->toBe(0)
        ->and(purgeBots(includeViewers: true)->views)->toBe(4);
});

it('deletes every view of a visitor with enough separate bursts', function (): void {
    burstOf($this->posts, '2026-03-31 09:00:00', 'bot');
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');
    purgeView($this->posts[0], '2026-03-31 11:00:00', 'bot');
    burstOf($this->posts, '2026-03-31 10:00:00', 'tabs');

    $run = purgeBots(chunk: 3, minBursts: 2);

    expect($run->views)->toBe(9)
        ->and($run->visitors)->toBe(1)
        ->and(View::query()->distinct()->pluck('visitor')->all())->toBe(['tabs']);
});

it('counts the views of those visitors on a dry run', function (): void {
    burstOf($this->posts, '2026-03-31 09:00:00', 'bot');
    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');

    expect(purgeBots(minBursts: 2, dryRun: true)->views)->toBe(8)
        ->and(View::query()->count())->toBe(8);
});

it('starts where the rollups can be folded again and refolds from the first deleted view', function (): void {
    $refolder = new class implements Refolder
    {
        public ?CarbonInterface $refolded = null;

        public function floor(): CarbonInterface
        {
            return Carbon::parse('2026-03-31 00:00:00');
        }

        public function refold(CarbonInterface $from): void
        {
            $this->refolded = $from;
        }
    };

    app()->instance(Refolder::class, $refolder);

    burstOf($this->posts, '2026-03-30 10:00:00', 'bot');
    burstOf($this->posts, '2026-03-31 10:00:05', 'bot');

    $run = purgeBots();

    expect($run->clamped)->toBeTrue()
        ->and($run->from->toDateTimeString())->toBe('2026-03-31 00:00:00')
        ->and($run->views)->toBe(4)
        ->and($refolder->refolded?->toDateTimeString())->toBe('2026-03-31 10:00:05');
});

it('does nothing when the rollups cannot be folded again after the start', function (): void {
    app()->instance(Refolder::class, new class implements Refolder
    {
        public function floor(): CarbonInterface
        {
            return Carbon::parse('2026-04-01 00:00:00');
        }

        public function refold(CarbonInterface $from): void {}
    });

    burstOf($this->posts, '2026-03-31 10:00:00', 'bot');

    expect(purgeBots())->views->toBe(0)->clamped->toBeTrue()
        ->and(View::query()->count())->toBe(4);
});

it('takes the deleted views out of the rollups', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    burstOf($this->posts, '2026-03-30 10:00:00', 'bot');
    purgeView($this->posts[0], '2026-03-30 11:00:00', 'person');

    app(FoldViews::class)->handle();

    $rolledUp = fn (): int => (int) ViewRollup::query()->where('tier', 'day')->where('grouping', 'viewable')->sum('views');

    expect($rolledUp())->toBe(5);

    purgeBots();

    expect($rolledUp())->toBe(1);
});

it('cannot fold a month again before the views were anonymised', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    app(RetentionState::class)->putMoment(StateStore::Anonymised, Carbon::parse('2026-03-15 00:00:00'));

    expect(app(Refolder::class)->floor()?->toDateTimeString())->toBe('2026-04-01 00:00:00');
});

it('cannot fold a day again before the views were pruned', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    app(RetentionState::class)->putMoment(StateStore::Anonymised, Carbon::parse('2026-03-15 00:00:00'));

    expect(app(Refolder::class)->floor())->toBeNull();

    app(RetentionState::class)->putMoment(StateStore::Pruned, Carbon::parse('2026-03-10 06:00:00'));

    expect(app(Refolder::class)->floor()?->toDateTimeString())->toBe('2026-03-11 00:00:00');
});

it('can fold everything again without rollups', function (): void {
    expect(app(Refolder::class)->floor())->toBeNull();
});
