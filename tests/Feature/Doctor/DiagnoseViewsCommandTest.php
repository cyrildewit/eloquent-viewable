<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use Illuminate\Support\Facades\Artisan;

class HealthyCheck implements Check
{
    public function name(): string
    {
        return 'Healthy';
    }

    public function run(): iterable
    {
        yield Finding::pass('Everything is in place.');

        yield Finding::advice('Consider the visitor index.', 'Add it in a migration.');

        yield Finding::skipped('The redis store is not in use.');
    }
}

class WarningCheck implements Check
{
    public function name(): string
    {
        return 'Warning';
    }

    public function run(): iterable
    {
        yield Finding::warning('The flush is not scheduled.', 'Schedule `views:flush`.');
    }
}

class FailingCheck implements Check
{
    public function name(): string
    {
        return 'Failing';
    }

    public function run(): iterable
    {
        yield Finding::failure('The views table is missing.');
    }
}

/** @param  list<class-string<Check>>  $checks */
function useChecks(array $checks): void
{
    config()->set('eloquent-viewable.doctor.checks', $checks);
}

it('is registered', function (): void {
    expect(Artisan::all())->toHaveKey('views:doctor');
});

it('prints every finding under the name of its check', function (): void {
    useChecks([HealthyCheck::class]);

    $this->artisan('views:doctor')
        ->expectsOutputToContain('Healthy')
        ->expectsOutputToContain('✓ Everything is in place.')
        ->expectsOutputToContain('i Consider the visitor index.')
        ->expectsOutputToContain('→ Add it in a migration.')
        ->expectsOutputToContain('- The redis store is not in use.')
        ->expectsOutputToContain('0 failures, 0 warnings, 1 suggestion.')
        ->assertSuccessful();
});

it('passes with warnings unless it is strict', function (): void {
    useChecks([WarningCheck::class]);

    $this->artisan('views:doctor')
        ->expectsOutputToContain('0 failures, 1 warning, 0 suggestions.')
        ->assertSuccessful();

    $this->artisan('views:doctor', ['--strict' => true])->assertFailed();
});

it('fails when a check fails', function (): void {
    useChecks([HealthyCheck::class, FailingCheck::class]);

    $this->artisan('views:doctor')
        ->expectsOutputToContain('✗ The views table is missing.')
        ->expectsOutputToContain('1 failure, 0 warnings, 1 suggestion.')
        ->assertFailed();
});

it('runs only the checks it is asked for', function (): void {
    useChecks([HealthyCheck::class, FailingCheck::class]);

    $this->artisan('views:doctor', ['--only' => ['healthy']])
        ->doesntExpectOutputToContain('The views table is missing.')
        ->assertSuccessful();
});

it('refuses to run without checks', function (): void {
    useChecks([HealthyCheck::class]);

    $this->artisan('views:doctor', ['--only' => ['unknown']])
        ->expectsOutputToContain('No checks to run.')
        ->assertFailed();
});

it('prints the findings as JSON', function (): void {
    useChecks([WarningCheck::class]);

    Artisan::call('views:doctor', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBe([
        'checks' => [
            [
                'check' => 'warning',
                'name' => 'Warning',
                'findings' => [
                    ['status' => 'warning', 'summary' => 'The flush is not scheduled.', 'fix' => 'Schedule `views:flush`.'],
                ],
            ],
        ],
        'summary' => ['pass' => 0, 'advice' => 0, 'warning' => 1, 'failure' => 0, 'skipped' => 0],
    ]);
});

it('runs the shipped checks in order', function (): void {
    Artisan::call('views:doctor', ['--json' => true]);

    /** @var array{checks: list<array{check: string, name: string}>} $report */
    $report = json_decode(Artisan::output(), true);

    expect(array_map(fn (array $check): string => "{$check['check']}: {$check['name']}", $report['checks']))->toBe([
        'schema: Database schema',
        'index-advice: Optional indexes',
        'dimensions: Dimensions',
        'shared-cache: Shared cache stores',
        'schedule: Scheduler',
        'trusted-proxies: Trusted proxies',
        'redis-stream: Redis stream',
        'crawler-share: Refused attempts',
        'configuration: Configuration',
    ]);
});
