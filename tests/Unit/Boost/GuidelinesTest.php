<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Concerns\HasViewHistory;
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Milestones\Events\ViewMilestoneReached;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Presence\LiveViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Testing\ViewsFake;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;

const BoostPath = __DIR__.'/../../../resources/boost';

/** @return list<string> */
function boostFiles(): array
{
    return [
        BoostPath.'/guidelines/core.md',
        ...glob(BoostPath.'/skills/*/SKILL.md') ?: [],
    ];
}

/** @return list<string> the methods called in the PHP blocks of a file */
function calledMethods(string $file): array
{
    preg_match_all('/```php\n(.*?)```/s', (string) file_get_contents($file), $blocks);
    preg_match_all('/(?:->|::)(\w+)\(/', implode("\n", $blocks[1]), $calls);

    return array_values(array_unique($calls[1]));
}

it('ships a guideline and a skill', function (): void {
    expect(BoostPath.'/guidelines/core.md')->toBeFile()
        ->and(glob(BoostPath.'/skills/*/SKILL.md'))->not->toBeEmpty();
});

it('names each skill after its directory', function (string $file): void {
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($file), $frontmatter);
    preg_match('/^name: (.+)$/m', $frontmatter[1] ?? '', $name);
    preg_match('/^description: (.+)$/m', $frontmatter[1] ?? '', $description);

    expect($name[1] ?? null)->toBe(basename(dirname($file)))
        ->and($description[1] ?? null)->not->toBeNull();
})->with(fn (): array => glob(BoostPath.'/skills/*/SKILL.md') ?: []);

it('points the guideline at a skill that exists', function (): void {
    preg_match_all('/`([a-z-]+-development)`/', (string) file_get_contents(BoostPath.'/guidelines/core.md'), $skills);

    expect($skills[1])->not->toBeEmpty();

    foreach ($skills[1] as $skill) {
        expect(BoostPath.'/skills/'.$skill.'/SKILL.md')->toBeFile();
    }
});

it('only calls methods that exist', function (string $file): void {
    $classes = [
        Views::class, ViewsFacade::class, ViewsFake::class, Period::class, RecordResult::class, RecordViews::class,
        InteractsWithViews::class, HasViewHistory::class, View::class, LiveViews::class, ViewMilestoneReached::class,
        Builder::class, Factory::class, Route::class, Router::class, MakesHttpRequests::class,
    ];

    $missing = array_filter(calledMethods($file), fn (string $method): bool => array_filter(
        $classes,
        fn (string $class): bool => method_exists($class, $method) || method_exists($class, 'scope'.ucfirst($method)),
    ) === []);

    expect($missing)->toBeEmpty();
})->with(fn (): array => boostFiles());

it('only names config keys that exist', function (string $file): void {
    $config = require __DIR__.'/../../../config/eloquent-viewable.php';

    preg_match_all('/`([a-z_]+(?:\.[a-z_]+)+)`/', (string) file_get_contents($file), $keys);

    expect(array_values(array_filter($keys[1], fn (string $key): bool => ! Arr::has($config, $key))))->toBeEmpty();
})->with(fn (): array => boostFiles());
