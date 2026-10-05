<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Erasure\Actions\ExportViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Events\ViewHistoryExported;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
});

it('exports the views of a viewer, oldest first, without the visitor id', function (): void {
    View::factory()
        ->for($this->post, 'viewable')
        ->by($this->user)
        ->fromVisitor('cookie-1')
        ->viewedAt(Carbon::parse('2026-03-01 10:00:00'))
        ->inCollection('sidebar')
        ->withContext(['source' => 'newsletter'])
        ->create();
    View::factory()->for($this->post, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-03-02 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->by(User::factory()->create())->create();

    $export = $this->user->exportViewHistory();

    expect($export)->toBeInstanceOf(LazyCollection::class)
        ->and($export->all())->toEqual([
            [
                'viewable_type' => $this->post->getMorphClass(),
                'viewable_id' => $this->post->getKey(),
                'collection' => 'sidebar',
                'context' => ['source' => 'newsletter'],
                'viewed_at' => '2026-03-01T10:00:00+00:00',
            ],
            [
                'viewable_type' => $this->post->getMorphClass(),
                'viewable_id' => $this->post->getKey(),
                'collection' => null,
                'context' => null,
                'viewed_at' => '2026-03-02T10:00:00+00:00',
            ],
        ]);
});

it('exports the views of a visitor in chunks', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->count(3)->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-2')->create();

    expect(app(ExportViewHistory::class)->handle(Subject::visitor('cookie-1'), chunk: 2)->count())->toBe(3);
});

it('dispatches ViewHistoryExported', function (): void {
    Event::fake([ViewHistoryExported::class]);

    $this->user->exportViewHistory();

    Event::assertDispatched(ViewHistoryExported::class, fn (ViewHistoryExported $event): bool => $event->subject->viewerKey === $this->user->getKey());
});
