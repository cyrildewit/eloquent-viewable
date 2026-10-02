<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures;

use CyrildeWit\EloquentViewable\Visitors\Visitor;

/**
 * A visitor that reports a crawler's user agent whatever the request says.
 */
class TestVisitor extends Visitor
{
    #[\Override]
    public function userAgent(): string
    {
        return 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    }
}
