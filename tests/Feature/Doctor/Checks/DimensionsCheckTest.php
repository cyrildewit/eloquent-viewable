<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Dimensions\Campaign;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Doctor\Checks\DimensionsCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
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
 * A DimensionsCheck that reports `$driver` as the driver, and `$version` as
 * the server version, or the real server version when `$version` is null.
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

describe('large hour tiers', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.retention.rollups.tiers', ['hour' => '35d', 'day' => '2y', 'month' => null]);
        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source', 'plan']);

        foreach (['09:00', '10:00', '11:00'] as $hour) {
            ViewRollup::query()->insert([
                'rollup' => 'views:source',
                'tier' => 'hour',
                'bucket_start' => "2026-03-10 {$hour}:00",
                'grouping' => 'type:dimension',
                'viewable_type' => 'post',
                'dimension' => 'Google',
                'views' => 1,
                'unique_visitors' => 1,
            ]);
        }
    });

    /** A DimensionsCheck that advises dropping an hour tier from three rows. */
    function hourTierCheck(): DimensionsCheck
    {
        return new class(app(DimensionRegistry::class), app(RollupPolicy::class), app(RollupState::class), app(View::class)) extends DimensionsCheck
        {
            protected function largeHourTier(): int
            {
                return 3;
            }
        };
    }

    it('advises dropping the hour tier of a dimension once it is large', function (): void {
        $findings = iterator_to_array(hourTierCheck()->run(), preserve_keys: false);

        expect($findings)->toHaveCount(1)
            ->and($findings[0]->status)->toBe(Status::Advice)
            ->and($findings[0]->summary)->toBe('The hour tier of the `source` dimension holds 3 rows.')
            ->and($findings[0]->fix)->toBe("Unless you read `source` by the hour, list `'source' => ['tiers' => ['day', 'month']]` under `retention.rollups.dimensions`. The next `views:maintain` drops its hour rows.");
    });

    it('says nothing of an hour tier below the size', function (): void {
        expect(dimensionFindings()[0][0])->toBe(Status::Pass)
            ->and(DimensionsCheck::LargeHourTier)->toBe(100_000);
    });

    it('says nothing of a dimension without an hour tier', function (): void {
        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source' => ['tiers' => ['day', 'month']]]);

        expect(dimensionFindings(hourTierCheck())[0][0])->toBe(Status::Pass);
    });

    it('says nothing of a dimension with only an hour tier', function (): void {
        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source' => ['tiers' => ['hour']]]);

        expect(dimensionFindings(hourTierCheck())[0][0])->toBe(Status::Pass);
    });

    it('says nothing of hour tiers without the state table', function (): void {
        $this->mock(StateStore::class)->allows('installed')->andReturn(false);

        expect(dimensionFindings(hourTierCheck())[0][0])->toBe(Status::Pass);
    });
});
