<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Doctor\Checks\RollupsCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;

/** @return list<array{Status, string, ?string}> */
function rollupFindings(): array
{
    return array_map(
        fn (Finding $finding): array => [$finding->status, $finding->summary, $finding->fix],
        iterator_to_array(app()->make(RollupsCheck::class)->run(), preserve_keys: false),
    );
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    $post = Post::factory()->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2025-06-10 10:00:00'))->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2026-02-10 10:00:00'))->create();
});

it('skips without rollups', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', []);

    expect(rollupFindings())->toBe([[Status::Skipped, 'No rollup is configured in `retention.rollups`.', null]]);
});

it('skips before the rollup migrations have run', function (): void {
    $this->mock(StateStore::class)->allows('installed')->andReturn(false);

    expect(rollupFindings())->toBe([[Status::Skipped, 'Nothing to check before the rollup migrations have run.', null]]);
});

it('passes rollups that count every view, or were not folded yet', function (): void {
    expect(rollupFindings()[0][0])->toBe(Status::Pass);

    app(FoldViews::class)->handle();

    expect(rollupFindings())->toBe([[Status::Pass, 'Every rollup counts the views it keeps.', null]]);
});

it('warns about a rollup that starts after views it should count', function (): void {
    app(FoldViews::class)->handle();

    $state = app(RollupState::class);
    $state->putSince('views', Tier::Day, Carbon::parse('2026-02-10'));
    $state->putSince('views', Tier::Month, Carbon::parse('2026-02-01'));

    expect(rollupFindings())->toBe([[
        Status::Warning,
        'The `views` rollup starts at 2026-02-01, but the views table holds views from 2025-06-10 that it does not count, so anonymising and pruning wait for them.',
        'Run `php artisan views:rollup --rollup=views --from=2025-06-10` to fold them.',
    ]]);
});

it('passes once the refold it suggests has folded those views', function (): void {
    app(FoldViews::class)->handle();

    $state = app(RollupState::class);
    $state->putSince('views', Tier::Day, Carbon::parse('2026-02-10'));
    $state->putSince('views', Tier::Month, Carbon::parse('2026-02-01'));
    app(StateStore::class)->put('anonymised', '2026-03-01 00:00:00');

    app(FoldViews::class)->handle(from: Carbon::parse('2025-06-10'), rollup: 'views');

    expect(rollupFindings()[0][0])->toBe(Status::Pass);
});
