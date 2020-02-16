<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

interface HeaderResolver
{
    /**
     * Resolve the header.
     *
     * @return mixed
     */
    public function resolve(string $name);
}
