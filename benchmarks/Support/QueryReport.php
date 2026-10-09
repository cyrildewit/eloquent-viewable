<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use JsonException;
use stdClass;

/**
 * The queries and plans `bench:explain` collects, in the shape it writes as
 * JSON. The results repository stores the file next to every run and shows
 * the SQL on each benchmark's page, so a key is only ever added, never
 * renamed or removed; bump `SCHEMA_VERSION` when one changes meaning.
 *
 * A query holds its SQL and plan and nothing else, because readers of the
 * first version refuse any other key there. What `--execute` measures goes
 * beside it: `executed` in the header, and per variant `timings_ms`, the
 * time of each statement in the order of `queries`, only when it ran.
 *
 * @phpstan-type Plan array{columns: list<string>, rows: list<list<string|null>>}
 * @phpstan-type Query array{sql: string, plan: Plan}
 */
final class QueryReport
{
    public const int SchemaVersion = 1;

    /**
     * @var list<array{class: string, subject: string, set: string, params: array<string, mixed>|stdClass, queries: list<Query>, timings_ms?: list<float>}>
     */
    private array $subjects = [];

    public function __construct(
        private readonly string $driver,
        private readonly bool $analyzed,
        private readonly string $group,
        private readonly bool $executed = false,
    ) {}

    /**
     * @param  list<Query>  $queries  every statement the variant ran, in order
     * @param  list<float>  $timings  the time each of them took, in milliseconds, when the variant was executed
     */
    public function add(Variant $variant, array $queries, array $timings = []): void
    {
        $subject = [
            'class' => $variant->class,
            'subject' => $variant->subject,
            'set' => $variant->set,
            // An empty parameter set is still an object in the JSON, not an array.
            'params' => $variant->params === [] ? new stdClass : $variant->params,
            'queries' => $queries,
        ];

        if ($this->executed) {
            $subject['timings_ms'] = $timings;
        }

        $this->subjects[] = $subject;
    }

    /**
     * Normalises the rows an explain statement returned: the column names
     * from the first row, every value cast to string with `null` kept, so a
     * plan reads the same from every driver.
     *
     * @param  list<object>  $rows  as `Connection::select()` returns them
     * @return Plan
     */
    public static function plan(array $rows): array
    {
        $rows = array_map(static fn (object $row): array => (array) $row, $rows);

        return [
            'columns' => $rows === [] ? [] : array_map(strval(...), array_keys($rows[0])),
            'rows' => array_map(
                static fn (array $row): array => array_values(array_map(
                    static fn (mixed $value): ?string => $value === null ? null : (string) $value,
                    $row,
                )),
                $rows,
            ),
        ];
    }

    /**
     * @return array{schema_version: int, driver: string, analyzed: bool, executed: bool, group: string, subjects: list<array{class: string, subject: string, set: string, params: array<string, mixed>|stdClass, queries: list<Query>, timings_ms?: list<float>}>}
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SchemaVersion,
            'driver' => $this->driver,
            'analyzed' => $this->analyzed,
            'executed' => $this->executed,
            'group' => $this->group,
            'subjects' => $this->subjects,
        ];
    }

    /**
     * Pretty-printed with unescaped slashes and a trailing newline, like
     * `bench:describe`.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
