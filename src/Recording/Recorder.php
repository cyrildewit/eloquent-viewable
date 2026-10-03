<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
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
    ) {}

    /** @throws RecordingFailed */
    public function record(ViewAttempt $attempt): RecordResult
    {
        $key = ViewableKey::of($attempt->viewable);

        if ($key === null) {
            throw RecordingFailed::cannotRecordViewForViewableType();
        }

        $viewer = $this->viewerOf($attempt);

        if ($viewer !== $attempt->viewer) {
            $attempt = $attempt->withViewer($viewer);
        }

        foreach ($this->guards as $guard) {
            if (! $guard->allows($attempt)) {
                $this->events->dispatch(new ViewSkipped($attempt, $guard));

                return RecordResult::skipped($guard);
            }
        }

        $record = new ViewRecord(
            viewableId: $key,
            viewableType: $attempt->viewable->getMorphClass(),
            visitor: $this->identity->of($attempt->visitor, $viewer),
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
