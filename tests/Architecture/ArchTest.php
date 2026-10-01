<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\InteractsWithViews;
use CyrildeWit\EloquentViewable\PendingView;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\ViewsFacade;

const FOUNDATION = [
    'CyrildeWit\EloquentViewable\Support',
    'CyrildeWit\EloquentViewable\Contracts',
    'CyrildeWit\EloquentViewable\Exceptions',
    View::class,
    PendingView::class,
];

arch('no debugging statements are left in the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'die', 'exit'])
    ->not->toBeUsed();

arch('the package uses strict types')
    ->expect('CyrildeWit\EloquentViewable')
    ->toUseStrictTypes();

arch('contracts are interfaces')
    ->expect(['CyrildeWit\EloquentViewable\Contracts', 'CyrildeWit\EloquentViewable\Querying\Contracts'])
    ->toBeInterfaces();

arch('exceptions extend the base Exception')
    ->expect(['CyrildeWit\EloquentViewable\Exceptions', 'CyrildeWit\EloquentViewable\Querying\Exceptions'])
    ->toExtend(Exception::class)
    ->ignoring(EloquentViewableException::class);

arch('every package exception lives in an Exceptions namespace')
    ->expect(EloquentViewableException::class)
    ->toOnlyBeUsedIn([
        'CyrildeWit\EloquentViewable\Exceptions',
        'CyrildeWit\EloquentViewable\Querying\Exceptions',
    ]);

arch('the foundation is a leaf layer')
    ->expect(FOUNDATION)
    ->toOnlyUse([
        ...FOUNDATION,
        'Carbon',
        'Illuminate',
    ])
    ->ignoring(ViewsContract::class);

arch('the Views contract only reaches outside the foundation for ViewSeries')
    ->expect(ViewsContract::class)
    ->toOnlyUse([
        ...FOUNDATION,
        ViewSeries::class,
        'Carbon',
        'Illuminate',
    ]);

arch('querying does not reach back into the entry points')
    ->expect('CyrildeWit\EloquentViewable\Querying')
    ->not->toUse([
        Views::class,
        ViewsFacade::class,
        InteractsWithViews::class,
        EloquentViewableServiceProvider::class,
    ]);

arch()->preset()->php();

arch()->preset()->security();
