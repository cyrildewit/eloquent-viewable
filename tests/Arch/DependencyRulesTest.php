<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\DimensionResolver;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Http\Beacon;
use CyrildeWit\EloquentViewable\Http\Controllers\BeaconController;
use CyrildeWit\EloquentViewable\Http\Controllers\PresenceController;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Presence\LiveViews;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Cookie;

const FOUNDATION = [
    'CyrildeWit\EloquentViewable\Support',
    'CyrildeWit\EloquentViewable\Data',
    'CyrildeWit\EloquentViewable\Models',
    'CyrildeWit\EloquentViewable\Database\Factories',
    'CyrildeWit\EloquentViewable\Contracts',
    'CyrildeWit\EloquentViewable\Exceptions',
];

const CONTRACTS = [
    'CyrildeWit\EloquentViewable\Contracts',
    'CyrildeWit\EloquentViewable\Recording\Contracts',
    'CyrildeWit\EloquentViewable\Recording\Streams\Contracts',
    'CyrildeWit\EloquentViewable\Visitors\Contracts',
    'CyrildeWit\EloquentViewable\Crawlers\Contracts',
    'CyrildeWit\EloquentViewable\Dimensions\Contracts',
    'CyrildeWit\EloquentViewable\Querying\Contracts',
    'CyrildeWit\EloquentViewable\Querying\Rollups\Contracts',
    'CyrildeWit\EloquentViewable\Cooldowns\Contracts',
    'CyrildeWit\EloquentViewable\Doctor\Contracts',
    'CyrildeWit\EloquentViewable\Presence\Contracts',
];

const MODULES = [
    'CyrildeWit\EloquentViewable\Recording',
    'CyrildeWit\EloquentViewable\Querying',
    'CyrildeWit\EloquentViewable\Visitors',
    'CyrildeWit\EloquentViewable\Crawlers',
    'CyrildeWit\EloquentViewable\Dimensions',
    'CyrildeWit\EloquentViewable\Cooldowns',
    'CyrildeWit\EloquentViewable\Retention',
    'CyrildeWit\EloquentViewable\Debugging',
    'CyrildeWit\EloquentViewable\Maintenance',
    'CyrildeWit\EloquentViewable\Erasure',
    'CyrildeWit\EloquentViewable\Doctor',
    'CyrildeWit\EloquentViewable\Presence',
    'CyrildeWit\EloquentViewable\Milestones',
    'CyrildeWit\EloquentViewable\Spikes',
];

const ENTRY_POINTS = [
    Views::class,
    ViewsFacade::class,
    InteractsWithViews::class,
    EloquentViewableServiceProvider::class,
    RecordViews::class,
    Beacon::class,
    BeaconController::class,
    PresenceController::class,
];

const EXCEPTIONS = [
    'CyrildeWit\EloquentViewable\Exceptions',
    'CyrildeWit\EloquentViewable\Dimensions\Exceptions',
    'CyrildeWit\EloquentViewable\Recording\Exceptions',
    'CyrildeWit\EloquentViewable\Querying\Exceptions',
    'CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions',
    'CyrildeWit\EloquentViewable\Retention\Exceptions',
    'CyrildeWit\EloquentViewable\Presence\Exceptions',
    'CyrildeWit\EloquentViewable\Milestones\Exceptions',
    'CyrildeWit\EloquentViewable\Spikes\Exceptions',
];

arch('no debugging statements are left in the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'die', 'exit'])
    ->not->toBeUsed();

arch('the package uses strict types')
    ->expect('CyrildeWit\EloquentViewable')
    ->toUseStrictTypes();

arch('contracts are interfaces')
    ->expect(CONTRACTS)
    ->toBeInterfaces();

arch('exceptions extend the base Exception')
    ->expect(EXCEPTIONS)
    ->toExtend(Exception::class)
    ->ignoring(EloquentViewableException::class);

arch('every package exception lives in an Exceptions namespace')
    ->expect(EloquentViewableException::class)
    ->toOnlyBeUsedIn(EXCEPTIONS);

arch('the foundation is a leaf layer')
    ->expect(FOUNDATION)
    ->toOnlyUse([
        ...FOUNDATION,
        'Carbon',
        'Illuminate',
    ]);

arch('crawlers is a leaf module')
    ->expect('CyrildeWit\EloquentViewable\Crawlers')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Crawlers',
        CrawlerDetect::class,
    ]);

arch('visitors report facts and judge nothing')
    ->expect('CyrildeWit\EloquentViewable\Visitors')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Visitors',
        'Illuminate',
        Cookie::class,
    ]);

arch('dimensions read the facts of the visitor and the verdict of the detector')
    ->expect('CyrildeWit\EloquentViewable\Dimensions')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Dimensions',
        'CyrildeWit\EloquentViewable\Visitors\Contracts',
        'CyrildeWit\EloquentViewable\Crawlers\Contracts',
        'Illuminate',
    ]);

arch('recording does not depend on querying')
    ->expect('CyrildeWit\EloquentViewable\Recording')
    ->not->toUse('CyrildeWit\EloquentViewable\Querying');

arch('querying knows dimensions only by their definitions')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse([
        DimensionResolver::class,
        DimensionInput::class,
        Arrival::class,
    ]);

arch('querying does not depend on recording')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse('CyrildeWit\EloquentViewable\Recording');

arch('the core of querying is unaware of rollups')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse('CyrildeWit\EloquentViewable\Querying\Rollups')
    ->ignoring('CyrildeWit\EloquentViewable\Querying\Rollups');

arch('presence builds on the foundation and the rankings of querying')
    ->expect('CyrildeWit\EloquentViewable\Presence')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Presence',
        'CyrildeWit\EloquentViewable\Querying\Ranking',
        'CyrildeWit\EloquentViewable\Querying\Exceptions',
        'Carbon',
        'Illuminate',
        Redis::class,
    ]);

arch('recording keeps presence through its contract and data only')
    ->expect('CyrildeWit\EloquentViewable\Recording')
    ->not->toUse([
        'CyrildeWit\EloquentViewable\Presence\Stores',
        LiveViews::class,
    ]);

arch('querying does not depend on presence')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse('CyrildeWit\EloquentViewable\Presence');

arch('recording and querying do not depend on retention')
    ->expect(['CyrildeWit\EloquentViewable\Recording', 'CyrildeWit\EloquentViewable\Querying'])
    ->not->toUse('CyrildeWit\EloquentViewable\Retention');

arch('retention knows querying only through the rollup contracts')
    ->expect('CyrildeWit\EloquentViewable\Retention')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Retention',
        'CyrildeWit\EloquentViewable\Querying\Rollups\Contracts',
        'Carbon',
        'Illuminate',
    ]);

arch('debugging only reads what recording reports')
    ->expect('CyrildeWit\EloquentViewable\Debugging')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Debugging',
        'CyrildeWit\EloquentViewable\Recording\Contracts',
        'CyrildeWit\EloquentViewable\Recording\Events',
        'CyrildeWit\EloquentViewable\Recording\Data',
        'DebugBar',
        'Fruitcake\LaravelDebugbar',
        'Illuminate',
        'class_basename',
    ]);

arch('only debugging knows Debugbar')
    ->expect(['DebugBar', 'Fruitcake\LaravelDebugbar'])
    ->toOnlyBeUsedIn('CyrildeWit\EloquentViewable\Debugging');

arch('maintenance runs querying and retention and nothing else')
    ->expect('CyrildeWit\EloquentViewable\Maintenance')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Maintenance',
        'CyrildeWit\EloquentViewable\Querying',
        'CyrildeWit\EloquentViewable\Retention',
        'Carbon',
        'Illuminate',
    ]);

arch('nothing below maintenance depends on it')
    ->expect(['CyrildeWit\EloquentViewable\Recording', 'CyrildeWit\EloquentViewable\Querying', 'CyrildeWit\EloquentViewable\Retention'])
    ->not->toUse('CyrildeWit\EloquentViewable\Maintenance');

arch('no module depends on erasure')
    ->expect(['CyrildeWit\EloquentViewable\Recording', 'CyrildeWit\EloquentViewable\Querying', 'CyrildeWit\EloquentViewable\Retention'])
    ->not->toUse('CyrildeWit\EloquentViewable\Erasure');

arch('erasure reaches the other modules through their contracts and a few seams')
    ->expect('CyrildeWit\EloquentViewable\Erasure')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Erasure',
        'CyrildeWit\EloquentViewable\Recording\Contracts',
        VisitorIdentity::class,
        AnonymiseViews::class,
        'Carbon',
        'Illuminate',
    ]);

arch('no module depends on milestones')
    ->expect(['CyrildeWit\EloquentViewable\Recording', 'CyrildeWit\EloquentViewable\Querying', 'CyrildeWit\EloquentViewable\Retention', 'CyrildeWit\EloquentViewable\Maintenance'])
    ->not->toUse('CyrildeWit\EloquentViewable\Milestones');

arch('milestones read the counter columns and the state table, and nothing else')
    ->expect('CyrildeWit\EloquentViewable\Milestones')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Milestones',
        'CyrildeWit\EloquentViewable\Querying\Rollups\Contracts',
        'Illuminate',
    ]);

arch('no module depends on spikes')
    ->expect(['CyrildeWit\EloquentViewable\Recording', 'CyrildeWit\EloquentViewable\Querying', 'CyrildeWit\EloquentViewable\Retention', 'CyrildeWit\EloquentViewable\Maintenance', 'CyrildeWit\EloquentViewable\Milestones'])
    ->not->toUse('CyrildeWit\EloquentViewable\Spikes');

arch('spikes rank through querying and keep their episodes, and nothing else')
    ->expect('CyrildeWit\EloquentViewable\Spikes')
    ->toOnlyUse([
        ...FOUNDATION,
        'CyrildeWit\EloquentViewable\Spikes',
        'CyrildeWit\EloquentViewable\Querying\Contracts',
        'CyrildeWit\EloquentViewable\Querying\Exceptions',
        'CyrildeWit\EloquentViewable\Querying\Growth',
        'Carbon',
        'Illuminate',
    ]);

arch('nothing but the entry points knows the doctor')
    ->expect('CyrildeWit\EloquentViewable\Doctor')
    ->toOnlyBeUsedIn([
        'CyrildeWit\EloquentViewable\Doctor',
        EloquentViewableServiceProvider::class,
    ]);

arch('only the entry points join the two sides')
    ->expect(MODULES)
    ->not->toUse(ENTRY_POINTS);

arch()->preset()->php();

arch()->preset()->security();
