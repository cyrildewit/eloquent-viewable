<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletableView;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Config;

$sqliteOnly = fn (): bool => driver() !== 'sqlite';

it('reads the connection name from the config', function (): void {
    Config::set('database.connections.analytics', ['driver' => 'sqlite', 'database' => ':memory:']);
    Config::set('eloquent-viewable.models.view.connection', 'analytics');

    expect(new View()->getConnectionName())->toBe('analytics');
});

it('reads the table name from the config', function (): void {
    Config::set('eloquent-viewable.models.view.table_name', 'page_views');

    expect(new View()->getTable())->toBe('page_views');
});

it('prefers the connection name set on the model over the config', function (): void {
    Config::set('database.connections.analytics', ['driver' => 'sqlite', 'database' => ':memory:']);
    Config::set('eloquent-viewable.models.view.connection', 'analytics');

    $view = new class extends View
    {
        protected $connection = 'testing';
    };

    expect($view->getConnectionName())->toBe('testing');
});

it('prefers the table name set on the model over the config', function (): void {
    Config::set('eloquent-viewable.models.view.table_name', 'page_views');

    $view = new class extends View
    {
        protected $table = 'article_views';
    };

    expect($view->getTable())->toBe('article_views');
});

it('can belong to viewable model', function (): void {
    $post = Post::factory()->create();

    View::create([
        'viewable_id' => $post->getKey(),
        'viewable_type' => $post->getMorphClass(),
    ]);

    expect(View::first()->viewable)->toBeInstanceOf(Post::class);
});

describe('viewer', function () use ($sqliteOnly): void {
    it('can belong to any viewer model', function (): void {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->create();

        View::factory()->for(Post::factory()->create(), 'viewable')->by($user)->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->by($apartment)->create();

        expect(View::byViewer($user)->sole()->viewer->is($user))->toBeTrue()
            ->and(View::byViewer($apartment)->sole()->viewer->is($apartment))->toBeTrue();
    });

    it('has no viewer for a guest view', function (): void {
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(View::sole()->viewer)->toBeNull();
    });

    it('scopes to the viewer type and key', function (): void {
        $user = User::factory()->create();

        $query = View::byViewer($user);

        expect($query->toSql())->toBe('select * from "views" where "views"."viewer_type" = ? and "views"."viewer_id" = ?')
            ->and($query->getBindings())->toBe([$user->getMorphClass(), $user->getKey()]);
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('refuses a viewer without a key rather than matching guest views', function (): void {
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(fn (): int => View::byViewer(new User)->count())
            ->toThrow(InvalidViewer::class, 'The key of the viewer ['.User::class.'] must be an integer or a string, null given.');
    });

    it('counts only the views of the viewer', function (): void {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        View::factory()->for($post, 'viewable')->by($user)->count(2)->create();
        View::factory()->for($post, 'viewable')->by(User::factory()->create())->create();
        View::factory()->for($post, 'viewable')->create();

        expect(View::byViewer($user)->count())->toBe(2);
    });

    it('scopes to the visitor', function (): void {
        $query = View::byVisitor('visitor_one');

        expect($query->toSql())->toBe('select * from "views" where "views"."visitor" = ?')
            ->and($query->getBindings())->toBe(['visitor_one']);
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('counts only the views of the visitor', function (): void {
        $post = Post::factory()->create();

        View::factory()->for($post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
        View::factory()->for($post, 'viewable')->fromVisitor('visitor_two')->create();

        expect(View::byVisitor('visitor_one')->count())->toBe(2);
    });

    it('casts the context to an array', function (): void {
        $view = View::factory()->for(Post::factory()->create(), 'viewable')->withContext(['source' => 'newsletter'])->create()->fresh();

        expect($view->context)->toBe(['source' => 'newsletter'])
            ->and(View::factory()->for(Post::factory()->create(), 'viewable')->create()->fresh()->context)->toBeNull();
    });

    it('sets the values of dimensions kept in a column', function (): void {
        $view = View::factory()->for(Post::factory()->create(), 'viewable')->withDimensions(['source' => 'Google', 'device' => 'mobile'])->create()->fresh();

        expect($view->getAttribute('source'))->toBe('Google')
            ->and($view->getAttribute('device'))->toBe('mobile');
    });
});

describe('within period', function () use ($sqliteOnly): void {
    it('scopes with only a start date time', function (): void {
        expect(View::withinPeriod(Period::since('2019-06-12'))->toSql())
            ->toBe('select * from "views" where "viewed_at" >= ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('scopes with only an end date time', function (): void {
        expect(View::withinPeriod(Period::upto('2019-03-23'))->toSql())
            ->toBe('select * from "views" where "viewed_at" < ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('scopes with both a start and an end date time', function (): void {
        expect(View::withinPeriod(Period::create('2019-02-15', '2019-06-12'))->toSql())
            ->toBe('select * from "views" where "viewed_at" >= ? and "viewed_at" < ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('includes the start and excludes the end', function (): void {
        $post = Post::factory()->create();

        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2019-01-01 00:00:00'))->create();
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2019-01-15 12:00:00'))->create();
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2019-01-31 00:00:00'))->create();

        expect(View::withinPeriod(Period::create('2019-01-01', '2019-01-31'))->count())->toBe(2)
            ->and(View::withinPeriod(Period::upto('2019-01-31'))->count())->toBe(2)
            ->and(View::withinPeriod(Period::since('2019-01-31'))->count())->toBe(1);
    });
});

describe('collection', function () use ($sqliteOnly): void {
    it('scopes to a null collection', function (): void {
        expect(View::collection(null)->toSql())
            ->toBe('select * from "views" where "collection" is null');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('scopes to a custom collection', function (): void {
        expect(View::collection('custom')->toSql())
            ->toBe('select * from "views" where "collection" = ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');
});

describe('for viewable', function () use ($sqliteOnly): void {
    it('scopes to the viewable type and key', function (): void {
        $post = Post::factory()->create();

        $query = View::forViewable($post);

        expect($query->toSql())->toBe('select * from "views" where "viewable_type" = ? and "viewable_id" = ?')
            ->and($query->getBindings())->toBe([$post->getMorphClass(), $post->getKey()]);
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('scopes to the viewable type alone when the viewable has no key', function (): void {
        $query = View::forViewable(new Post);

        expect($query->toSql())->toBe('select * from "views" where "viewable_type" = ?')
            ->and($query->getBindings())->toBe([(new Post)->getMorphClass()]);
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('counts only the views of the viewable', function (): void {
        $postOne = Post::factory()->create();
        $postTwo = Post::factory()->create();

        View::factory()->for($postOne, 'viewable')->create();
        View::factory()->for($postTwo, 'viewable')->count(2)->create();

        expect(View::forViewable($postOne)->count())->toBe(1)
            ->and(View::forViewable($postTwo)->count())->toBe(2)
            ->and(View::forViewable(new Post)->count())->toBe(3);
    });
});

describe('matching', function () use ($sqliteOnly): void {
    it('applies nothing for an empty views query', function (): void {
        expect(View::matching(new ViewsQuery)->toSql())->toBe('select * from "views"');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('applies the period', function (): void {
        expect(View::matching(new ViewsQuery(period: Period::since('2019-06-12')))->toSql())
            ->toBe('select * from "views" where "viewed_at" >= ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('applies the collection', function (): void {
        expect(View::matching(new ViewsQuery(collection: 'custom'))->toSql())
            ->toBe('select * from "views" where "collection" = ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('applies the period and the collection together', function (): void {
        expect(View::matching(new ViewsQuery(Period::since('2019-06-12'), 'custom'))->toSql())
            ->toBe('select * from "views" where "viewed_at" >= ? and "collection" = ?');
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('applies the viewer', function (): void {
        $user = User::factory()->create();

        $query = View::matching(new ViewsQuery(viewer: $user));

        expect($query->toSql())->toBe('select * from "views" where "views"."viewer_type" = ? and "views"."viewer_id" = ?')
            ->and($query->getBindings())->toBe([$user->getMorphClass(), $user->getKey()]);
    })->skip($sqliteOnly, 'SQL string assertions are written for the SQLite grammar');

    it('ignores the unique flag, which is not a filter', function (): void {
        expect(View::matching(new ViewsQuery(unique: true))->toSql())
            ->toBe(View::matching(new ViewsQuery)->toSql());
    });
});

describe('new query for', function (): void {
    it('produces the same query as the chained scopes', function (): void {
        $post = Post::factory()->create();
        $viewsQuery = new ViewsQuery(Period::create('2019-01-01', '2019-02-01'), 'custom');

        $direct = (new View)->newQueryFor($post, $viewsQuery);
        $chained = View::forViewable($post)->matching($viewsQuery);

        expect($direct->toSql())->toBe($chained->toSql())
            ->and($direct->getBindings())->toEqual($chained->getBindings());
    });

    it('covers every viewable of the type when the viewable has no key', function (): void {
        $post = Post::factory()->create();

        View::factory()->for($post, 'viewable')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect((new View)->newQueryFor(new Post, new ViewsQuery)->count())->toBe(2)
            ->and((new View)->newQueryFor($post, new ViewsQuery)->count())->toBe(1);
    });
});

describe('factory', function (): void {
    it('creates a view of a viewable', function (): void {
        $post = Post::factory()->create();

        $view = View::factory()->for($post, 'viewable')->create();

        expect($view->viewable)->toBeInstanceOf(Post::class)
            ->and($view->visitor)->toBeString()
            ->and($view->collection)->toBeNull()
            ->and($view->viewed_at)->not->toBeNull();
    });

    it('has states for the visitor, the collection and the time of the view', function (): void {
        $view = View::factory()
            ->for(Post::factory()->create(), 'viewable')
            ->fromVisitor('visitor_one')
            ->inCollection('sidebar')
            ->viewedAt(Carbon::parse('2026-09-01 08:00:00'))
            ->create()
            ->fresh();

        expect($view->visitor)->toBe('visitor_one')
            ->and($view->collection)->toBe('sidebar')
            ->and(Carbon::parse($view->viewed_at)->toDateTimeString())->toBe('2026-09-01 08:00:00');
    });

    it('builds instances of a model that extends the view', function (): void {
        $views = SoftDeletableView::factory()->for(Post::factory()->create(), 'viewable')->count(2)->create();

        expect($views)->toHaveCount(2)
            ->each->toBeInstanceOf(SoftDeletableView::class)
            ->and(View::count())->toBe(2);
    });
});
