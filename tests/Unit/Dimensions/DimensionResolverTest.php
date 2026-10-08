<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Arrival;
use CyrildeWit\EloquentViewable\Dimensions\Dimension;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Dimensions\DimensionResolver;
use CyrildeWit\EloquentViewable\Dimensions\FakeVisitor;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;

/**
 * A dimension that hands back the input it was given, so a test can read it.
 */
final class InputCapturingDimension extends Dimension
{
    public static ?DimensionInput $input = null;

    public function resolve(DimensionInput $input): string
    {
        self::$input = $input;

        return 'seen';
    }
}

function capturingResolver(Request $request, array $config = [], ?ExceptionHandler $exceptions = null, array $extra = []): DimensionResolver
{
    InputCapturingDimension::$input = null;

    return new DimensionResolver(
        new DimensionRegistry(['seen' => new DimensionDefinition('seen', new InputCapturingDimension), ...$extra]),
        new Config(new Repository($config)),
        $request,
        $exceptions ?? Mockery::mock(ExceptionHandler::class),
    );
}

it('resolves nothing without dimensions', function (): void {
    $resolver = new DimensionResolver(new DimensionRegistry, new Config(new Repository), Request::create('/'), Mockery::mock(ExceptionHandler::class));

    expect($resolver->resolve(new FakeVisitor, new Post)->all())->toBeEmpty();
});

it('builds the input from the attempt and the request', function (): void {
    $request = Request::create('https://shop.example/posts/1?utm_source=fb', server: [
        'HTTP_REFERER' => 'https://t.co/abc',
        'HTTP_CF_IPCOUNTRY' => 'NL',
    ]);
    $visitor = new FakeVisitor('Mozilla/5.0');
    $post = new Post(['id' => 7]);

    $resolved = capturingResolver($request, ['app' => ['url' => 'https://example.com'], 'eloquent-viewable' => ['dimensions' => ['internal_hosts' => ['Blog.Example.com', '']]]])
        ->resolve($visitor, $post, 'amp', ['ab' => 'b']);

    $input = InputCapturingDimension::$input;

    expect($resolved->all())->toBe(['seen' => 'seen'])
        ->and($input?->visitor)->toBe($visitor)
        ->and($input?->viewable)->toBe($post)
        ->and($input?->collection)->toBe('amp')
        ->and($input?->context)->toBe(['ab' => 'b'])
        ->and($input?->referrer)->toBe('t.co')
        ->and($input?->landing)->toBe(['utm_source' => 'fb'])
        ->and($input?->appHosts)->toBe(['example.com', 'shop.example', 'blog.example.com'])
        ->and($input?->header('CF-IPCountry'))->toBe('NL');
});

it('reads the arrival it is given over the request', function (): void {
    $request = Request::create('https://example.com/_ev/posts/1', server: ['HTTP_REFERER' => 'https://example.com/posts/1']);

    capturingResolver($request)->resolve(new FakeVisitor, new Post, arrival: new Arrival('news.ycombinator.com', ['ref' => 'hn']));

    expect(InputCapturingDimension::$input?->referrer)->toBe('news.ycombinator.com')
        ->and(InputCapturingDimension::$input?->landing)->toBe(['ref' => 'hn']);
});

it('reports a dimension that throws and leaves its value null', function (): void {
    $broken = new class extends Dimension
    {
        public function resolve(DimensionInput $input): ?string
        {
            throw new RuntimeException('down');
        }
    };

    $exceptions = Mockery::mock(ExceptionHandler::class);
    $exceptions->expects('report')->with(Mockery::on(fn (Throwable $exception): bool => $exception->getMessage() === 'down'));

    $resolved = capturingResolver(Request::create('/'), exceptions: $exceptions, extra: ['broken' => new DimensionDefinition('broken', $broken)])
        ->resolve(new FakeVisitor, new Post);

    expect($resolved->all())->toBe(['seen' => 'seen', 'broken' => null]);
});
