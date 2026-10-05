<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
});

it('registers {0}', function (string $command): void {
    expect(Artisan::all())->toHaveKey($command);
})->with(['views:forget-viewer', 'views:forget-visitor']);

it('forgets a viewer named by its class', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->count(2)->create();
    View::factory()->for($this->post, 'viewable')->create();

    $this->artisan('views:forget-viewer', ['type' => User::class, 'id' => (string) $this->user->getKey()])
        ->expectsOutputToContain('Deleted 2 views of')
        ->assertSuccessful();

    expect(View::query()->count())->toBe(1);
});

it('forgets a viewer named by its morph alias', function (): void {
    Relation::morphMap(['user' => User::class]);

    try {
        View::factory()->for($this->post, 'viewable')->by($this->user)->create();

        expect($this->user->getMorphClass())->toBe('user');

        $this->artisan('views:forget-viewer', ['type' => User::class, 'id' => (string) $this->user->getKey()])->assertSuccessful();
        expect(View::query()->count())->toBe(0);

        View::factory()->for($this->post, 'viewable')->by($this->user)->create();

        $this->artisan('views:forget-viewer', ['type' => 'user', 'id' => (string) $this->user->getKey()])->assertSuccessful();
        expect(View::query()->count())->toBe(0);
    } finally {
        Relation::morphMap([], false);
    }
});

it('forgets the guest views of a viewer with --with-visitors', function (): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->create();

    $this->artisan('views:forget-viewer', ['type' => User::class, 'id' => (string) $this->user->getKey(), '--with-visitors' => true])
        ->expectsOutputToContain('Deleted 2 views of')
        ->assertSuccessful();
});

it('forgets a visitor', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-1')->count(3)->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-2')->create();

    $this->artisan('views:forget-visitor', ['visitor' => 'cookie-1', '--chunk' => '2'])
        ->expectsOutputToContain('Deleted 3 views of the visitor.')
        ->assertSuccessful();

    expect(View::query()->sole()->visitor)->toBe('cookie-2');
});

it('refuses a chunk that is not a positive integer', function (string $command, array $arguments, string $chunk): void {
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->create();

    $this->artisan($command, [...$arguments, '--chunk' => $chunk])
        ->expectsOutputToContain('The --chunk option must be a positive integer.')
        ->assertFailed();

    expect(View::query()->count())->toBe(1);
})->with([
    'viewer, zero' => fn (): array => ['views:forget-viewer', ['type' => User::class, 'id' => (string) $this->user->getKey()], '0'],
    'visitor, text' => ['views:forget-visitor', ['visitor' => 'cookie-1'], 'many'],
]);

it('asks before deleting in production', function (string $command, array $arguments): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->create();

    $this->artisan($command, $arguments)
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect(View::query()->count())->toBe(1);

    $this->artisan($command, [...$arguments, '--force' => true])->assertSuccessful();

    expect(View::query()->count())->toBe(0);
})->with([
    'viewer' => fn (): array => ['views:forget-viewer', ['type' => User::class, 'id' => (string) $this->user->getKey()]],
    'visitor' => ['views:forget-visitor', ['visitor' => 'cookie-1']],
]);
