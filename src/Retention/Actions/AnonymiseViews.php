<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Actions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\Events\ViewsAnonymised;
use CyrildeWit\EloquentViewable\Retention\Exceptions\RetentionNotInstalled;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Timezone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Takes what ties a view to a person out of the views viewed in
 * `[anonymised, cutoff)`, one day at a time, on the clock of
 * `retention.rollups.timezone`. `visitor` is re-hashed under a
 * salt of that day, so the views of one visitor on one day keep sharing an
 * id but no id links two days, and once the salt is forgotten nothing leads
 * back. `viewer` and `context` become null.
 */
final readonly class AnonymiseViews
{
    public const string MARK = StateStore::ANONYMISED;

    public const string PREFIX = 'a:';

    private const string SALT = 'anonymise:salt:';

    public function __construct(
        private View $view,
        private RetentionState $state,
        private Watermarks $watermarks,
        private Dispatcher $events,
        private Config $config,
    ) {}

    /**
     * @param  list<'visitor'|'viewer'|'context'>  $columns
     *
     * @throws RetentionNotInstalled
     */
    public function handle(CarbonInterface $cutoff, array $columns, int $chunk, bool $dryRun = false): RetentionRun
    {
        $this->state->ensureInstalled();

        // Whole days on the clock rollups align to, so a day is never split
        // across two salts and a day bucket never holds two ids of a visitor.
        $requested = $this->midnight($cutoff);
        $until = $this->midnight($this->watermarks->clamp($requested));
        $from = $this->state->moment(self::MARK);
        $clamped = $until < $requested;

        if ($from instanceof CarbonInterface && $from >= $until) {
            return new RetentionRun($from, $until, 0, $clamped, $dryRun);
        }

        if ($dryRun) {
            return new RetentionRun($from, $until, $this->pending($from, $until, $columns)->count(), $clamped, true);
        }

        $views = 0;
        $cursor = $from;

        while (($day = $this->nextDay($cursor, $until, $columns)) instanceof CarbonInterface) {
            $views += $this->anonymiseDay($day, $columns, $chunk);
            $cursor = $this->nextMidnight($day);

            $this->state->putMoment(self::MARK, $cursor);
        }

        $this->state->putMoment(self::MARK, $until);

        if ($views > 0) {
            $this->events->dispatch(new ViewsAnonymised($from, $until, $views));
        }

        return new RetentionRun($from, $until, $views, $clamped, false);
    }

    /** @param  list<'visitor'|'viewer'|'context'>  $columns */
    private function nextDay(?CarbonInterface $from, CarbonInterface $until, array $columns): ?Carbon
    {
        $first = $this->pending($from, $until, $columns)->min('viewed_at');

        return is_string($first) ? $this->midnight(Carbon::parse($first)) : null;
    }

    /** @param  list<'visitor'|'viewer'|'context'>  $columns */
    private function anonymiseDay(Carbon $day, array $columns, int $chunk): int
    {
        $name = self::SALT.$day->avoidMutation()->setTimezone($this->zone())->toDateString();
        $salt = $this->state->get($name);

        // Kept until the day is done, so a run that stops halfway picks the
        // same salt up again and one visitor keeps one id.
        if ($salt === null) {
            $salt = bin2hex(random_bytes(32));

            $this->state->put($name, $salt);
        }

        $end = $this->nextMidnight($day);
        $views = 0;

        do {
            $visitors = $this->pending($day, $end, $columns)->orderBy('id')->limit($chunk)->pluck('visitor', 'id')->all();

            if ($visitors !== []) {
                $this->update($visitors, $columns, $salt);
            }

            $views += count($visitors);
        } while (count($visitors) === $chunk);

        $this->state->forget($name);

        return $views;
    }

    /**
     * The midnight at or before the moment, on the clock of `viewed_at`.
     *
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    private function midnight(CarbonInterface $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone($this->zone())->startOfDay()->setTimezone(date_default_timezone_get());
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    private function nextMidnight(Carbon $midnight): Carbon
    {
        return $midnight->avoidMutation()->setTimezone($this->zone())->addDay()->setTimezone(date_default_timezone_get());
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidTimezone
     */
    private function zone(): Timezone
    {
        $zone = $this->config->rollupTimezone();

        return $zone === null ? Timezone::application() : new Timezone($zone);
    }

    /**
     * One statement per chunk. Each visitor gets its own hash, so the new
     * values go through a `case` on the old one.
     *
     * @param  non-empty-array<int|string, mixed>  $visitors  keyed by view id
     * @param  list<'visitor'|'viewer'|'context'>  $columns
     */
    private function update(array $visitors, array $columns, string $salt): void
    {
        $connection = $this->view->getConnection();
        $grammar = $connection->getQueryGrammar();
        $sets = [];
        $bindings = [];

        if (in_array('visitor', $columns, true)) {
            $hashes = [];

            foreach ($visitors as $visitor) {
                if (is_string($visitor) && ! str_starts_with($visitor, self::PREFIX)) {
                    $hashes[$visitor] = self::PREFIX.hash_hmac('sha256', $visitor, $salt);
                }
            }

            if ($hashes !== []) {
                $visitor = $grammar->wrap('visitor');
                $sets[] = "{$visitor} = case {$visitor}".str_repeat(' when ? then ?', count($hashes))." else {$visitor} end";

                foreach ($hashes as $old => $new) {
                    $bindings[] = $old;
                    $bindings[] = $new;
                }
            }
        }

        if (in_array('viewer', $columns, true)) {
            $sets[] = "{$grammar->wrap('viewer_type')} = null";
            $sets[] = "{$grammar->wrap('viewer_id')} = null";
        }

        if (in_array('context', $columns, true)) {
            $sets[] = "{$grammar->wrap('context')} = null";
        }

        $ids = array_keys($visitors);

        $connection->update(
            "update {$grammar->wrapTable($this->view->getTable())} set ".implode(', ', $sets)
                ." where {$grammar->wrap('id')} in ({$grammar->parameterize($ids)})",
            [...$bindings, ...$ids],
        );
    }

    /**
     * The views in `[from, until)` that still hold something to anonymise.
     *
     * @param  list<'visitor'|'viewer'|'context'>  $columns
     */
    private function pending(?CarbonInterface $from, CarbonInterface $until, array $columns): Builder
    {
        return $this->view->newQuery()->toBase()
            ->when($from, fn (Builder $query, CarbonInterface $from): Builder => $query->where('viewed_at', '>=', $from))
            ->where('viewed_at', '<', $until)
            ->where(function (Builder $query) use ($columns): void {
                if (in_array('visitor', $columns, true)) {
                    $query->orWhere(fn (Builder $query): Builder => $query
                        ->whereNotNull('visitor')
                        ->where('visitor', 'not like', self::PREFIX.'%'));
                }

                if (in_array('viewer', $columns, true)) {
                    $query->orWhereNotNull('viewer_type')->orWhereNotNull('viewer_id');
                }

                if (in_array('context', $columns, true)) {
                    $query->orWhereNotNull('context');
                }
            });
    }
}
