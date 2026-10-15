<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Samples\RecentlyViewed\Course;
use CyrildeWit\EloquentViewable\Samples\RecentlyViewed\Learner;
use CyrildeWit\EloquentViewable\Samples\RecentlyViewed\LearnerHome;
use CyrildeWit\EloquentViewable\Samples\RecentlyViewed\ShowCourse;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // The web middleware encrypts the session and visitor cookies, and the
    // `viewer` identity is an HMAC under the application key.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    config([
        'eloquent-viewable.recording.viewer.enabled' => true,
        'eloquent-viewable.visitor.identity' => 'viewer',
    ]);

    Route::get('/courses/{course}', ShowCourse::class)
        ->middleware(['web', RecordViews::using('course', cooldown: 30)]);
});

/**
 * Opens the course on a new device: a fresh session, so no cooldown is
 * carried over from the last time.
 */
function openCourse(Course $course): void
{
    session()->flush();

    test()->get("/courses/{$course->id}")->assertOk();
}

it('records the signed-in learner as the viewer from the route', function (): void {
    $learner = Learner::create(['name' => 'Ada']);
    $course = Course::create(['title' => 'Intro to SQL']);

    $this->actingAs($learner);
    openCourse($course);

    expect($learner->hasViewed($course))->toBeTrue()
        ->and($course->views()->sole()->viewer?->is($learner))->toBeTrue();
});

it('records a guest without a viewer', function (): void {
    $course = Course::create(['title' => 'Intro to SQL']);

    openCourse($course);

    expect($course)->toHaveViewsCount(1)
        ->and($course->views()->sole()->viewer)->toBeNull();
});

it('lists the courses the learner opened, most recent first and each once', function (): void {
    $learner = Learner::create(['name' => 'Ada']);
    $sql = Course::create(['title' => 'Intro to SQL']);
    $php = Course::create(['title' => 'Modern PHP']);
    $git = Course::create(['title' => 'Git basics']);
    Course::create(['title' => 'Never opened']);

    $this->actingAs($learner);
    openCourse($sql);
    $this->travel(1)->hours();
    openCourse($php);
    $this->travel(1)->hours();
    openCourse($git);
    $this->travel(1)->hours();
    openCourse($sql);

    $recent = app(LearnerHome::class)->continueWhereYouLeftOff($learner);

    expect($recent->pluck('title')->all())->toBe(['Intro to SQL', 'Git basics', 'Modern PHP']);
});

it('keeps the history of one learner apart from another', function (): void {
    $ada = Learner::create(['name' => 'Ada']);
    $linus = Learner::create(['name' => 'Linus']);
    $course = Course::create(['title' => 'Intro to SQL']);

    $this->actingAs($linus);
    openCourse($course);

    expect(app(LearnerHome::class)->continueWhereYouLeftOff($ada))->toBeEmpty()
        ->and($ada->hasViewed($course))->toBeFalse();
});

it('suggests the newest courses the learner has not opened', function (): void {
    $learner = Learner::create(['name' => 'Ada']);
    $opened = Course::create(['title' => 'Intro to SQL']);
    Course::create(['title' => 'Modern PHP']);
    Course::create(['title' => 'Git basics']);

    $this->actingAs($learner);
    openCourse($opened);

    $suggestions = app(LearnerHome::class)->notOpenedYet($learner);

    expect($suggestions->pluck('title')->all())->toBe(['Git basics', 'Modern PHP']);
});

it('tells the learner when they last opened the course before', function (): void {
    $learner = Learner::create(['name' => 'Ada']);
    $course = Course::create(['title' => 'Intro to SQL']);
    $this->actingAs($learner);

    // On a whole second, because MySQL rounds the fraction of one away.
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    $this->get("/courses/{$course->id}")->assertJson(['last_opened_at' => null]);

    $this->travelTo(Carbon::parse('2026-10-03 09:00:00'));
    session()->flush();

    $this->get("/courses/{$course->id}")->assertJson(['last_opened_at' => '2026-10-01T10:00:00+00:00']);
});

it('counts a learner on two devices as one visitor', function (): void {
    $learner = Learner::create(['name' => 'Ada']);
    $course = Course::create(['title' => 'Intro to SQL']);

    $this->actingAs($learner);
    openCourse($course);
    openCourse($course);

    expect($course)->toHaveViewsCount(2)
        ->toHaveUniqueViewsCount(1);
});
