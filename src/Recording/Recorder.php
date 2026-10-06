<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Recording\Contracts\LimitsRepeats;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Eloquent\Model;

final readonly class Recorder
{
    /** @param  list<RecordingGuard>  $guards */
    public function __construct(
        private array $guards,
        private Config $config,
        private BusDispatcher $bus,
        private EventDispatcher $events,
        private RecordsViews $action,
        private VisitorIdentity $identity,
        private PresenceStore $presence,
    ) {}

    /**
     * A guard that limits repeats, such as the cooldown, does not stop the
     * other guards from being asked, so the view is only skipped by it once
     * every other guard allowed it. The visitor is then still kept active.
     *
     * @throws RecordingFailed
     */
    public function record(ViewAttempt $attempt): RecordResult
    {
        $key = $this->keyOf($attempt);
        $attempt = $this->withViewer($attempt);
        $limitedBy = null;

        foreach ($this->guards as $guard) {
            if ($guard->allows($attempt)) {
                continue;
            }

            if ($guard instanceof LimitsRepeats) {
                $limitedBy ??= $guard;

                continue;
            }

            $this->events->dispatch(new ViewSkipped($attempt, $guard));

            return $this->attempted($attempt, RecordResult::skipped($guard));
        }

        if ($limitedBy instanceof RecordingGuard) {
            $this->events->dispatch(new ViewSkipped($attempt, $limitedBy));

            $present = $this->sight($attempt, $key);

            return $this->attempted($attempt, RecordResult::skipped($limitedBy)->withPresence($present));
        }

        $viewer = $attempt->viewer;
        $visitor = $this->identity->of($attempt->visitor, $viewer);

        $record = new ViewRecord(
            viewableId: $key,
            viewableType: $attempt->viewable->getMorphClass(),
            visitor: $visitor,
            collection: $attempt->collection,
            viewedAt: Carbon::now(),
            viewerType: $viewer?->getMorphClass(),
            viewerId: $viewer instanceof Model ? ViewerKey::of($viewer) : null,
            context: $attempt->context,
        );

        $result = $this->handOn($record, $attempt->queue ?? $this->config->queueEnabled());

        foreach ($this->guards as $guard) {
            if ($guard instanceof RemembersRecordedViews) {
                $guard->remember($attempt);
            }
        }

        $present = $this->sight($attempt, $key, $visitor);

        return $this->attempted($attempt, $result->withPresence($present));
    }

    /**
     * Keeps the visitor active without recording a view. The guards that
     * limit repeats are not asked, because a heartbeat is not a new view,
     * and no guard remembers it.
     *
     * @throws RecordingFailed
     */
    public function heartbeat(ViewAttempt $attempt): bool
    {
        $key = $this->keyOf($attempt);
        $attempt = $this->withViewer($attempt);

        if (! $this->config->presenceEnabled()) {
            return false;
        }

        foreach ($this->guards as $guard) {
            if ($guard instanceof LimitsRepeats) {
                continue;
            }

            if (! $guard->allows($attempt)) {
                return false;
            }
        }

        return $this->sight($attempt, $key);
    }

    /**
     * Stops counting the visitor on the viewable at once, rather than once
     * the window has passed.
     *
     * @throws RecordingFailed
     */
    public function leave(ViewAttempt $attempt): void
    {
        $key = $this->keyOf($attempt);
        $attempt = $this->withViewer($attempt);

        if (! $this->config->presenceEnabled()) {
            return;
        }

        $this->presence->leave($this->sightingOf($attempt, $key, $this->identity->of($attempt->visitor, $attempt->viewer)));
    }

    /** @throws RecordingFailed */
    private function keyOf(ViewAttempt $attempt): int|string
    {
        return ViewableKey::of($attempt->viewable) ?? throw RecordingFailed::cannotRecordViewForViewableType();
    }

    private function withViewer(ViewAttempt $attempt): ViewAttempt
    {
        $viewer = $this->viewerOf($attempt);

        if ($viewer === $attempt->viewer) {
            return $attempt;
        }

        return $attempt->withViewer($viewer);
    }

    private function sight(ViewAttempt $attempt, int|string $key, ?string $visitor = null): bool
    {
        if (! $this->config->presenceEnabled()) {
            return false;
        }

        $visitor ??= $this->identity->of($attempt->visitor, $attempt->viewer);

        $this->presence->touch($this->sightingOf($attempt, $key, $visitor));

        return true;
    }

    /**
     * The visitor id is hashed, so presence never keeps the id that the
     * views table and the cooldowns are keyed on.
     */
    private function sightingOf(ViewAttempt $attempt, int|string $key, string $visitor): Sighting
    {
        $viewer = $attempt->viewer;

        $reference = $viewer instanceof Model && $this->config->presenceTracksViewers()
            ? new Reference($viewer->getMorphClass(), ViewerKey::of($viewer))
            : null;

        return new Sighting(
            type: $attempt->viewable->getMorphClass(),
            key: $key,
            visitor: hash('xxh128', $visitor),
            seenAt: Carbon::now(),
            collection: $attempt->collection,
            viewer: $reference,
        );
    }

    /**
     * Every view passes through here, so the event is only built when
     * something listens, such as the Debugbar collector.
     */
    private function attempted(ViewAttempt $attempt, RecordResult $result): RecordResult
    {
        if ($this->events->hasListeners(ViewAttempted::class)) {
            $this->events->dispatch(new ViewAttempted($attempt, $result));
        }

        return $result;
    }

    private function viewerOf(ViewAttempt $attempt): ?Model
    {
        if ($attempt->viewer instanceof Model) {
            return $attempt->viewer;
        }

        return $this->config->viewerEnabled() ? $attempt->visitor->viewer() : null;
    }

    private function handOn(ViewRecord $record, bool $queue): RecordResult
    {
        if ($queue) {
            $this->bus->dispatch(
                new RecordViewJob($record)
                    ->onConnection($this->config->queueConnection())
                    ->onQueue($this->config->queueName())
            );

            return RecordResult::queued();
        }

        $this->action->handle($record);

        return RecordResult::stored();
    }
}
