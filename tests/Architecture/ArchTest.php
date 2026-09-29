<?php

declare(strict_types=1);

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
    ->toExtend(Exception::class);

arch()->preset()->php();

arch()->preset()->security();
