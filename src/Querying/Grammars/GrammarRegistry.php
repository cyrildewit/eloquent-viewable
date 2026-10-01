<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Grammars;

use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedDriver;
use Illuminate\Container\Container;

final class GrammarRegistry
{
    /**
     * @var array<string, class-string<BucketGrammar>|BucketGrammar>
     */
    private array $grammars = [];

    /**
     * @param  class-string<BucketGrammar>|BucketGrammar  $grammar
     */
    public function register(string $driver, string|BucketGrammar $grammar): void
    {
        $this->grammars[$driver] = $grammar;
    }

    /**
     * @throws UnsupportedDriver
     */
    public function for(string $driver): BucketGrammar
    {
        $grammar = $this->grammars[$driver] ?? throw UnsupportedDriver::noBucketGrammar($driver);

        if (is_string($grammar)) {
            return $this->grammars[$driver] = Container::getInstance()->make($grammar);
        }

        return $grammar;
    }
}
