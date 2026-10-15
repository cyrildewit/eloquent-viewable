<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedDriver;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Querying\Grammars\MySqlGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\PostgresGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\SQLiteGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;
use Illuminate\Container\Container;

function bucketGrammarRegistry(): GrammarRegistry
{
    return Container::getInstance()->make(GrammarRegistry::class);
}

it('is a singleton', function (): void {
    expect(bucketGrammarRegistry())->toBe(bucketGrammarRegistry());
});

it('ships a grammar for {driver}', function (string $driver, string $grammar): void {
    expect(bucketGrammarRegistry()->for($driver))->toBeInstanceOf($grammar);
})->with([
    'sqlite' => ['sqlite', SQLiteGrammar::class],
    'mysql' => ['mysql', MySqlGrammar::class],
    'mariadb' => ['mariadb', MySqlGrammar::class],
    'pgsql' => ['pgsql', PostgresGrammar::class],
]);

it('resolves a registered class through the container once', function (): void {
    $grammars = new GrammarRegistry;

    $grammars->register('custom', SQLiteGrammar::class);

    expect($grammars->for('custom'))->toBeInstanceOf(SQLiteGrammar::class)
        ->and($grammars->for('custom'))->toBe($grammars->for('custom'));
});

it('returns a registered instance as is', function (): void {
    $grammar = new class implements BucketGrammar
    {
        public function truncate(string $column, Granularity $granularity): string
        {
            return $column;
        }

        public function convertTimezone(string $column, TimezoneConversion $conversion): string
        {
            return $column;
        }
    };

    $grammars = new GrammarRegistry;

    $grammars->register('custom', $grammar);

    expect($grammars->for('custom'))->toBe($grammar);
});

it('lets an application override a shipped driver', function (): void {
    bucketGrammarRegistry()->register('sqlite', PostgresGrammar::class);

    expect(bucketGrammarRegistry()->for('sqlite'))->toBeInstanceOf(PostgresGrammar::class);
});

it('throws for a driver without a grammar', function (): void {
    expect(fn (): BucketGrammar => bucketGrammarRegistry()->for('sqlsrv'))
        ->toThrow(UnsupportedDriver::class, '`sqlsrv`');
});
