<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Doctor\Doctor;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

class PassingCheck implements Check
{
    public function name(): string
    {
        return 'Passing';
    }

    public function run(): iterable
    {
        yield Finding::pass('Fine.');
    }
}

class UnreachableStreamCheck implements Check
{
    public function name(): string
    {
        return 'Unreachable stream';
    }

    public function run(): iterable
    {
        throw new RuntimeException('Connection refused');
    }
}

/** @param  list<mixed>  $checks */
function doctor(array $checks): Doctor
{
    $config = new Config(new Repository(['eloquent-viewable' => ['doctor' => ['checks' => $checks]]]));

    return new Doctor(new Container, $config);
}

it('resolves the configured checks in order', function (): void {
    $checks = doctor([UnreachableStreamCheck::class, PassingCheck::class])->checks();

    expect($checks)->toHaveCount(2)
        ->and($checks[0])->toBeInstanceOf(UnreachableStreamCheck::class)
        ->and($checks[1])->toBeInstanceOf(PassingCheck::class);
});

it('keys a check by its class name without the suffix', function (): void {
    expect(Doctor::keyOf(new UnreachableStreamCheck))->toBe('unreachable-stream')
        ->and(Doctor::keyOf(new PassingCheck))->toBe('passing');
});

it('runs only the checks it is asked for', function (): void {
    $checks = doctor([UnreachableStreamCheck::class, PassingCheck::class])->checks(['passing']);

    expect($checks)->toHaveCount(1)
        ->and($checks[0])->toBeInstanceOf(PassingCheck::class);
});

it('rejects a check that does not implement the contract', function (): void {
    expect(fn (): array => doctor([stdClass::class])->checks())
        ->toThrow(InvalidConfiguration::class, 'Every class in `eloquent-viewable.doctor.checks` must implement');
});

it('returns the findings of a check', function (): void {
    $findings = doctor([])->examine(new PassingCheck);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->summary)->toBe('Fine.');
});

it('reports a check that throws as a failure', function (): void {
    $findings = doctor([])->examine(new UnreachableStreamCheck);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->status)->toBe(Status::Failure)
        ->and($findings[0]->summary)->toBe('The check could not run: Connection refused');
});
