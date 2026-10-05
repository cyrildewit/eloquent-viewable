<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsAnonymised;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $this->post = Post::factory()->create();
    $this->user = User::factory()->create();
});

/** @param  array<string, mixed>|null  $context */
function retainedView(Post $post, string $viewedAt, ?string $visitor = 'visitor-1', ?Model $viewer = null, ?array $context = ['source' => 'newsletter']): View
{
    $factory = View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->state(['visitor' => $visitor])->withContext($context);

    return ($viewer instanceof Model ? $factory->by($viewer) : $factory)->create();
}

/** @param  list<'visitor'|'viewer'|'context'>  $columns */
function anonymiseBefore(string $cutoff, array $columns = ['visitor', 'viewer', 'context'], int $chunk = 100, bool $dryRun = false): RetentionRun
{
    return app(AnonymiseViews::class)->handle(Carbon::parse($cutoff), $columns, $chunk, $dryRun);
}

it('takes the visitor, viewer and context out of views before the cutoff', function (): void {
    $old = retainedView($this->post, '2026-03-01 10:00:00', viewer: $this->user);
    $recent = retainedView($this->post, '2026-03-20 10:00:00', viewer: $this->user);

    $run = anonymiseBefore('2026-03-15 00:00:00');

    $old->refresh();
    $recent->refresh();

    expect($run->views)->toBe(1)
        ->and($run->from)->toBeNull()
        ->and($run->until->toDateTimeString())->toBe('2026-03-15 00:00:00')
        ->and($run->clamped)->toBeFalse()
        ->and($old->visitor)->toStartWith('a:')->toHaveLength(66)
        ->and($old->viewer_type)->toBeNull()
        ->and($old->viewer_id)->toBeNull()
        ->and($old->context)->toBeNull()
        ->and($recent->visitor)->toBe('visitor-1')
        ->and($recent->viewer_id)->toBe($this->user->getKey())
        ->and($recent->context)->toBe(['source' => 'newsletter']);
});

it('keeps one id per visitor per day and none across days', function (): void {
    $morning = retainedView($this->post, '2026-03-01 08:00:00');
    $evening = retainedView($this->post, '2026-03-01 20:00:00');
    $other = retainedView($this->post, '2026-03-01 21:00:00', visitor: 'visitor-2');
    $nextDay = retainedView($this->post, '2026-03-02 08:00:00');

    anonymiseBefore('2026-03-15');

    expect($morning->refresh()->visitor)->toBe($evening->refresh()->visitor)
        ->not->toBe($other->refresh()->visitor)
        ->not->toBe($nextDay->refresh()->visitor)
        ->and(views($this->post)->unique()->count())->toBe(3);
});

it('never splits a day, so the cutoff is moved back to midnight', function (): void {
    $sameDay = retainedView($this->post, '2026-03-15 10:00:00');

    $run = anonymiseBefore('2026-03-15 18:00:00');

    expect($run->until->toDateTimeString())->toBe('2026-03-15 00:00:00')
        ->and($sameDay->refresh()->visitor)->toBe('visitor-1');
});

it('anonymises only the configured columns', function (): void {
    $view = retainedView($this->post, '2026-03-01 10:00:00', viewer: $this->user);

    anonymiseBefore('2026-03-15', ['context']);

    $view->refresh();

    expect($view->visitor)->toBe('visitor-1')
        ->and($view->viewer_id)->toBe($this->user->getKey())
        ->and($view->context)->toBeNull();
});

it('anonymises a view without a visitor', function (): void {
    $view = retainedView($this->post, '2026-03-01 10:00:00', visitor: null, viewer: $this->user, context: null);

    expect(anonymiseBefore('2026-03-15')->views)->toBe(1)
        ->and($view->refresh()->visitor)->toBeNull()
        ->and($view->viewer_type)->toBeNull();
});

it('hashes under the salt kept for a day still in progress and forgets it once the day is done', function (): void {
    $state = app(RetentionState::class);
    $state->put('anonymise:salt:2026-03-01', 'kept-salt');

    $view = retainedView($this->post, '2026-03-01 10:00:00');

    anonymiseBefore('2026-03-15');

    expect($view->refresh()->visitor)->toBe('a:'.hash_hmac('sha256', 'visitor-1', 'kept-salt'))
        ->and($state->get('anonymise:salt:2026-03-01'))->toBeNull();
});

it('leaves rows a previous run already did', function (): void {
    $done = retainedView($this->post, '2026-03-01 10:00:00', visitor: 'a:done', context: null);

    expect(anonymiseBefore('2026-03-15')->views)->toBe(0)
        ->and($done->refresh()->visitor)->toBe('a:done');
});

it('re-hashes no visitor when only other columns are left to anonymise', function (): void {
    $view = retainedView($this->post, '2026-03-01 10:00:00', visitor: 'a:done');

    expect(anonymiseBefore('2026-03-15')->views)->toBe(1)
        ->and($view->refresh()->visitor)->toBe('a:done')
        ->and($view->context)->toBeNull();
});

it('works through a day in chunks', function (): void {
    foreach (['visitor-1', 'visitor-2', 'visitor-3'] as $visitor) {
        retainedView($this->post, '2026-03-01 10:00:00', visitor: $visitor);
    }

    expect(anonymiseBefore('2026-03-15', chunk: 2)->views)->toBe(3)
        ->and(View::query()->where('visitor', 'like', 'a:%')->count())->toBe(3);
});

it('starts where the last run stopped', function (): void {
    retainedView($this->post, '2026-03-01 10:00:00');

    anonymiseBefore('2026-03-10');

    $late = retainedView($this->post, '2026-03-05 10:00:00');
    $new = retainedView($this->post, '2026-03-12 10:00:00');

    $run = anonymiseBefore('2026-03-15');

    expect($run->from?->toDateTimeString())->toBe('2026-03-10 00:00:00')
        ->and($run->views)->toBe(1)
        ->and($late->refresh()->visitor)->toBe('visitor-1')
        ->and($new->refresh()->visitor)->toStartWith('a:')
        ->and(app(RetentionState::class)->get(AnonymiseViews::Mark))->toBe('2026-03-15 00:00:00');
});

it('does nothing when the cutoff lies before the last run', function (): void {
    anonymiseBefore('2026-03-15');

    $view = retainedView($this->post, '2026-03-01 10:00:00');

    expect(anonymiseBefore('2026-03-10')->views)->toBe(0)
        ->and($view->refresh()->visitor)->toBe('visitor-1')
        ->and(app(RetentionState::class)->get(AnonymiseViews::Mark))->toBe('2026-03-15 00:00:00');
});

it('counts without changing anything on a dry run', function (): void {
    $view = retainedView($this->post, '2026-03-01 10:00:00');
    retainedView($this->post, '2026-03-02 10:00:00');

    $run = anonymiseBefore('2026-03-15', dryRun: true);

    expect($run->views)->toBe(2)
        ->and($run->dryRun)->toBeTrue()
        ->and($view->refresh()->visitor)->toBe('visitor-1')
        ->and(app(RetentionState::class)->get(AnonymiseViews::Mark))->toBeNull();
});

it('dispatches an event with the range and the views it changed', function (): void {
    Event::fake([ViewsAnonymised::class]);

    retainedView($this->post, '2026-03-01 10:00:00');
    retainedView($this->post, '2026-03-02 10:00:00');

    anonymiseBefore('2026-03-15');

    Event::assertDispatched(ViewsAnonymised::class, fn (ViewsAnonymised $event): bool => ! $event->from instanceof CarbonInterface
        && $event->until->toDateTimeString() === '2026-03-15 00:00:00'
        && $event->views === 2);
});

it('dispatches nothing when no view changed', function (): void {
    Event::fake([ViewsAnonymised::class]);

    anonymiseBefore('2026-03-15');

    Event::assertNotDispatched(ViewsAnonymised::class);
});

it('stops where the rollups have captured the views', function (): void {
    app()->instance(Watermarks::class, new class implements Watermarks
    {
        public function clamp(CarbonInterface $cutoff): CarbonInterface
        {
            return Carbon::parse('2026-03-05 06:00:00');
        }

        public function afterFolding(): Watermarks
        {
            return $this;
        }
    });

    $captured = retainedView($this->post, '2026-03-01 10:00:00');
    $waiting = retainedView($this->post, '2026-03-05 01:00:00');

    $run = anonymiseBefore('2026-03-15');

    expect($run->clamped)->toBeTrue()
        ->and($run->until->toDateTimeString())->toBe('2026-03-05 00:00:00')
        ->and($captured->refresh()->visitor)->toStartWith('a:')
        ->and($waiting->refresh()->visitor)->toBe('visitor-1');
});

it('forgets the remembered counts once views are anonymised', function (): void {
    retainedView($this->post, '2026-03-01 10:00:00');
    retainedView($this->post, '2026-03-02 10:00:00');

    expect(views($this->post)->unique()->remember(3600)->count())->toBe(1);

    anonymiseBefore('2026-03-15');

    expect(views($this->post)->unique()->remember(3600)->count())->toBe(2);
});

it('throws when the retention migration has not run', function (): void {
    config()->set('database.connections.bare', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('eloquent-viewable.models.view.connection', 'bare');

    anonymiseBefore('2026-03-15');
})->throws(RetentionNotInstalled::class, 'The `view_retention_state` table does not exist. Publish its migration with `php artisan vendor:publish --tag=eloquent-viewable-retention` and run `php artisan migrate`.');

it('anonymises a day in chunks that each start after the last', function (): void {
    foreach (range(1, 5) as $number) {
        retainedView($this->post, "2026-03-01 1{$number}:00:00", visitor: "visitor-{$number}");
    }

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (! str_starts_with($query->sql, 'select')) {
            return;
        }

        if (! str_contains($query->sql, 'order by')) {
            return;
        }

        $statements[] = $query->sql;
    });

    $run = anonymiseBefore('2026-03-15 00:00:00', chunk: 2);

    expect($run->views)->toBe(5)
        ->and(View::query()->where('visitor', 'like', 'a:%')->count())->toBe(5)
        ->and($statements)->toHaveCount(3)
        ->and(preg_match('/id[`"]? > \\?/', $statements[0]))->toBe(0)
        ->and($statements[1])->toMatch('/id[`"]? > \\?/')
        ->and($statements[2])->toMatch('/id[`"]? > \\?/');
});

it('splits a chunk with many visitors into statements of a hundred visitors', function (): void {
    foreach (range(1, 150) as $number) {
        retainedView($this->post, '2026-03-01 10:00:00', visitor: "visitor-{$number}");
    }

    $withoutVisitor = retainedView($this->post, '2026-03-01 11:00:00', visitor: null, viewer: $this->user);
    $updates = 0;

    DB::listen(function (QueryExecuted $query) use (&$updates): void {
        if (str_starts_with($query->sql, 'update')) {
            $updates++;
        }
    });

    $run = anonymiseBefore('2026-03-15 00:00:00', chunk: 500);

    expect($run->views)->toBe(151)
        ->and($updates)->toBe(2)
        ->and(View::query()->where('visitor', 'like', 'a:%')->distinct()->count('visitor'))->toBe(150)
        ->and($withoutVisitor->refresh()->visitor)->toBeNull()
        ->and($withoutVisitor->viewer_id)->toBeNull();
});
