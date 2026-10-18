<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Actions;

use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryAnonymised;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\TouchedViewables;
use CyrildeWit\EloquentViewable\Erasure\ViewHistory;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Visitors\AnonymisedVisitor;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * This action keeps the views of a subject but takes what ties them to it
 * out, the way retention anonymises old views: `visitor` is re-hashed under
 * a salt per day on the clock of `retention.rollups.timezone`, which is
 * thrown away after the run, and `viewer`, `context` and the dimensions
 * marked personal become null. Counts and daily unique visitors stay the
 * same.
 */
final readonly class AnonymiseViewHistory
{
    public function __construct(
        private ViewHistory $history,
        private View $view,
        private Config $config,
        private Dispatcher $events,
        private DimensionRegistry $dimensions,
    ) {}

    /**
     * An anonymised view no longer matches, but a visitor id that was
     * anonymised already keeps matching, so the run walks the ids.
     *
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    public function handle(Subject $subject, int $chunk = 1000): int
    {
        $selection = $this->history->select($subject);

        $this->history->land($selection);

        $zone = $this->zone();
        $touched = new TouchedViewables;
        $salts = [];
        $after = 0;

        $views = Chunks::run(
            Deadline::none(),
            $chunk,
            function () use ($selection, $chunk, &$after): Collection {
                return $this->history
                    ->query($selection)
                    ->where('id', '>', $after)
                    ->orderBy('id')
                    ->limit($chunk)
                    ->get(['id', 'viewable_type', 'viewable_id', 'visitor', 'viewed_at']);
            },
            function (Collection $rows) use ($touched, $zone, &$salts, &$after): int {
                foreach ($rows as $row) {
                    $touched->add($row->viewable_type, $row->viewable_id);
                    $after = $row->id;
                }

                $this->anonymise($rows, $zone, $salts);

                return $rows->count();
            },
        )->count;

        $this->events->dispatch($touched->countsChanged());

        $this->events->dispatch(new ViewHistoryAnonymised($subject, $views));

        return $views;
    }

    /**
     * The views that get the same visitor id are updated in one statement.
     * The salts are keyed by the day.
     *
     * @param  Collection<int, View>  $rows
     * @param  array<string, string>  $salts
     */
    private function anonymise(Collection $rows, Timezone $zone, array &$salts): void
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$this->anonymisedVisitor($row, $zone, $salts) ?? ''][] = $row->id;
        }

        $personal = array_fill_keys($this->dimensions->personalColumns(), null);

        foreach ($groups as $visitor => $ids) {
            $this->view->newQuery()->toBase()->whereIn('id', $ids)->update([
                'visitor' => $visitor === '' ? null : $visitor,
                'viewer_type' => null,
                'viewer_id' => null,
                'context' => null,
                ...$personal,
            ]);
        }
    }

    /**
     * A missing visitor id stays missing and an anonymised one stays as it
     * is. The salts are keyed by the day.
     *
     * @param  array<string, string>  $salts
     */
    private function anonymisedVisitor(View $view, Timezone $zone, array &$salts): ?string
    {
        if ($view->visitor === null || AnonymisedVisitor::isAnonymised($view->visitor)) {
            return $view->visitor;
        }

        $day = Carbon::parse($view->viewed_at)->setTimezone($zone)->toDateString();

        $salts[$day] ??= bin2hex(random_bytes(32));

        return AnonymisedVisitor::hash($view->visitor, $salts[$day]);
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    private function zone(): Timezone
    {
        return Timezone::named($this->config->rollupTimezone());
    }
}
