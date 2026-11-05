<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Data;

class Report
{
    /** @var list<CheckResult> */
    protected array $results = [];

    public function add(CheckResult $result): void
    {
        $this->results[] = $result;
    }

    public function count(Status $status): int
    {
        $count = 0;

        foreach ($this->results as $result) {
            foreach ($result->findings as $finding) {
                if ($finding->status === $status) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function fails(bool $strict): bool
    {
        if ($this->count(Status::Failure) > 0) {
            return true;
        }

        if (! $strict) {
            return false;
        }

        return $this->count(Status::Warning) > 0;
    }

    /** @return array{checks: list<array{check: string, name: string, findings: list<array{status: string, summary: string, fix: ?string}>}>, summary: array<string, int>} */
    public function toArray(): array
    {
        $summary = [];

        foreach (Status::cases() as $status) {
            $summary[$status->value] = $this->count($status);
        }

        return [
            'checks' => array_map(fn (CheckResult $result): array => $result->toArray(), $this->results),
            'summary' => $summary,
        ];
    }
}
