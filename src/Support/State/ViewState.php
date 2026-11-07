<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\State;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** The table holds one row per mark, so it is read whole and kept in memory. */
final class ViewState implements StateStore
{
    public const string Table = 'view_state';

    /** Other processes move the marks, so the table is read again after this many seconds. */
    private const int MaxAge = 5;

    private readonly ConnectionInterface $connection;

    /** @var ?array<string, string> */
    private ?array $values = null;

    private ?CarbonImmutable $readAt = null;

    public function __construct(View $view)
    {
        $this->connection = $view->getConnection();
    }

    public function installed(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable(self::Table);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function many(array $names): array
    {
        return array_intersect_key($this->values(), array_flip($names));
    }

    public function get(string $name): ?string
    {
        return $this->values()[$name] ?? null;
    }

    public function put(string $name, string $value): void
    {
        $this->table()->upsert([['name' => $name, 'value' => $value]], ['name'], ['value']);

        $this->values = null;
    }

    public function forget(string $name): void
    {
        $this->table()->where('name', $name)->delete();

        $this->values = null;
    }

    /** @return array<string, string> */
    private function values(): array
    {
        $now = CarbonImmutable::now();

        if ($this->values !== null && $this->fresh($now)) {
            return $this->values;
        }

        $values = [];

        foreach ($this->table()->pluck('value', 'name') as $name => $value) {
            $values[(string) $name] = (string) $value; // @phpstan-ignore cast.string (a text column)
        }

        $this->readAt = $now;

        return $this->values = $values;
    }

    /** A clock that moved back counts as stale, so the values never outlive their age. */
    private function fresh(CarbonImmutable $now): bool
    {
        return $this->readAt instanceof CarbonImmutable
            && $now >= $this->readAt
            && $now < $this->readAt->addSeconds(self::MaxAge);
    }

    private function table(): Builder
    {
        return $this->connection->table(self::Table);
    }
}
