<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Debugging\Debugbar\ViewsCollector;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\RefuseAll;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function attemptedView(RecordResult $result, ?ViewAttempt $attempt = null): ViewAttempted
{
    $attempt ??= new ViewAttempt(new Post(['id' => 7]), Mockery::mock(Visitor::class));

    return new ViewAttempted($attempt, $result);
}

/** @return array<string, mixed> */
function onlyMessage(ViewsCollector $collector): array
{
    $data = $collector->collect();

    expect($data['count'])->toBe(1);

    return $data['messages'][0];
}

it('lists a stored view', function (): void {
    $collector = new ViewsCollector;
    $type = Post::class;

    $collector->addAttempt(attemptedView(RecordResult::stored()));

    expect(onlyMessage($collector))
        ->message
        ->toBe("{$type}(7) stored")
        ->label
        ->toBe('success');
});

it('lists a queued view', function (): void {
    $collector = new ViewsCollector;
    $type = Post::class;

    $collector->addAttempt(attemptedView(RecordResult::queued()));

    expect(onlyMessage($collector))
        ->message
        ->toBe("{$type}(7) queued")
        ->label
        ->toBe('info');
});

it('lists a skipped view with the guard that refused it', function (): void {
    $collector = new ViewsCollector;
    $type = Post::class;

    $collector->addAttempt(attemptedView(RecordResult::skipped(new RefuseAll)));

    $message = onlyMessage($collector);

    expect($message)
        ->message
        ->toBe("{$type}(7) skipped by RefuseAll")
        ->label
        ->toBe('warning')
        ->and($message['context']['guard'])
        ->toContain(RefuseAll::class);
});

it('shows what the attempt asked for', function (): void {
    $collector = new ViewsCollector;
    $viewer = Apartment::class;

    $attempt = new ViewAttempt(
        new Post(['id' => 7]),
        Mockery::mock(Visitor::class),
        collection: 'amp',
        cooldown: Carbon::parse('2026-10-05 12:00:00', 'UTC'),
        viewer: new Apartment(['id' => 3]),
        context: ['source' => 'newsletter'],
    );

    $collector->addAttempt(attemptedView(RecordResult::stored(), $attempt));

    expect(onlyMessage($collector)['context'])
        ->collection
        ->toContain('amp')
        ->viewer
        ->toContain("{$viewer}(3)")
        ->cooldown
        ->toContain('2026-10-05T12:00:00+00:00')
        ->context
        ->toContain('newsletter');
});

it('starts empty again once Debugbar resets it', function (): void {
    $collector = new ViewsCollector;

    $collector->addAttempt(attemptedView(RecordResult::stored()));

    $collector->reset();

    expect($collector->collect()['count'])->toBe(0);
});

it('renders the views in a tab of its own with a count', function (): void {
    $collector = new ViewsCollector;

    expect($collector->getName())
        ->toBe(ViewsCollector::Name)
        ->and($collector->getWidgets())
        ->toMatchArray([
            'eloquent_viewable' => [
                'title' => 'Viewable',
                'icon' => 'list',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => 'eloquent_viewable.messages',
                'default' => '[]',
            ],
            'eloquent_viewable:badge' => [
                'map' => 'eloquent_viewable.count',
                'default' => 'null',
            ],
        ]);
});

it('shows whether the visitor was kept active', function (): void {
    $collector = new ViewsCollector;

    $collector->addAttempt(attemptedView(RecordResult::skipped(new RefuseAll)->withPresence(true)));

    expect(onlyMessage($collector)['context'])
        ->present
        ->toContain('true');
});
