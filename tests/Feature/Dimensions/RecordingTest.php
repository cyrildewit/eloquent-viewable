<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Medium;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\BrokenDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\CountingDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

const DIMENSIONS_STREAM = 'eloquent-viewable:views';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

/** @param  array<string, mixed>  $definitions */
function recordDimensions(array $definitions): void
{
    config()->set('eloquent-viewable.dimensions.definitions', $definitions);
}

/**
 * Makes the request a page request, as the middleware or a controller would
 * see it.
 *
 * @param  array<string, string>  $headers
 */
function onPage(string $url, array $headers = []): void
{
    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    app()->instance('request', Request::create($url, 'GET', server: $server));
}

function bufferWith(string $client): RedisStreamStore
{
    if ($client === 'phpredis' && ! extension_loaded('redis')) {
        test()->markTestSkipped('The phpredis extension is not installed.');
    }

    config()->set('database.redis.client', $client);
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->forgetInstance('redis');
    app()->make(StoreManager::class)->forgetDrivers();
    app()->make(RedisFactory::class)->connection()->command('del', [DIMENSIONS_STREAM]);

    /** @var RedisStreamStore $store */
    $store = app()->make(ViewStore::class);

    return $store;
}

beforeEach(function (): void {
    config()->set('app.url', 'https://example.com');

    $this->post = Post::factory()->create(['title' => 'pro']);
});

it('keeps each dimension in its column and a JSON one in the context', function (): void {
    recordDimensions([
        'source' => Source::class,
        'medium' => Medium::class,
        'campaign' => Campaign::class,
        'device' => Device::class,
        'plan' => PlanDimension::class,
    ]);

    onPage('https://example.com/posts/1?utm_campaign=Spring&utm_medium=email', [
        'Referer' => 'https://news.ycombinator.com/item?id=1',
        'User-Agent' => IPHONE,
    ]);

    views($this->post)->context(['ab' => 'b'])->record();

    $view = View::sole();

    expect($view->getAttribute('source'))->toBe('Hacker News')
        ->and($view->getAttribute('medium'))->toBe('email')
        ->and($view->getAttribute('campaign'))->toBe('spring')
        ->and($view->getAttribute('device'))->toBe('mobile')
        ->and($view->context)->toBe(['ab' => 'b', 'plan' => 'plan-of-pro']);
});

it('records nothing new without dimensions', function (): void {
    onPage('https://example.com/posts/1', ['Referer' => 'https://news.ycombinator.com/']);

    views($this->post)->record();

    expect(View::sole()->getAttribute('source'))->toBeNull()
        ->and(View::sole()->context)->toBeNull();
});

it('reads a referrer from the application itself as direct', function (): void {
    recordDimensions(['source' => Source::class]);
    config()->set('eloquent-viewable.dimensions.internal_hosts', ['shop.example']);

    onPage('https://example.com/posts/1', ['Referer' => 'https://shop.example/cart']);
    views($this->post)->record();

    onPage('https://example.com/posts/1', ['Referer' => 'https://www.example.com/']);
    views($this->post)->record();

    expect(View::query()->pluck('source')->all())->toBe(['Direct', 'Direct']);
});

it('reads the referrer and landing page the view arrived from over the request', function (): void {
    recordDimensions(['source' => Source::class, 'campaign' => Campaign::class]);

    onPage('https://example.com/api/views', ['Referer' => 'https://example.com/posts/1']);

    views($this->post)->arrivedFrom(new Arrival('t.co', ['utm_campaign' => 'launch']))->record();

    expect(View::sole())
        ->getAttribute('source')->toBe('X')
        ->getAttribute('campaign')->toBe('launch');
});

it('resolves nothing for a view a guard skips', function (): void {
    recordDimensions(['counted' => [CountingDimension::class, 'json' => 'context->counted']]);
    CountingDimension::$resolved = 0;

    views($this->post)->cooldown(Carbon::now()->addHour())->record();
    views($this->post)->cooldown(Carbon::now()->addHour())->record();

    expect(View::count())->toBe(1)
        ->and(CountingDimension::$resolved)->toBe(1);
});

it('reports a dimension that throws and records the view without its value', function (): void {
    Exceptions::fake();

    recordDimensions([
        'source' => BrokenDimension::class,
        'medium' => Medium::class,
    ]);

    expect(views($this->post)->record())->toBeTrue();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'The lookup is down.');

    expect(View::sole())
        ->getAttribute('source')->toBeNull()
        ->getAttribute('medium')->toBe('direct');
});

it('resolves the dimensions before the view is queued', function (): void {
    Bus::fake();
    recordDimensions(['source' => Source::class]);
    onPage('https://example.com/posts/1?utm_source=fb');

    views($this->post)->queue()->record();

    Bus::assertDispatched(RecordViewJob::class, fn (RecordViewJob $job): bool => $job->record->dimensions === ['source' => 'Facebook']);
});

it('stores the dimensions of a queued view', function (): void {
    config()->set('queue.default', 'sync');
    recordDimensions(['source' => Source::class]);
    onPage('https://example.com/posts/1?utm_source=fb');

    views($this->post)->queue()->record();

    expect(View::sole()->getAttribute('source'))->toBe('Facebook');
});

it('reports the dimensions a view was given', function (): void {
    $attempted = [];
    Event::listen(ViewAttempted::class, function (ViewAttempted $event) use (&$attempted): void {
        $attempted[] = $event->result->dimensions;
    });
    recordDimensions(['source' => Source::class, 'campaign' => Campaign::class]);

    $result = views($this->post)->attempt();

    expect($result->dimensions)->toBe(['source' => 'Direct', 'campaign' => null])
        ->and($attempted)->toBe([['source' => 'Direct', 'campaign' => null]]);
});

it('keeps the dimensions of a view recorded with the fake', function (): void {
    $fake = Views::fake();
    recordDimensions(['source' => Source::class, 'plan' => PlanDimension::class]);
    onPage('https://example.com/posts/1?utm_source=newsletter');

    views($this->post)->record();

    $fake->assertRecorded($this->post, fn (ViewRecord $view): bool => $view->dimensions['source'] === 'newsletter');
    $fake->assertRecorded($this->post, fn (ViewRecord $view): bool => $view->context === ['plan' => 'plan-of-pro']);
});

describe('the Redis buffer', function (): void {
    it('lands the dimensions of a buffered view', function (string $client): void {
        $store = bufferWith($client);
        recordDimensions(['source' => Source::class, 'device' => Device::class]);
        onPage('https://example.com/posts/1', ['Referer' => 'https://www.bing.com/', 'User-Agent' => IPHONE]);

        views($this->post)->record();

        expect($store->flush())->toBe(1)
            ->and(View::sole())
            ->getAttribute('source')->toBe('Bing')
            ->getAttribute('device')->toBe('mobile');
    })->with(['phpredis', 'predis']);

    it('lands views buffered before a dimension was added along with the views after', function (string $client): void {
        $store = bufferWith($client);
        onPage('https://example.com/posts/1', ['Referer' => 'https://www.bing.com/']);

        views($this->post)->record();

        recordDimensions(['source' => Source::class]);
        app()->forgetScopedInstances();

        views($this->post)->record();

        expect($store->flush())->toBe(2)
            ->and(View::query()->orderBy('id')->pluck('source')->all())->toBe([null, 'Bing']);
    })->with(['phpredis', 'predis']);
});
