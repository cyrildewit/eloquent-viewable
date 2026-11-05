# Recently viewed

An online course platform greets a signed-in learner with two rows: "Continue where you left off", the courses they
opened most recently, and "Not opened yet", new courses they have never looked at. A course page says when they last
opened it.

## The pieces

| File                                                                                                   | Role                                                |
|--------------------------------------------------------------------------------------------------------|-----------------------------------------------------|
| [`Course.php`](Course.php)                                                                             | The viewable model                                  |
| [`Learner.php`](Learner.php)                                                                           | The model people sign in as, with its view history  |
| [`ShowCourse.php`](ShowCourse.php)                                                                     | The course page, which says when it was last opened |
| [`LearnerHome.php`](LearnerHome.php)                                                                   | The two rows of the home page                       |
| [`create_courses_and_learners_tables.php`](database/migrations/create_courses_and_learners_tables.php) | The `courses` and `learners` tables                 |
| [`RecentlyViewedTest.php`](RecentlyViewedTest.php)                                                     | The behaviour below, as tests                       |

Store the signed-in learner with every view, and count them by account, in `config/eloquent-viewable.php`:

```php
'recording' => [
    'viewer' => [
        'enabled' => true,
    ],
],

'visitor' => [
    'identity' => 'viewer',
],
```

Record from the route, for example in `routes/web.php`:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

Route::get('/courses/{course}', ShowCourse::class)
    ->middleware(RecordViews::using('course', cooldown: 30));
```

Build the home page:

```php
$home = app(LearnerHome::class);

$home->continueWhereYouLeftOff($request->user()); // Intro to SQL, Git basics, Modern PHP
$home->notOpenedYet($request->user());            // the newest courses they have not opened
```

## Decisions

**Store the viewer, not only the visitor.** A visitor id says that the same browser came back; it cannot say which
courses Ada opened, and it changes when she switches from her laptop to her phone. With `recording.viewer.enabled` on,
every view of a signed-in learner carries her model in the `viewer_type` and `viewer_id` columns, which is what
`HasViewHistory` and `whereNotViewedBy()` read. The switch is off by default because it ties a view to a person, so turn
it on only where the product needs it, and say so in the privacy notice.

**Count an account once.** With the `viewer` identity, a learner who opens a course on her laptop and again on her
phone is one visitor in `unique()`, not two. Guests keep the cookie. A guest who signs in midway counts as two visitors,
one before and one after.

**Record from the route.** The course page has nothing to decide before a view counts, so the `views` middleware
records it and the controller stays free of tracking code. It records only a successful `GET`, so a course that does not
exist or a redirect to the login page adds nothing. When the controller must decide, such as skipping a preview by the
course's author, call `views()` in the controller instead.

**Read the last visit in the controller.** The middleware records after the response is built, so
`lastViewedAt()` in the controller still returns the visit before this one. That is exactly the date a "welcome back"
line wants, and it needs no extra query to exclude the current visit.

**One row per course.** `viewed()` is every view of the learner, newest first, so a course opened ten times appears ten
times. `LearnerHome` groups the views by course and orders the groups by their latest view, then loads the courses with
one query. A course deleted since drops out of the list. The 30 minute cooldown keeps a learner who clicks through the
lessons of one course from writing a row per click; the course still moves to the front once the cooldown has passed.

**Ask the database what is new.** `whereNotViewedBy()` adds a `NOT EXISTS` subquery over the learner's views, so the
"not opened yet" row is one query however many courses there are, and it pages like any other query.

## Where to take it next

- Show progress per course by recording each lesson into a [view collection](../../README.md#view-collections) and
  counting with `views($course)->collection('lessons')->viewedBy($learner)->count()`.
- Let learners clear their history. `$learner->viewed()->update(['viewer_type' => null, 'viewer_id' => null])` forgets
  who viewed what and keeps the course counts. See [deleting a user](../../README.md#deleting-a-user).
- Narrow "not opened yet" to the last month, so courses a learner ignored for a year come back:
  `Course::whereNotViewedBy($learner, Period::pastMonths(1))`.
