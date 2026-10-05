<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Http\Beacon;
use CyrildeWit\EloquentViewable\Http\Controllers\BeaconController;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
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
    'CyrildeWit\EloquentViewable\Querying\Contracts',
    'CyrildeWit\EloquentViewable\Querying\Rollups\Contracts',
    'CyrildeWit\EloquentViewable\Cooldowns\Contracts',
];

const MODULES = [
    'CyrildeWit\EloquentViewable\Recording',
    'CyrildeWit\EloquentViewable\Querying',
    'CyrildeWit\EloquentViewable\Visitors',
    'CyrildeWit\EloquentViewable\Crawlers',
    'CyrildeWit\EloquentViewable\Cooldowns',
    'CyrildeWit\EloquentViewable\Retention',
    'CyrildeWit\EloquentViewable\Debugging',
    'CyrildeWit\EloquentViewable\Maintenance',
    'CyrildeWit\EloquentViewable\Erasure',
];

const ENTRY_POINTS = [
    Views::class,
    ViewsFacade::class,
    InteractsWithViews::class,
    EloquentViewableServiceProvider::class,
    RecordViews::class,
    Beacon::class,
    BeaconController::class,
];

const EXCEPTIONS = [
    'CyrildeWit\EloquentViewable\Exceptions',
    'CyrildeWit\EloquentViewable\Recording\Exceptions',
    'CyrildeWit\EloquentViewable\Querying\Exceptions',
    'CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions',
    'CyrildeWit\EloquentViewable\Retention\Exceptions',
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

arch('recording does not depend on querying')
    ->expect('CyrildeWit\EloquentViewable\Recording')
    ->not->toUse('CyrildeWit\EloquentViewable\Querying');

arch('querying does not depend on recording')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse('CyrildeWit\EloquentViewable\Recording');

arch('the core of querying is unaware of rollups')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse('CyrildeWit\EloquentViewable\Querying\Rollups')
    ->ignoring('CyrildeWit\EloquentViewable\Querying\Rollups');

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
        CacheVersions::class,
        AnonymiseViews::class,
        'Carbon',
        'Illuminate',
    ]);

arch('only the entry points join the two sides')
    ->expect(MODULES)
    ->not->toUse(ENTRY_POINTS);

arch()->preset()->php();

arch()->preset()->security();
