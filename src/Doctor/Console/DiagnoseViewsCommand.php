<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Console;

use CyrildeWit\EloquentViewable\Doctor\Data\CheckResult;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Report;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Doctor\Doctor;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DiagnoseViewsCommand extends Command
{
    #[\Override]
    protected $signature = 'views:doctor
        {--only=* : Run only the checks with these keys, such as `schema`}
        {--json : Print the findings as JSON}
        {--strict : Fail on warnings as well as failures}';

    #[\Override]
    protected $description = 'Check the setup of the package and say what to fix';

    /** @throws InvalidConfiguration */
    public function handle(Doctor $doctor): int
    {
        /** @var list<string> $only */
        $only = $this->option('only');

        $checks = $doctor->checks($only);

        if ($checks === []) {
            $this->components->error('No checks to run. Check the --only option and `doctor.checks`.');

            return self::FAILURE;
        }

        $report = new Report;

        foreach ($checks as $check) {
            $this->printName($check->name());

            $findings = $doctor->examine($check);

            $this->printFindings($findings);

            $report->add(new CheckResult(Doctor::keyOf($check), $check->name(), $findings));
        }

        $this->isJson()
            ? $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            : $this->printSummary($report);

        return $report->fails((bool) $this->option('strict')) ? self::FAILURE : self::SUCCESS;
    }

    protected function printName(string $name): void
    {
        if ($this->isJson()) {
            return;
        }

        $this->newLine();
        $this->line("  <options=bold>{$name}</>");
    }

    /** @param  list<Finding>  $findings */
    protected function printFindings(array $findings): void
    {
        if ($this->isJson()) {
            return;
        }

        foreach ($findings as $finding) {
            $this->line("  <fg={$finding->status->color()}>{$finding->status->icon()}</> {$finding->summary}");

            if ($finding->fix !== null) {
                $this->line("    <fg=gray>→ {$finding->fix}</>");
            }
        }
    }

    protected function printSummary(Report $report): void
    {
        $failures = $report->count(Status::Failure);
        $warnings = $report->count(Status::Warning);
        $advice = $report->count(Status::Advice);

        $failureNoun = Str::plural('failure', $failures);
        $warningNoun = Str::plural('warning', $warnings);
        $adviceNoun = Str::plural('suggestion', $advice);

        $summary = "{$failures} {$failureNoun}, {$warnings} {$warningNoun}, {$advice} {$adviceNoun}.";

        $this->newLine();

        if ($failures > 0) {
            $this->components->error($summary);

            return;
        }

        if ($warnings > 0) {
            $this->components->warn($summary);

            return;
        }

        $this->components->info($summary);
    }

    protected function isJson(): bool
    {
        return (bool) $this->option('json');
    }
}
