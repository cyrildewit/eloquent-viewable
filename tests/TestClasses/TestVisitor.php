<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses;

use CyrildeWit\EloquentViewable\Visitor;

class TestVisitor extends Visitor
{
    public function isCrawler(): bool
    {
        return true;
    }
}
