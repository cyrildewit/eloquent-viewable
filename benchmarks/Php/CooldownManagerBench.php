<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Php;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Config\Repository;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `CooldownManager::push()`: every call first sweeps the expired cooldowns
 * out of the session, parsing a date per entry, so the cost grows with the
 * number of viewables the visitor has seen recently. Runs against an array
 * session; the application is only booted so the viewable models resolve.
 */
#[Groups(['php'])]
#[BeforeMethods('setUp')]
#[OutputTimeUnit('microseconds', 1)]
#[Revs(100)]
#[Iterations(5)]
final class CooldownManagerBench
{
    private CooldownManager $cooldownManager;

    private Article $article;

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
        Application::boot();

        $config = new Config(new Repository([
            'eloquent-viewable' => require Application::projectPath('config/eloquent-viewable.php'),
        ]));

        $session = new Store('benchmark', new ArraySessionHandler(120));
        $this->cooldownManager = new CooldownManager($config, $session);
        $this->expiresAt = CarbonImmutable::now()->addDay();

        // Written straight into the session, the way the manager stores them.
        // Pushing them one by one would sweep the session on every push and
        // take minutes for the largest case.
        $namespace = $config->cooldownKey().'.'.strtolower(str_replace('\\', '-', Article::class));

        for ($id = 1; $id <= $params['entries']; $id++) {
            $session->put("{$namespace}.{$id}", ['viewable_id' => $id, 'expires_at' => $this->expiresAt]);
        }

        $this->article = $this->viewable($params['entries'] + 1);
    }

    #[ParamProviders('provideSessionSizes')]
    public function benchPush(): void
    {
        $this->cooldownManager->push($this->article, $this->expiresAt);
    }

    private function viewable(int $id): Article
    {
        return new Article()->forceFill(['id' => $id]);
    }
}
