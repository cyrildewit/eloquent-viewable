<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Spikes\Data\Episode;
use CyrildeWit\EloquentViewable\Spikes\Exceptions\SpikesNotInstalled;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * The episodes table keeps one row per model and direction while the model
 * spikes or drops. A row is only ever started and settled by one statement,
 * so two runs at once never both report the same change.
 *
 * @internal
 */
final readonly class SpikeEpisodes
{
    private const string Format = 'Y-m-d H:i:s';

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
        return $this->connection->getSchemaBuilder()->hasTable($this->config->spikesTable());
    }

    /**
     * @throws InvalidConfiguration
     * @throws SpikesNotInstalled
     */
    public function ensureInstalled(): void
    {
        if (! $this->installed()) {
            throw SpikesNotInstalled::missingTable($this->config->spikesTable());
        }
    }

    /**
     * It reads the open episodes of a type, keyed by model key as a string.
     *
     * @return array<string, Episode>
     *
     * @throws InvalidConfiguration
     */
    public function open(string $type, Direction $direction): array
    {
        $episodes = [];

        $rows = $this->table()
            ->where('viewable_type', $type)
            ->where('direction', $direction->value)
            ->get();

        /** @var stdClass&object{viewable_id: int|string, since: string, peak_count: int|string, peak_score: int|float|string, quiet_since: ?string} $row */
        foreach ($rows as $row) {
            $episodes[(string) $row->viewable_id] = new Episode(
                $row->viewable_id,
                CarbonImmutable::parse($row->since),
                (int) $row->peak_count,
                (float) $row->peak_score,
                $row->quiet_since === null ? null : CarbonImmutable::parse($row->quiet_since),
            );
        }

        return $episodes;
    }

    /**
     * Open an episode, and say whether this call did.
     *
     * @throws InvalidConfiguration
     */
    public function start(string $type, int|string $key, Direction $direction, CarbonInterface $since, int $count, float $score): bool
    {
        $inserted = $this->table()->insertOrIgnore([
            'viewable_type' => $type,
            'viewable_id' => $key,
            'direction' => $direction->value,
            'since' => $since->format(self::Format),
            'peak_count' => $count,
            'peak_score' => $score,
            'quiet_since' => null,
        ]);

        return $inserted > 0;
    }

    /**
     * The model still spikes or drops, so it is no longer quiet, and a more
     * extreme score becomes the peak.
     *
     * @throws InvalidConfiguration
     */
    public function continue(string $type, Episode $episode, Direction $direction, int $count, float $score): void
    {
        $further = $direction === Direction::Spike
            ? $score > $episode->peakScore
            : $score < $episode->peakScore;

        $values = $further
            ? ['peak_count' => $count, 'peak_score' => $score, 'quiet_since' => null]
            : ['quiet_since' => null];

        $this->episode($type, $episode->key, $direction)->update($values);
    }

    /**
     * The model is back to normal from this moment, unless it already was.
     *
     * @throws InvalidConfiguration
     */
    public function quiet(string $type, Episode $episode, Direction $direction, CarbonInterface $moment): void
    {
        $this->episode($type, $episode->key, $direction)
            ->whereNull('quiet_since')
            ->update(['quiet_since' => $moment->format(self::Format)]);
    }

    /**
     * Close an episode, and say whether this call did.
     *
     * @throws InvalidConfiguration
     */
    public function settle(string $type, Episode $episode, Direction $direction): bool
    {
        return $this->episode($type, $episode->key, $direction)->delete() > 0;
    }

    /** @throws InvalidConfiguration */
    private function episode(string $type, int|string $key, Direction $direction): Builder
    {
        return $this->table()
            ->where('viewable_type', $type)
            ->where('direction', $direction->value)
            ->where('viewable_id', $key);
    }

    /** @throws InvalidConfiguration */
    private function table(): Builder
    {
        return $this->connection->table($this->config->spikesTable());
    }
}
