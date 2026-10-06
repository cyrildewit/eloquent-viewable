# Eloquent Viewable

- `cyrildewit/eloquent-viewable` stores every view of an Eloquent model as its own row, then counts, charts and ranks them in SQL.
- A model needs `Contracts\Viewable` and `Concerns\InteractsWithViews`. Record and count through `views($model)` or the `Facades\Views` facade, never by writing `Models\View` rows, which skips the guards, cooldowns and caches.
- `remember()` without a lifetime caches forever and new views do not refresh it, so pass one: `remember(600)`.
- A `Period` includes its start and excludes its end.
- Activate the `eloquent-viewable-development` skill when recording or counting views, or when working with `views()`, `Views::`, `Period`, `Granularity`, the `views` middleware, `@viewsBeacon`, the `orderByViews()`, `orderByTrending()`, `whereViewedBy()` or `recommendedFor()` scopes, `recommended()`, `Views::fake()`, `config/eloquent-viewable.php`, or when deleting a user or answering a GDPR request.
