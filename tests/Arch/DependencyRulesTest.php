<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Views;
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
    'CyrildeWit\EloquentViewable\Cooldowns\Contracts',
];

const MODULES = [
    'CyrildeWit\EloquentViewable\Recording',
    'CyrildeWit\EloquentViewable\Querying',
    'CyrildeWit\EloquentViewable\Visitors',
    'CyrildeWit\EloquentViewable\Crawlers',
    'CyrildeWit\EloquentViewable\Cooldowns',
];

const ENTRY_POINTS = [
    Views::class,
    ViewsFacade::class,
    InteractsWithViews::class,
    EloquentViewableServiceProvider::class,
];

const EXCEPTIONS = [
    'CyrildeWit\EloquentViewable\Exceptions',
    'CyrildeWit\EloquentViewable\Recording\Exceptions',
    'CyrildeWit\EloquentViewable\Querying\Exceptions',
    'CyrildeWit\EloquentViewable\Testing\Exceptions',
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

arch('only the entry points join the two sides')
    ->expect(MODULES)
    ->not->toUse(ENTRY_POINTS);

arch()->preset()->php();

arch()->preset()->security();
