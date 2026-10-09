<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Pairs\Events\ViewsPaired;
use CyrildeWit\EloquentViewable\Querying\Pairs\PairTable;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

beforeEach(function (): void {
    config()->set('eloquent-viewable.querying.pairs.enabled', true);
    config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);

    $this->travelTo(Carbon::parse('2026-03-01'));

    $this->post = Post::factory()->create();
});

/** @param  list<string>  $visitors */
function pairedBy(Model $viewable, array $visitors, string $viewedAt = '2026-02-20'): void
{
    foreach ($visitors as $visitor) {
        View::factory()->for($viewable, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse($viewedAt))->create();
    }
}

/** @return list<array{string, int|string, string, int|string, int, int, int}> */
function pairRows(): array
{
    return DB::table('view_pairs')
        ->orderBy('viewable_type')
        ->orderBy('viewable_id')
        ->orderBy('paired_type')
        ->orderBy('paired_id')
        ->get()
        ->map(fn (object $row): array => [$row->viewable_type, (int) $row->viewable_id, $row->paired_type, (int) $row->paired_id, (int) $row->visitors, (int) $row->viewable_visitors, (int) $row->paired_visitors])
        ->all();
}

it('writes both directions of every pair with the visitors of each side', function (): void {
    $apartment = Apartment::factory()->create();

    pairedBy($this->post, ['one', 'two', 'three']);
    pairedBy($apartment, ['one', 'two']);

    $this->artisan('views:pairs')
        ->expectsOutputToContain('Pairing the views of the last 90d...')
        ->expectsOutputToContain('Paired 2 viewables into 2 pairs.')
        ->assertSuccessful();

    expect(pairRows())->toBe([
        [Apartment::class, $apartment->getKey(), Post::class, $this->post->getKey(), 2, 2, 3],
        [Post::class, $this->post->getKey(), Apartment::class, $apartment->getKey(), 2, 3, 2],
    ]);
});

it('leaves out pairs below the minimum and views before the period', function (): void {
    config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 2);
    $rare = Post::factory()->create();
    $old = Post::factory()->create();

    pairedBy($this->post, ['one', 'two']);
    pairedBy($rare, ['one']);
    pairedBy($old, ['one', 'two'], '2025-01-01');

    $this->artisan('views:pairs')->assertSuccessful();

    expect(pairRows())->toBeEmpty();
});

it('keeps the pairs that share the most visitors, up to max_pairs', function (): void {
    config()->set('eloquent-viewable.querying.pairs.max_pairs', 1);
    $close = Post::factory()->create();
    $far = Post::factory()->create();

    pairedBy($this->post, ['one', 'two']);
    pairedBy($close, ['one', 'two']);
    pairedBy($far, ['one']);

    $this->artisan('views:pairs')->assertSuccessful();

    expect(array_values(array_filter(pairRows(), fn (array $row): bool => $row[1] === $this->post->getKey())))->toBe([
        [Post::class, $this->post->getKey(), Post::class, $close->getKey(), 2, 2, 2],
    ]);
});

it('replaces the pairs of the run before and announces the run', function (): void {
    Event::fake([ViewsPaired::class]);
    $other = Post::factory()->create();

    pairedBy($this->post, ['one']);
    pairedBy($other, ['one']);

    $this->artisan('views:pairs')->assertSuccessful();

    View::query()->delete();

    $this->artisan('views:pairs')->expectsOutputToContain('Paired 0 viewables into 0 pairs.')->assertSuccessful();

    expect(pairRows())->toBeEmpty();

    Event::assertDispatched(ViewsPaired::class, fn (ViewsPaired $event): bool => $event->since->equalTo(Carbon::parse('2025-12-01')) && $event->viewables === 2 && $event->pairs === 2);
});

it('does nothing while the pairs table is off', function (): void {
    config()->set('eloquent-viewable.querying.pairs.enabled', false);

    $this->artisan('views:pairs')
        ->expectsOutputToContain('Nothing to pair, `querying.pairs.enabled` is off.')
        ->assertSuccessful();
});

it('skips the run while another one holds the lock', function (): void {
    pairedBy($this->post, ['one']);
    pairedBy(Post::factory()->create(), ['one']);

    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        $this->artisan('views:pairs')
            ->expectsOutputToContain('Another run is in progress, so this one was skipped.')
            ->assertSuccessful();
    } finally {
        $lock->release();
    }

    expect(pairRows())->toBeEmpty();
});

it('publishes the pairs migration under a tag of its own', function (): void {
    $pairs = array_keys(ServiceProvider::pathsToPublish(EloquentViewableServiceProvider::class, 'eloquent-viewable-pairs'));

    expect($pairs)->toHaveCount(1)
        ->and($pairs[0])->toEndWith('create_view_pairs_table.php.stub');
});

it('serves alsoViewed() from the table unless the call names a period or collection', function (): void {
    $apartment = Apartment::factory()->create();
    $other = Post::factory()->create();

    pairedBy($this->post, ['one', 'two']);
    pairedBy($apartment, ['one', 'two']);
    pairedBy($other, ['one']);

    $this->artisan('views:pairs')->assertSuccessful();

    pairedBy($other, ['two', 'three']);

    expect(views($this->post)->alsoViewed()->entries->map(fn ($entry): array => [$entry->viewable::class, $entry->count])->all())->toBe([
        [Apartment::class, 2],
        [Post::class, 1],
    ])
        ->and(views($this->post)->alsoViewed(1)->viewables()->modelKeys())->toBe([$apartment->getKey()])
        ->and(views($this->post)->alsoViewed(among: Post::class)->viewables()->modelKeys())->toBe([$other->getKey()])
        ->and(views($this->post)->period(Period::since('2026-01-01'))->alsoViewed()->entries->map(fn ($entry): int => $entry->count)->all())->toBe([2, 2]);
});

it('counts a recipient that viewed a candidate in any way one visitor fewer', function (): void {
    $user = User::factory()->create();
    $other = Post::factory()->create();
    $apartment = Apartment::factory()->create();

    View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('laptop')->viewedAt(Carbon::parse('2026-02-20'))->create();
    pairedBy($this->post, ['one', 'two']);
    pairedBy($other, ['laptop', 'one', 'two']);
    pairedBy($apartment, ['one']);

    $this->artisan('views:pairs')->assertSuccessful();

    $pairs = fn (int $minimum, bool $includeSeen = false, ?Model $among = null): array => Container::getInstance()->make(PairTable::class)->recommendationPairs(
        new RecommendationRequest(Recipient::viewer($user), $among, 20, $minimum, null, $includeSeen),
        [['type' => $this->post->getMorphClass(), 'id' => $this->post->getKey(), 'viewed_at' => '2026-02-20 00:00:00']],
    );

    expect(array_map(fn (array $pair): array => [$pair['id'], $pair['visitors']], $pairs(1)['pairs']))->toBe([[$apartment->getKey(), 1], [$other->getKey(), 2]])
        ->and(array_column($pairs(3)['pairs'], 'id'))->toBeEmpty()
        ->and(array_column($pairs(1, among: new Apartment)['pairs'], 'id'))->toBe([$apartment->getKey()])
        ->and(array_column($pairs(1)['audiences'], 'visitors'))->toBe([3, 1, 3]);
});

it('leaves out what a viewer viewed itself, unless it is included', function (): void {
    $user = User::factory()->create();
    $other = Post::factory()->create();

    View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('laptop')->viewedAt(Carbon::parse('2026-02-20'))->create();
    View::factory()->for($other, 'viewable')->by($user)->fromVisitor('laptop')->viewedAt(Carbon::parse('2026-02-20'))->create();
    pairedBy($this->post, ['one', 'two']);
    pairedBy($other, ['one', 'two']);

    $this->artisan('views:pairs')->assertSuccessful();

    $pairs = fn (bool $includeSeen): array => Container::getInstance()->make(PairTable::class)->recommendationPairs(
        new RecommendationRequest(Recipient::viewer($user), null, 20, 1, null, $includeSeen),
        [['type' => $this->post->getMorphClass(), 'id' => $this->post->getKey(), 'viewed_at' => '2026-02-20 00:00:00']],
    );

    expect($pairs(false)['pairs'])->toBeEmpty()
        ->and(array_map(fn (array $pair): array => [$pair['id'], $pair['visitors']], $pairs(true)['pairs']))->toBe([[$other->getKey(), 2]]);
});

it('leaves out what a visitor viewed itself, unless it is included', function (): void {
    $other = Post::factory()->create();

    pairedBy($this->post, ['laptop', 'one', 'two']);
    pairedBy($other, ['laptop', 'one', 'two']);

    $this->artisan('views:pairs')->assertSuccessful();

    $pairs = fn (bool $includeSeen): array => Container::getInstance()->make(PairTable::class)->recommendationPairs(
        new RecommendationRequest(Recipient::visitor('laptop'), null, 20, 1, null, $includeSeen),
        [['type' => $this->post->getMorphClass(), 'id' => $this->post->getKey(), 'viewed_at' => '2026-02-20 00:00:00']],
    );

    expect($pairs(false)['pairs'])->toBeEmpty()
        ->and(array_map(fn (array $pair): array => [$pair['id'], $pair['visitors']], $pairs(true)['pairs']))->toBe([[$other->getKey(), 2]]);
});
