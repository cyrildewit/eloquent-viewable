<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Refolder;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $posts = Post::factory()->count(10)->create();

    foreach ($posts as $post) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2026-03-31 10:00:00'))->fromVisitor('bot')->create();
    }

    View::factory()->for($posts->first(), 'viewable')->viewedAt(Carbon::parse('2026-03-31 11:00:00'))->fromVisitor('bot')->create();
});

it('registers views:purge-bots', function (): void {
    expect(Artisan::all())->toHaveKey('views:purge-bots');
});

it('deletes the views in a burst', function (): void {
    $this->artisan('views:purge-bots')
        ->expectsOutputToContain('Deleted 10 views of 1 visitor viewed since 2026-03-30 12:00:00.')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(1);
});

it('counts the views on a dry run', function (): void {
    $this->artisan('views:purge-bots', ['--dry-run' => true, '--since' => '2026-03-31'])
        ->expectsOutputToContain('Would have deleted 10 views of 1 visitor viewed since 2026-03-31 00:00:00.')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(11);
});

it('takes the burst rule from the command line', function (): void {
    $this->artisan('views:purge-bots', ['--max' => 10, '--seconds' => 1])
        ->expectsOutputToContain('Deleted 0 views of 0 visitors')
        ->assertSuccessful();
});

it('deletes every view of a visitor with enough bursts', function (): void {
    $this->artisan('views:purge-bots', ['--whole-visitor' => true, '--min-bursts' => 1])
        ->expectsOutputToContain('Deleted 11 views of 1 visitor')
        ->assertSuccessful();
});

it('refuses --whole-visitor with the fingerprint identity', function (): void {
    config()->set('eloquent-viewable.visitor.identity', 'fingerprint');

    $this->artisan('views:purge-bots', ['--whole-visitor' => true])
        ->expectsOutputToContain('The --whole-visitor option cannot be used with the `fingerprint` identity')
        ->assertFailed();

    expect(View::query()->count())->toBe(11);
});

it('rejects an option that is not valid', function (array $options, string $message): void {
    $this->artisan('views:purge-bots', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(View::query()->count())->toBe(11);
})->with([
    'since' => [['--since' => 'soon'], 'The --since option must be a duration such as `7d` or a date such as `2026-01-01`.'],
    'max' => [['--max' => 'many'], 'The --max option must be a positive integer.'],
    'seconds' => [['--seconds' => '0'], 'The --seconds option must be a positive integer.'],
    'min bursts' => [['--min-bursts' => '-1'], 'The --min-bursts option must be a positive integer.'],
    'chunk' => [['--chunk' => '0'], 'The --chunk option must be a positive integer.'],
]);

it('asks before deleting in production', function (): void {
    $this->app['env'] = 'production';

    $this->artisan('views:purge-bots')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect(View::query()->count())->toBe(11);

    $this->artisan('views:purge-bots', ['--force' => true])->assertSuccessful();

    expect(View::query()->count())->toBe(1);
});

it('warns when the start was clamped', function (): void {
    app()->instance(Refolder::class, new class implements Refolder
    {
        public function floor(): CarbonInterface
        {
            return Carbon::parse('2026-03-31 00:00:00');
        }

        public function refold(CarbonInterface $from): void {}
    });

    $this->artisan('views:purge-bots')
        ->expectsOutputToContain('Started at 2026-03-31 00:00:00, because the rollups cannot be folded again before it.')
        ->assertSuccessful();
});

it('reminds to recount the counter columns', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    $this->artisan('views:purge-bots')
        ->expectsOutputToContain('Run `views:recount` to bring the counter columns up to date.')
        ->assertSuccessful();
});
