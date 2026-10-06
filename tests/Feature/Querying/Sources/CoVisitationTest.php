<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->seed = Post::factory()->create();
    $this->other = Post::factory()->create();
});

/** @param  list<string>  $visitors */
function viewedByVisitors(Model $viewable, array $visitors, string $viewedAt = '2026-01-10', ?string $collection = null): void
{
    foreach ($visitors as $visitor) {
        View::factory()->for($viewable, 'viewable')->fromVisitor($visitor)->inCollection($collection)->viewedAt(Carbon::parse($viewedAt))->create();
    }
}

function viewedByUser(Model $viewable, User $user, string $viewedAt = '2026-01-10', string $visitor = 'own-browser'): void
{
    View::factory()->for($viewable, 'viewable')->by($user)->fromVisitor($visitor)->viewedAt(Carbon::parse($viewedAt))->create();
}

/** @return array<string, mixed> */
function pairsFor(Recipient $recipient, ViewsQuery $query = new ViewsQuery, ?Model $among = null, int $seeds = 20, int $minimum = 1, ?int $maxVisitors = null, bool $includeSeen = false): array
{
    return Container::getInstance()->make(DatabaseSource::class)
        ->recommendationPairs(new RecommendationRequest($recipient, $among, $seeds, $minimum, $maxVisitors, $includeSeen), $query);
}

it('reads the seeds, the pairs their visitors make and the audiences of both', function (): void {
    $apartment = Apartment::factory()->create();

    viewedByUser($this->seed, $this->user);
    viewedByVisitors($this->seed, ['one', 'two', 'three']);
    viewedByVisitors($this->other, ['one', 'two', 'stranger']);
    viewedByVisitors($apartment, ['three']);

    expect(pairsFor(Recipient::viewer($this->user)))->toEqual([
        'seeds' => [['type' => Post::class, 'id' => $this->seed->getKey(), 'viewed_at' => '2026-01-10 00:00:00']],
        'pairs' => [
            ['seed_type' => Post::class, 'seed_id' => $this->seed->getKey(), 'type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
            ['seed_type' => Post::class, 'seed_id' => $this->seed->getKey(), 'type' => Post::class, 'id' => $this->other->getKey(), 'visitors' => 2],
        ],
        'audiences' => [
            ['type' => Post::class, 'id' => $this->seed->getKey(), 'visitors' => 4],
            ['type' => Post::class, 'id' => $this->other->getKey(), 'visitors' => 3],
            ['type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
        ],
    ]);
});

it('takes the most recently viewed seeds first and stops at the number asked for', function (): void {
    $older = Post::factory()->create();

    viewedByUser($older, $this->user, '2026-01-01');
    viewedByUser($this->seed, $this->user, '2026-01-05');
    viewedByUser($older, $this->user, '2026-01-03');

    expect(array_column(pairsFor(Recipient::viewer($this->user))['seeds'], 'id'))->toBe([$this->seed->getKey(), $older->getKey()])
        ->and(array_column(pairsFor(Recipient::viewer($this->user), seeds: 1)['seeds'], 'id'))->toBe([$this->seed->getKey()]);
});

it('returns nothing for a recipient without views', function (): void {
    viewedByVisitors($this->seed, ['one']);

    expect(pairsFor(Recipient::viewer($this->user)))->toBe(['seeds' => [], 'pairs' => [], 'audiences' => []]);
});

it('returns the seeds alone when no pair reaches the minimum', function (): void {
    viewedByUser($this->seed, $this->user);
    viewedByVisitors($this->seed, ['one']);
    viewedByVisitors($this->other, ['one']);

    $pairs = pairsFor(Recipient::viewer($this->user), minimum: 2);

    expect($pairs['seeds'])->toHaveCount(1)
        ->and($pairs['pairs'])->toBe([])
        ->and($pairs['audiences'])->toBe([]);
});

it('never counts the recipient, on any browser it was signed in on, as a visitor of a pair', function (): void {
    viewedByUser($this->seed, $this->user, visitor: 'laptop');
    View::factory()->for($this->seed, 'viewable')->by($this->user)->fromVisitor('phone')->create();
    viewedByVisitors($this->other, ['laptop', 'phone', 'one']);
    viewedByVisitors($this->seed, ['one']);

    expect(pairsFor(Recipient::viewer($this->user))['pairs'][0]['visitors'])->toBe(1);
});

it('leaves out what the recipient viewed before, unless it is included', function (): void {
    viewedByUser($this->seed, $this->user, '2026-01-10');
    viewedByUser($this->other, $this->user, '2025-01-01');
    viewedByVisitors($this->seed, ['one']);
    viewedByVisitors($this->other, ['one']);

    $period = new ViewsQuery(Period::since('2026-01-01'));

    expect(pairsFor(Recipient::viewer($this->user), $period)['pairs'])->toBe([])
        ->and(array_column(pairsFor(Recipient::viewer($this->user), $period, includeSeen: true)['pairs'], 'id'))->toBe([$this->other->getKey()]);
});

it('never pairs a seed with another seed', function (): void {
    viewedByUser($this->seed, $this->user);
    viewedByUser($this->other, $this->user);
    viewedByVisitors($this->seed, ['one']);
    viewedByVisitors($this->other, ['one']);

    expect(pairsFor(Recipient::viewer($this->user), includeSeen: true)['pairs'])->toBe([]);
});

it('recommends for a visitor id', function (): void {
    viewedByVisitors($this->seed, ['guest', 'one']);
    viewedByVisitors($this->other, ['guest', 'one']);
    $third = Post::factory()->create();
    viewedByVisitors($third, ['one']);

    $pairs = pairsFor(Recipient::visitor('guest'));

    expect(array_column($pairs['seeds'], 'id'))->toBe([$this->seed->getKey(), $this->other->getKey()])
        ->and(array_column($pairs['pairs'], 'id'))->toBe([$third->getKey(), $third->getKey()])
        ->and(array_column($pairs['pairs'], 'visitors'))->toBe([1, 1]);
});

it('ranks among one type', function (): void {
    $apartment = Apartment::factory()->create();

    viewedByUser($this->seed, $this->user);
    viewedByVisitors($this->seed, ['one']);
    viewedByVisitors($this->other, ['one']);
    viewedByVisitors($apartment, ['one']);

    expect(array_column(pairsFor(Recipient::viewer($this->user), among: new Apartment)['pairs'], 'type'))->toBe([Apartment::class]);
});

it('reads only the most recent visitors of each seed', function (): void {
    viewedByUser($this->seed, $this->user);
    viewedByVisitors($this->seed, ['early'], '2026-01-01');
    viewedByVisitors($this->seed, ['late'], '2026-01-20');
    viewedByVisitors($this->other, ['early', 'late']);

    expect(pairsFor(Recipient::viewer($this->user), maxVisitors: 1)['pairs'][0]['visitors'])->toBe(1)
        ->and(pairsFor(Recipient::viewer($this->user), maxVisitors: 2)['pairs'][0]['visitors'])->toBe(2);
});

it('applies the period and collection to every side', function (): void {
    viewedByUser($this->seed, $this->user, '2026-02-10');
    viewedByVisitors($this->seed, ['january'], '2026-01-10');
    viewedByVisitors($this->seed, ['february'], '2026-02-10', 'sidebar');
    viewedByVisitors($this->other, ['january', 'february'], '2026-02-10');

    expect(pairsFor(Recipient::viewer($this->user))['pairs'][0]['visitors'])->toBe(2)
        ->and(pairsFor(Recipient::viewer($this->user), new ViewsQuery(Period::since('2026-02-01')))['pairs'][0]['visitors'])->toBe(1)
        ->and(pairsFor(Recipient::viewer($this->user), new ViewsQuery(collection: 'sidebar'))['seeds'])->toBe([])
        ->and(pairsFor(Recipient::visitor('nobody')))->toBe(['seeds' => [], 'pairs' => [], 'audiences' => []]);
});

it('reads the pairs from the pairs table when it serves the query', function (): void {
    Config::set('eloquent-viewable.querying.pairs.enabled', true);
    Config::set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);
    $this->travelTo(Carbon::parse('2026-01-20'));

    viewedByUser($this->seed, $this->user);
    viewedByVisitors($this->seed, ['one']);
    viewedByVisitors($this->other, ['one']);

    expect(pairsFor(Recipient::viewer($this->user))['pairs'])->toBe([]);

    $this->artisan('views:pairs')->assertSuccessful();

    expect(pairsFor(Recipient::viewer($this->user))['pairs'])->toEqual([
        ['seed_type' => Post::class, 'seed_id' => $this->seed->getKey(), 'type' => Post::class, 'id' => $this->other->getKey(), 'visitors' => 1],
    ])
        ->and(pairsFor(Recipient::viewer($this->user), new ViewsQuery(collection: 'sidebar'))['seeds'])->toBe([])
        ->and(pairsFor(Recipient::visitor('nobody')))->toBe(['seeds' => [], 'pairs' => [], 'audiences' => []]);
});

it('reads what the fake reads from the same views', function (): void {
    $earlier = Post::factory()->create();
    $next = Post::factory()->create();
    $apartment = Apartment::factory()->create();

    $record = function (Model $viewable, ?string $visitor, string $viewedAt, ?User $viewer = null, ?string $collection = null): void {
        $factory = View::factory()->for($viewable, 'viewable')->state(['visitor' => $visitor])->inCollection($collection)->viewedAt(Carbon::parse($viewedAt));

        ($viewer instanceof User ? $factory->by($viewer) : $factory)->create();
    };

    $record($this->seed, 'laptop', '2026-01-10', $this->user);
    $record($earlier, 'phone', '2026-01-03', $this->user);
    $record($next, 'phone', '2025-01-01', $this->user);
    $record($this->seed, 'one', '2026-01-09');
    $record($this->seed, 'two', '2026-01-08');
    $record($this->seed, null, '2026-01-08');
    $record($next, 'one', '2026-01-09');
    $record($next, 'two', '2026-01-09');
    $record($next, 'laptop', '2026-01-09');
    $record($apartment, 'one', '2026-01-09');
    $record($earlier, 'one', '2026-01-09', collection: 'sidebar');

    $all = pairsFor(Recipient::viewer($this->user), new ViewsQuery(Period::since('2026-01-01')), includeSeen: true);

    expect($all['seeds'])->toEqual([
        ['type' => Post::class, 'id' => $this->seed->getKey(), 'viewed_at' => '2026-01-10 00:00:00'],
        ['type' => Post::class, 'id' => $earlier->getKey(), 'viewed_at' => '2026-01-03 00:00:00'],
    ])
        ->and($all['pairs'])->toEqual([
            ['seed_type' => Post::class, 'seed_id' => $this->seed->getKey(), 'type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
            ['seed_type' => Post::class, 'seed_id' => $this->seed->getKey(), 'type' => Post::class, 'id' => $next->getKey(), 'visitors' => 2],
            ['seed_type' => Post::class, 'seed_id' => $earlier->getKey(), 'type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
            ['seed_type' => Post::class, 'seed_id' => $earlier->getKey(), 'type' => Post::class, 'id' => $next->getKey(), 'visitors' => 1],
        ])
        ->and($all['audiences'])->toEqualCanonicalizing([
            ['type' => Post::class, 'id' => $this->seed->getKey(), 'visitors' => 3],
            ['type' => Post::class, 'id' => $earlier->getKey(), 'visitors' => 2],
            ['type' => Post::class, 'id' => $next->getKey(), 'visitors' => 3],
            ['type' => Apartment::class, 'id' => $apartment->getKey(), 'visitors' => 1],
        ]);
});
