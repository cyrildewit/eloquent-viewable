<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Php;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Cooldown;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Session\Session;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `CooldownStore::put()` on the session store `CooldownManager` builds:
 * every put first sweeps the expired cooldowns out of the session, so the
 * cost grows with the number of cooldowns the visitor has running. Runs
 * against the array session; the application is only booted so the manager
 * and its store resolve.
 */
#[Groups(['php'])]
#[BeforeMethods('setUp')]
#[OutputTimeUnit('microseconds', 1)]
#[Revs(100)]
#[Iterations(5)]
final class CooldownManagerBench
{
    private CooldownStore $store;

    private string $key;

    private CarbonImmutable $expiresAt;

    /**
     * @return Generator<string, array{entries: int}>
     */
    public function provideSessionSizes(): Generator
    {
        yield 'empty session' => ['entries' => 0];
        yield '100 cooldowns' => ['entries' => 100];
        yield '1,000 cooldowns' => ['entries' => 1_000];
        yield '10,000 cooldowns' => ['entries' => 10_000];
    }

    /**
     * @param  array{entries: int}  $params
     */
    public function setUp(array $params): void
    {
        $app = Application::boot();

        $this->store = $app->make(CooldownManager::class)->driver('session');
        $this->expiresAt = CarbonImmutable::now()->addDay();

        // Written straight into the session, the way the store keeps them.
        // Putting them one by one would sweep the session on every put and
        // take minutes for the largest case.
        $running = [];

        for ($id = 1; $id <= $params['entries']; $id++) {
            $running[Cooldown::of($this->viewable($id), 'visitor')->key()] = $this->expiresAt->getTimestamp();
        }

        $app->make(Session::class)->put($app->make(Config::class)->cooldownKey(), $running);

        $this->key = Cooldown::of($this->viewable($params['entries'] + 1), 'visitor')->key();
    }

    #[ParamProviders('provideSessionSizes')]
    public function benchPush(): void
    {
        $this->store->put($this->key, $this->expiresAt);
    }

    private function viewable(int $id): Article
    {
        return new Article()->forceFill(['id' => $id]);
    }
}
