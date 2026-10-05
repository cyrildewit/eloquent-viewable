<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Actions;

use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryAnonymised;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\TouchedViewables;
use CyrildeWit\EloquentViewable\Erasure\ViewHistory;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Cache\CacheVersions;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Timezone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * This action keeps the views of a subject but takes what ties them to it
 * out, the way retention anonymises old views: `visitor` is re-hashed under
 * a salt per day on the clock of `retention.rollups.timezone`, which is
 * thrown away after the run, and `viewer` and `context` become null. Counts
 * and daily unique visitors stay the same.
 */
final readonly class AnonymiseViewHistory
{
    public function __construct(
        private ViewHistory $history,
        private View $view,
        private CacheVersions $versions,
        private Config $config,
        private Dispatcher $events,
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
        $views = 0;
        $after = 0;

        do {
            $rows = $this->history
                ->query($selection)
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit($chunk)
                ->get(['id', 'viewable_type', 'viewable_id', 'visitor', 'viewed_at']);

            foreach ($rows as $row) {
                $touched->add($row->viewable_type, $row->viewable_id);
                $after = $row->id;
            }

            $this->anonymise($rows, $zone, $salts);

            $views += $rows->count();
        } while ($rows->count() === $chunk);

        $touched->forget($this->versions);

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

        foreach ($groups as $visitor => $ids) {
            $this->view->newQuery()->toBase()->whereIn('id', $ids)->update([
                'visitor' => $visitor === '' ? null : $visitor,
                'viewer_type' => null,
                'viewer_id' => null,
                'context' => null,
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
        $prefix = AnonymiseViews::Prefix;

        if ($view->visitor === null) {
            return null;
        }

        if (str_starts_with($view->visitor, $prefix)) {
            return $view->visitor;
        }

        $day = Carbon::parse($view->viewed_at)->setTimezone($zone)->toDateString();

        $salts[$day] ??= bin2hex(random_bytes(32));

        $hash = hash_hmac('sha256', $view->visitor, $salts[$day]);

        return "{$prefix}{$hash}";
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    private function zone(): Timezone
    {
        $zone = $this->config->rollupTimezone();

        if ($zone === null) {
            return Timezone::application();
        }

        return new Timezone($zone);
    }
}
