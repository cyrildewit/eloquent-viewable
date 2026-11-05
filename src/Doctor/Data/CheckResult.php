<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Data;

class CheckResult
{
    /** @param  list<Finding>  $findings */
    public function __construct(
        public string $key,
        public string $name,
        public array $findings,
    ) {}

    /** @return array{check: string, name: string, findings: list<array{status: string, summary: string, fix: ?string}>} */
    public function toArray(): array
    {
        return [
            'check' => $this->key,
            'name' => $this->name,
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
