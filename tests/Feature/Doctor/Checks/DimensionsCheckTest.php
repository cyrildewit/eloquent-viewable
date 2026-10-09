<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Doctor\Checks\DimensionsCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use Illuminate\Support\Carbon;

/** @return list<array{Status, string}> */
function dimensionFindings(?DimensionsCheck $check = null): array
{
    return array_map(
        fn (Finding $finding): array => [$finding->status, $finding->summary],
        iterator_to_array(($check ?? app()->make(DimensionsCheck::class))->run(), preserve_keys: false),
    );
}

/**
 * The check on a database that reports the driver given, and the version
 * given or its own.
 */
function dimensionsCheckOn(string $driver, ?string $version = null): DimensionsCheck
{
    return new class(app(DimensionRegistry::class), app(RollupPolicy::class), app(RollupState::class), app(View::class), $driver, $version) extends DimensionsCheck
    {
        public function __construct(DimensionRegistry $dimensions, RollupPolicy $rollups, RollupState $state, View $view, private readonly string $driver, private readonly ?string $version)
        {
            parent::__construct($dimensions, $rollups, $state, $view);
        }

        protected function driverName(): string
        {
            return $this->driver;
        }

        protected function serverVersion(): string
        {
            return $this->version ?? parent::serverVersion();
        }
    };
}

beforeEach(function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.dimensions.definitions', [
        'source' => Source::class,
        'plan' => [PlanDimension::class, 'maxValues' => null],
    ]);
});

it('skips without dimensions', function (): void {
    config()->set('eloquent-viewable.dimensions.definitions', []);

    expect(dimensionFindings())->toBe([[Status::Skipped, 'No dimension is listed in `dimensions.definitions`.']]);
});

it('passes dimensions that need nothing more', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    expect(dimensionFindings())->toBe([[Status::Pass, 'Every dimension can be recorded, folded and counted.']]);
});

it('fails a cap on a MySQL without window functions', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    expect(dimensionFindings(dimensionsCheckOn('mysql', '5.7.44')))->toBe([
        [Status::Failure, 'Rollups fold dimensions with a cap, which needs MySQL 8.0 or newer, and the database runs 5.7.44.'],
    ])->and(dimensionFindings(dimensionsCheckOn('mysql', '8.0.36'))[0][0])->toBe(Status::Pass)
        ->and(dimensionFindings(dimensionsCheckOn('mariadb', '10.6.0'))[0][0])->toBe(Status::Pass);
});

it('reads the version off the database itself', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    $version = app(View::class)->getConnection()->getServerVersion();

    expect(dimensionFindings(dimensionsCheckOn('mysql'))[0][1])->toBe(
        version_compare($version, '8.0', '>=')
            ? 'Every dimension can be recorded, folded and counted.'
            : "Rollups fold dimensions with a cap, which needs MySQL 8.0 or newer, and the database runs {$version}.",
    );
});

it('lets a MySQL without window functions fold dimensions without a cap', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['plan']);

    expect(dimensionFindings(dimensionsCheckOn('mysql', '5.7.44'))[0][0])->toBe(Status::Pass);
});

it('says when a dimension was folded after the views it counts', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    $state = app(RollupState::class);
    $state->putOrigin('views', Carbon::parse('2026-01-01'));
    $state->putOrigin('views:source', Carbon::parse('2026-03-10'));

    expect(dimensionFindings())->toBe([
        [Status::Advice, 'The `source` dimension is folded from 2026-03-10, the views it counts from 2026-01-01, so its rollup holds nothing before 2026-03-10.'],
    ]);
});

it('says nothing of a dimension folded along with the views, or not yet', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source', 'plan']);

    $state = app(RollupState::class);
    $state->putOrigin('views', Carbon::parse('2026-01-01'));
    $state->putOrigin('views:source', Carbon::parse('2026-01-01'));

    expect(dimensionFindings()[0][0])->toBe(Status::Pass);
});

it('says nothing of rollups without their state table', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    $this->mock(StateStore::class)->allows('installed')->andReturn(false);

    expect(dimensionFindings()[0][0])->toBe(Status::Pass);
});

it('says nothing before the views are folded', function (): void {
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

    app(RollupState::class)->putOrigin('views:source', Carbon::parse('2026-03-10'));

    expect(dimensionFindings()[0][0])->toBe(Status::Pass);
});

it('warns about a personal dimension folded into rollups', function (): void {
    config()->set('eloquent-viewable.dimensions.definitions', [
        'campaign' => Campaign::class,
        'source' => Source::class,
    ]);
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['campaign', 'source']);

    expect(dimensionFindings())->toBe([
        [Status::Warning, 'The `campaign` dimension is marked personal but folded into rollups, where anonymising and erasing a person never reach its values.'],
    ]);
});

it('lets a dimension whose values identify no one be folded', function (): void {
    config()->set('eloquent-viewable.dimensions.definitions', ['campaign' => [Campaign::class, 'personal' => false]]);
    config()->set('eloquent-viewable.retention.rollups.dimensions', ['campaign']);

    expect(dimensionFindings()[0][0])->toBe(Status::Pass);
});
