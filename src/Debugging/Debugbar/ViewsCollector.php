<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Debugging\Debugbar;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use DebugBar\DataCollector\MessagesCollector;
use Illuminate\Database\Eloquent\Model;

/**
 * Lists the views of the request in a Debugbar tab: the ones stored, the ones
 * handed to the queue and the ones a guard skipped, with the guard that did,
 * whether each kept the visitor active for the live counts, and the value of
 * every dimension a recorded view was given.
 *
 * Debugbar resets the messages at the start of every Octane request, so a
 * worker never shows the views of the request before.
 */
class ViewsCollector extends MessagesCollector
{
    public const string Name = 'eloquent_viewable';

    public function __construct()
    {
        parent::__construct(self::Name);
    }

    public function addAttempt(ViewAttempted $event): void
    {
        $attempt = $event->attempt;
        $guard = $event->result->skippedBy;

        [$outcome, $label] = $this->outcomeOf($event->result);

        $this->addMessage('{viewable} {outcome}', $label, [
            'viewable' => $this->describeViewable($attempt->viewable),
            'outcome' => $outcome,
            'guard' => $guard instanceof RecordingGuard
                ? $guard::class
                : null,
            'collection' => $attempt->collection,
            'present' => $event->result->present,
            'viewer' => $this->describeViewer($attempt->viewer),
            'cooldown' => $attempt->cooldown?->toIso8601String(),
            'context' => $attempt->context,
            'dimensions' => $event->result->dimensions,
        ]);
    }

    /** @return array<string, array{title?: string, icon?: string, widget?: string, map: string, default: string}> */
    #[\Override]
    public function getWidgets(): array
    {
        $name = self::Name;

        return [
            $name => [
                'title' => 'Viewable',
                'icon' => 'list',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => "{$name}.messages",
                'default' => '[]',
            ],
            "{$name}:badge" => [
                'map' => "{$name}.count",
                'default' => 'null',
            ],
        ];
    }

    /** @return array{string, string} */
    protected function outcomeOf(RecordResult $result): array
    {
        if ($result->skippedBy instanceof RecordingGuard) {
            $guard = class_basename($result->skippedBy);

            return ["skipped by {$guard}", 'warning'];
        }

        if ($result->queued) {
            return ['queued', 'info'];
        }

        return ['stored', 'success'];
    }

    protected function describeViewable(Viewable $viewable): string
    {
        $type = $viewable->getMorphClass();
        $key = ViewableKey::of($viewable);

        return "{$type}({$key})";
    }

    protected function describeViewer(?Model $viewer): ?string
    {
        if (! $viewer instanceof Model) {
            return null;
        }

        $type = $viewer->getMorphClass();
        $key = ViewerKey::of($viewer);

        return "{$type}({$key})";
    }
}
