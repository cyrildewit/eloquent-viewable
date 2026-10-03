<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Middleware;

use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
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
        $arguments = (array) $models;

        if ($collection !== null) {
            $arguments[] = "collection={$collection}";
        }

        if ($cooldown !== null) {
            $arguments[] = "cooldown={$cooldown}";
        }

        if ($queue !== null) {
            $value = $queue ? 'true' : 'false';

            $arguments[] = "queue={$value}";
        }

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

        $views = $this->views(...$arguments);
        $selectors = array_filter($arguments, fn (string $argument): bool => ! str_contains($argument, '='));

        foreach ($this->viewables($route, ...$selectors) as $viewable) {
            try {
                $views->forViewable($viewable)->record();
            } catch (RecordingFailed $exception) {
                $this->exceptions->report($exception);
            }
        }

        return $response;
    }

    private function views(string ...$arguments): Views
    {
        $views = $this->container->make(Views::class);

        foreach ($arguments as $argument) {
            if (! str_contains($argument, '=')) {
                continue;
            }

            $value = Str::after($argument, '=');

            match (Str::before($argument, '=')) {
                'collection' => $views->collection($value),
                'cooldown' => $views->cooldown($this->minutes($value, $argument)),
                'queue' => $views->queue(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? throw InvalidConfiguration::invalidMiddlewareOption($argument)),
                default => throw InvalidConfiguration::invalidMiddlewareOption($argument),
            };
        }

        return $views;
    }

    private function minutes(string $value, string $argument): int
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($minutes) ? $minutes : throw InvalidConfiguration::invalidMiddlewareOption($argument);
    }

    /** @return list<Viewable> */
    private function viewables(Route $route, string ...$selectors): array
    {
        $viewables = array_filter($route->parameters(), fn (mixed $value): bool => $value instanceof Viewable);

        if ($selectors === []) {
            return $viewables === [] ? throw InvalidViewable::noneInRoute($route->uri()) : [array_last($viewables)];
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
