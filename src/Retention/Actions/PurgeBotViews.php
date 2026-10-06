<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Actions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Refolder;
use CyrildeWit\EloquentViewable\Retention\Bots\BurstDetector;
use CyrildeWit\EloquentViewable\Retention\Data\PurgeRun;
use CyrildeWit\EloquentViewable\Retention\Events\BotViewsPurged;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * This action deletes the views of bots from `[since, now)`: by default only
 * the views inside a burst, the views `IgnoreBursts` would have refused. With
 * `$minBursts` it deletes every view of a visitor with at least that many
 * separate bursts instead. Views of a signed-in viewer are left alone unless
 * `$includeViewers` is set.
 *
 * The views are read in the order they were viewed, through the `viewed_at`
 * index, a chunk at a time. Once views are deleted, the rollups are folded
 * again from the first of them, and the start is clamped to where they can
 * still be.
 */
final readonly class PurgeBotViews
{
    public function __construct(
        private View $view,
        private Refolder $refolder,
        private Dispatcher $events,
    ) {}

    public function handle(
        CarbonInterface $since,
        int $max,
        int $seconds,
        int $chunk,
        bool $includeViewers = false,
        ?int $minBursts = null,
        bool $dryRun = false,
    ): PurgeRun {
        $until = Carbon::now();
        $floor = $this->refolder->floor();
        $clamped = $floor instanceof CarbonInterface && $floor > $since;
        $from = $clamped ? $floor : $since;

        if ($from >= $until) {
            return new PurgeRun($from, $until, 0, 0, $clamped, $dryRun);
        }

        $detector = new BurstDetector($max, $seconds);

        [$views, $earliest] = $minBursts === null
            ? $this->purgeBursts($detector, $from, $until, $chunk, $includeViewers, $dryRun)
            : $this->purgeVisitors($detector, $from, $until, $chunk, $includeViewers, $minBursts, $dryRun);

        $visitors = count($detector->visitorsWithBursts($minBursts ?? 1));

        if ($dryRun) {
            return new PurgeRun($from, $until, $views, $visitors, $clamped, true);
        }

        if (! $earliest instanceof CarbonInterface) {
            return new PurgeRun($from, $until, $views, $visitors, $clamped, false);
        }

        $this->refolder->refold($earliest);

        $this->events->dispatch(new BotViewsPurged($from, $until, $views, $visitors));

        return new PurgeRun($from, $until, $views, $visitors, $clamped, false);
    }

    /** @return array{int, ?CarbonInterface} */
    private function purgeBursts(BurstDetector $detector, CarbonInterface $from, CarbonInterface $until, int $chunk, bool $includeViewers, bool $dryRun): array
    {
        $views = 0;
        $earliest = null;
        $pending = [];

        foreach ($this->scan($from, $until, $chunk, $includeViewers) as $row) {
            foreach ($detector->feed($row['id'], $row['visitor'], $row['viewable'], $row['at']) as $id => $at) {
                $pending[] = $id;
                $earliest = $earliest === null ? $at : min($earliest, $at);
            }

            if (count($pending) >= $chunk) {
                $views += $this->delete($pending, $dryRun);
                $pending = [];
            }
        }

        $views += $this->delete($pending, $dryRun);

        return [$views, $earliest === null ? null : $this->moment($earliest)];
    }

    /** @return array{int, ?CarbonInterface} */
    private function purgeVisitors(BurstDetector $detector, CarbonInterface $from, CarbonInterface $until, int $chunk, bool $includeViewers, int $minBursts, bool $dryRun): array
    {
        foreach ($this->scan($from, $until, $chunk, $includeViewers) as $row) {
            $detector->feed($row['id'], $row['visitor'], $row['viewable'], $row['at']);
        }

        $views = 0;
        $earliest = null;

        foreach (array_chunk($detector->visitorsWithBursts($minBursts), max(1, $chunk)) as $visitors) {
            $first = $this->ofVisitors($from, $until, $includeViewers, $visitors)->min('viewed_at');

            if (is_string($first)) {
                $first = Carbon::parse($first);
                $earliest = $earliest?->min($first) ?? $first;
            }

            $views += $dryRun
                ? $this->ofVisitors($from, $until, $includeViewers, $visitors)->count()
                : $this->deleteOfVisitors($from, $until, $includeViewers, $visitors, $chunk);
        }

        return [$views, $earliest];
    }

    /**
     * It pages by `(viewed_at, id)` rather than by offset, so every page is a
     * range read on the index, however far into the table it is.
     *
     * @return Generator<int, array{id: int|string, visitor: string, viewable: string, at: int}>
     */
    private function scan(CarbonInterface $from, CarbonInterface $until, int $chunk, bool $includeViewers): Generator
    {
        $after = null;

        do {
            $rows = $this->view
                ->newQuery()
                ->toBase()
                ->select(['id', 'viewable_type', 'viewable_id', 'visitor', 'viewed_at'])
                ->where('viewed_at', '>=', $from)
                ->where('viewed_at', '<', $until)
                ->whereNotNull('visitor')
                ->when(! $includeViewers, fn (Builder $query): Builder => $query->whereNull('viewer_id'))
                ->when($after, fn (Builder $query, array $after): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('viewed_at', '>', $after[0])
                        ->orWhere(fn (Builder $query): Builder => $query->where('viewed_at', $after[0])->where('id', '>', $after[1])),
                ))
                ->orderBy('viewed_at')
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            foreach ($rows as $row) {
                /** @var array{id: int|string, viewable_type: string, viewable_id: int|string, visitor: string, viewed_at: string} $row */
                $row = (array) $row;
                $after = [$row['viewed_at'], $row['id']];

                yield [
                    'id' => $row['id'],
                    'visitor' => $row['visitor'],
                    'viewable' => "{$row['viewable_type']}|{$row['viewable_id']}",
                    'at' => Carbon::parse($row['viewed_at'], 'UTC')->getTimestamp(),
                ];
            }
        } while ($rows->count() === $chunk);
    }

    /** @param  list<int|string>  $ids */
    private function delete(array $ids, bool $dryRun): int
    {
        if ($ids === []) {
            return 0;
        }

        if ($dryRun) {
            return count($ids);
        }

        return $this->view->newQuery()->toBase()->whereIn('id', $ids)->delete();
    }

    /**
     * The ids are selected first, because Postgres has no `delete … limit`
     * and MySQL refuses a limited subquery on the table it deletes from.
     *
     * @param  list<string>  $visitors
     */
    private function deleteOfVisitors(CarbonInterface $from, CarbonInterface $until, bool $includeViewers, array $visitors, int $chunk): int
    {
        $views = 0;

        do {
            /** @var list<int|string> $ids */
            $ids = $this->ofVisitors($from, $until, $includeViewers, $visitors)->limit($chunk)->pluck('id')->all();

            $views += $this->delete($ids, false);
        } while (count($ids) === $chunk);

        return $views;
    }

    /** @param  list<string>  $visitors */
    private function ofVisitors(CarbonInterface $from, CarbonInterface $until, bool $includeViewers, array $visitors): Builder
    {
        return $this->view
            ->newQuery()
            ->toBase()
            ->where('viewed_at', '>=', $from)
            ->where('viewed_at', '<', $until)
            ->whereIn('visitor', $visitors)
            ->when(! $includeViewers, fn (Builder $query): Builder => $query->whereNull('viewer_id'));
    }

    /**
     * Timestamps are read as UTC, so the gaps between views stay true across
     * a change to daylight saving time, and turned back the same way.
     */
    private function moment(int $timestamp): CarbonInterface
    {
        return Carbon::parse(Carbon::createFromTimestamp($timestamp, 'UTC')->format('Y-m-d H:i:s'));
    }
}
