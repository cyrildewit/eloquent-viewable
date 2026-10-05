<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Debugging\Debugbar;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use DebugBar\DataCollector\MessagesCollector;
use Illuminate\Database\Eloquent\Model;

/**
 * Lists the views of the request in a Debugbar tab: the ones stored, the ones
 * handed to the queue and the ones a guard skipped, with the guard that did.
 *
 * Debugbar resets the messages at the start of every Octane request, so a
 * worker never shows the views of the request before.
 */
final class ViewsCollector extends MessagesCollector
{
    public const string Name = 'eloquent_viewable';

    public function __construct()
    {
        parent::__construct(self::Name);
    }

    public function addAttempt(ViewAttempted $event): void
    {
        $attempt = $event->attempt;
        $result = $event->result;
        $guard = $result->skippedBy;

        [$outcome, $label] = match (true) {
            $guard instanceof RecordingGuard => ['skipped by '.class_basename($guard), 'warning'],
            $result->queued => ['queued', 'info'],
            default => ['stored', 'success'],
        };

        $this->addMessage('{viewable} {outcome}', $label, [
            'viewable' => $attempt->viewable->getMorphClass().'('.ViewableKey::of($attempt->viewable).')',
            'outcome' => $outcome,
            'guard' => $guard instanceof RecordingGuard ? $guard::class : null,
            'collection' => $attempt->collection,
            'viewer' => $attempt->viewer instanceof Model ? $attempt->viewer->getMorphClass().'('.ViewerKey::of($attempt->viewer).')' : null,
            'cooldown' => $attempt->cooldown?->toIso8601String(),
            'context' => $attempt->context,
        ]);
    }

    /** @return array<string, array{title?: string, icon?: string, widget?: string, map: string, default: string}> */
    #[\Override]
    public function getWidgets(): array
    {
        return [
            self::Name => [
                'title' => 'Viewable',
                'icon' => 'list',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => self::Name.'.messages',
                'default' => '[]',
            ],
            self::Name.':badge' => [
                'map' => self::Name.'.count',
                'default' => 'null',
            ],
        ];
    }
}
