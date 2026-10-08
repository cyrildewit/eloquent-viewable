<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones;

use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Milestones\Exceptions\MilestonesNotInstalled;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * The marks keep, per model and counter column, the highest count a threshold
 * was crossed at. A mark only ever goes up, so a count that drops and climbs
 * back crosses nothing twice. A model without a mark has crossed nothing.
 *
 * @internal
 */
final readonly class MilestoneMarks
{
    private ConnectionInterface $connection;

    public function __construct(
        View $view,
        private Config $config,
    ) {
        $this->connection = $view->getConnection();
    }

    /** @throws InvalidConfiguration */
    public function installed(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($this->config->milestonesTable());
    }

    /**
     * @throws InvalidConfiguration
     * @throws MilestonesNotInstalled
     */
    public function ensureInstalled(): void
    {
        if (! $this->installed()) {
            throw MilestonesNotInstalled::missingTable($this->config->milestonesTable());
        }
    }

    /**
     * It reads the marks of the models with these keys, keyed by the key as a
     * string. A model without a mark is left out.
     *
     * @param  list<int|string>  $keys
     * @return array<string, int>
     *
     * @throws InvalidConfiguration
     */
    public function of(string $type, string $column, array $keys): array
    {
        $marks = [];

        $rows = $this->table()
            ->where('viewable_type', $type)
            ->where('column', $column)
            ->whereIn('viewable_id', $keys)
            ->pluck('high_water', 'viewable_id');

        foreach ($rows as $key => $highWater) {
            $marks[(string) $key] = (int) $highWater; // @phpstan-ignore cast.int (an integer column)
        }

        return $marks;
    }

    /**
     * It raises the mark of one model to the count, and says whether it moved.
     * A run that raised it first wins, so two runs never both report the same
     * crossing.
     *
     * @throws InvalidConfiguration
     */
    public function raise(string $type, int|string $key, string $column, int $count, ?int $mark): bool
    {
        if ($mark === null) {
            $inserted = $this->table()->insertOrIgnore([
                'viewable_type' => $type,
                'viewable_id' => $key,
                'column' => $column,
                'high_water' => $count,
            ]);

            if ($inserted > 0) {
                return true;
            }
        }

        $updated = $this->table()
            ->where('viewable_type', $type)
            ->where('column', $column)
            ->where('viewable_id', $key)
            ->where('high_water', '<', $count)
            ->update(['high_water' => $count]);

        return $updated > 0;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    /** @throws InvalidConfiguration */
    private function table(): Builder
    {
        return $this->connection->table($this->config->milestonesTable());
    }
}
