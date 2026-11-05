<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\State;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

final readonly class ViewState implements StateStore
{
    public const string Table = 'view_state';

    private ConnectionInterface $connection;

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
        $values = [];

        foreach ($this->table()->whereIn('name', $names)->pluck('value', 'name') as $name => $value) {
            $values[(string) $name] = (string) $value; // @phpstan-ignore cast.string (a text column)
        }

        return $values;
    }

    public function get(string $name): ?string
    {
        $value = $this->table()->where('name', $name)->value('value');

        if (! is_string($value)) {
            return null;
        }

        return $value;
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

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    public function putMoment(string $name, CarbonInterface $moment): void
    {
        $this->put($name, $moment->format(self::Format));
    }

    private function table(): Builder
    {
        return $this->connection->table(self::Table);
    }
}
