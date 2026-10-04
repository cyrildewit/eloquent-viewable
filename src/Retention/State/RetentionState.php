<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\State;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Names and values in the `view_retention_state` table, on the connection of
 * the views table. Moments are kept on the clock of `viewed_at`.
 */
final readonly class RetentionState implements StateStore
{
    public const string TABLE = 'view_retention_state';

    private ConnectionInterface $connection;

    public function __construct(View $view)
    {
        $this->connection = $view->getConnection();
    }

    /** @throws RetentionNotInstalled */
    public function ensureInstalled(): void
    {
        if (! $this->installed()) {
            throw RetentionNotInstalled::missingTable(self::TABLE);
        }
    }

    public function installed(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable(self::TABLE);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function many(array $names): array
    {
        $values = [];

        foreach ($this->table()->whereIn('name', $names)->pluck('value', 'name') as $name => $value) {
            $values[(string) $name] = (string) $value; // @phpstan-ignore cast.string (a text column)
        }

        return $values;
    }

    public function get(string $name): ?string
    {
        $value = $this->table()->where('name', $name)->value('value');

        return is_string($value) ? $value : null;
    }

    public function put(string $name, string $value): void
    {
        $this->table()->upsert([['name' => $name, 'value' => $value]], ['name'], ['value']);
    }

    public function forget(string $name): void
    {
        $this->table()->where('name', $name)->delete();
    }

    public function moment(string $name): ?CarbonInterface
    {
        $value = $this->get($name);

        return $value === null ? null : Carbon::parse($value);
    }

    public function putMoment(string $name, CarbonInterface $moment): void
    {
        $this->put($name, $moment->format(self::FORMAT));
    }

    private function table(): Builder
    {
        return $this->connection->table(self::TABLE);
    }
}
