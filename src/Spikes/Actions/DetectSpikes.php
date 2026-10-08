<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Growth\GrowthRanking;
use CyrildeWit\EloquentViewable\Spikes\Data\Episode;
use CyrildeWit\EloquentViewable\Spikes\Data\SpikeRun;
use CyrildeWit\EloquentViewable\Spikes\Direction;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsDropped;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSettled;
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSpiked;
use CyrildeWit\EloquentViewable\Spikes\Exceptions\SpikesNotInstalled;
use CyrildeWit\EloquentViewable\Spikes\SpikeEpisodes;
use CyrildeWit\EloquentViewable\Spikes\SpikeSettings;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * This action compares each watched model's views in the last closed window,
 * the hours before the current one began, with the same window on past days
 * or weeks. A model that leaves its baseline opens an episode and dispatches
 * one event; while it stays out, the episode keeps its peak. Once it has been
 * back to normal for the cooldown, the episode settles with one more event.
 * Every event is dispatched after the row it reports is written. Spikes and
 * drops are scored from one read of the windows.
 *
 * @internal
 *
 * @phpstan-import-type GrowthRow from GrowthRanking
 */
final readonly class DetectSpikes
{
    public function __construct(
        private Config $config,
        private ViewSource $source,
        private GrowthRanking $growth,
        private SpikeEpisodes $episodes,
        private Dispatcher $events,
    ) {}

    /**
     * @return list<SpikeRun>
     *
     * @throws InvalidBaseline
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws SpikesNotInstalled
     * @throws UnsupportedBySource
     */
    public function handle(): array
    {
        $watched = SpikeSettings::fromConfig($this->config);

        if ($watched === []) {
            return [];
        }

        $this->episodes->ensureInstalled();

        $source = $this->source;

        if (! $source instanceof CountsByWindow) {
            throw UnsupportedBySource::growth($source);
        }

        $runs = [];

        foreach ($watched as $settings) {
            $end = CarbonImmutable::now()->startOfHour();
            $window = Period::create($end->subHours($settings->hours), $end);

            $model = new ($settings->class);
            $rows = $this->growth->zScores($source, $model, new ViewsQuery($window), $settings->seasonality, $settings->samples, $settings->minimum);

            $spikes = $this->watch($model->getMorphClass(), $settings, $this->beyond($rows, $settings->threshold), $end, Direction::Spike);
            $drops = $settings->drops
                ? $this->watch($model->getMorphClass(), $settings, $this->beyond($rows, -$settings->threshold), $end, Direction::Drop)
                : ['started' => 0, 'settled' => 0];

            $runs[] = new SpikeRun($settings->class, $spikes['started'], $drops['started'], $spikes['settled'] + $drops['settled']);
        }

        return $runs;
    }

    /**
     * The rows at or above a positive threshold, or at or below a negative
     * one.
     *
     * @param  list<GrowthRow>  $rows
     * @return list<GrowthRow>
     */
    private function beyond(array $rows, float $threshold): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => $threshold > 0 ? $row['score'] >= $threshold : $row['score'] <= $threshold));
    }

    /**
     * @param  list<GrowthRow>  $rows
     * @return array{started: int, settled: int}
     *
     * @throws InvalidConfiguration
     */
    private function watch(string $type, SpikeSettings $settings, array $rows, CarbonImmutable $end, Direction $direction): array
    {
        $open = $this->episodes->open($type, $direction);
        $events = [];

        foreach ($rows as $row) {
            $episode = $open[(string) $row['id']] ?? null;

            unset($open[(string) $row['id']]);

            if ($episode instanceof Episode) {
                $this->episodes->continue($type, $episode, $direction, $row['count'], $row['score']);

                continue;
            }

            if (! $this->episodes->start($type, $row['id'], $direction, $end, $row['count'], $row['score'])) {
                continue;
            }

            $events[] = $direction === Direction::Spike
                ? new ViewsSpiked($type, $row['id'], $row['baseline'])
                : new ViewsDropped($type, $row['id'], $row['baseline']);
        }

        $started = count($events);

        foreach ($open as $episode) {
            if (! $episode->quietSince instanceof CarbonImmutable) {
                $this->episodes->quiet($type, $episode, $direction, $end);

                continue;
            }

            if ($episode->quietSince > $settings->cooldown->before($end)) {
                continue;
            }

            if (! $this->episodes->settle($type, $episode, $direction)) {
                continue;
            }

            $events[] = new ViewsSettled($type, $episode->key, $direction, $episode->peakCount, $episode->peakScore, $episode->since);
        }

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        return ['started' => $started, 'settled' => count($events) - $started];
    }
}
