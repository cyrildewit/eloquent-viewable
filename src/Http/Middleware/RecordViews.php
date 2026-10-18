<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Middleware;

use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Http\RecordingOptions;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

final readonly class RecordViews
{
    public const string Alias = 'views';

    public function __construct(
        private Container $container,
        private ExceptionHandler $exceptions,
    ) {}

    /** @param  string|list<string>  $models  route parameter names or model classes */
    public static function using(
        string|array $models = [],
        ?string $collection = null,
        ?int $cooldown = null,
        ?bool $queue = null,
    ): string {
        $arguments = [...(array) $models, ...new RecordingOptions($collection, $cooldown, $queue)->toMiddlewareArguments()];

        if ($arguments === []) {
            return self::Alias;
        }

        $alias = self::Alias;

        $options = implode(',', $arguments);

        return "{$alias}:{$options}";
    }

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewable
     */
    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $response = $next($request);
        $route = $request->route();

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if (! $response->isSuccessful()) {
            return $response;
        }

        if (! $route instanceof Route) {
            return $response;
        }

        [$options, $selectors] = RecordingOptions::fromMiddlewareArguments($arguments);
        $views = $options->applyTo($this->container->make(Views::class));

        foreach ($this->viewables($route, ...$selectors) as $viewable) {
            try {
                $views->forViewable($viewable)->record();
            } catch (RecordingFailed $exception) {
                $this->exceptions->report($exception);
            }
        }

        return $response;
    }

    /** @return list<Viewable> */
    private function viewables(Route $route, string ...$selectors): array
    {
        $viewables = array_filter($route->parameters(), fn (mixed $value): bool => $value instanceof Viewable);

        if ($selectors === []) {
            if ($viewables === []) {
                throw InvalidViewable::noneInRoute($route->uri());
            }

            return [array_last($viewables)];
        }

        $selected = [];

        foreach ($selectors as $selector) {
            $namesModelClass = str_contains($selector, '\\');

            $matches = $namesModelClass
                ? array_filter($viewables, fn (Viewable $viewable): bool => $viewable instanceof $selector)
                : array_intersect_key($viewables, [$selector => true]);

            if ($matches === []) {
                throw $route->hasParameter($selector)
                    ? InvalidViewable::routeParameterNotViewable($selector, $route->uri())
                    : InvalidViewable::notInRoute($selector, $route->uri());
            }

            array_push($selected, ...array_values($matches));
        }

        return $selected;
    }
}
