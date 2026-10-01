<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;

arch('no debugging statements are left in the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'die', 'exit'])
    ->not->toBeUsed();

arch('the package uses strict types')
    ->expect('CyrildeWit\EloquentViewable')
    ->toUseStrictTypes();

arch('contracts are interfaces')
    ->expect('CyrildeWit\EloquentViewable\Contracts')
    ->toBeInterfaces();

arch('exceptions extend the base Exception')
    ->expect('CyrildeWit\EloquentViewable\Exceptions')
    ->toExtend(Exception::class)
    ->ignoring(EloquentViewableException::class);

arch('every package exception lives in an Exceptions namespace')
    ->expect(EloquentViewableException::class)
    ->toOnlyBeUsedIn('CyrildeWit\EloquentViewable\Exceptions');

arch()->preset()->php();

arch()->preset()->security();
