<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Middleware;

use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class RecordViews
{
    public const string ALIAS = 'views';

    public function __construct(
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * @param  string|list<string>  $models  route parameter names or model classes
     */
    public static function using(
        string|array $models = [],
        ?string $collection = null,
        ?int $cooldown = null,
        ?bool $queue = null,
    ): string {
        $arguments = (array) $models;

        if ($collection !== null) {
            $arguments[] = 'collection='.$collection;
        }

        if ($cooldown !== null) {
            $arguments[] = 'cooldown='.$cooldown;
        }

        if ($queue !== null) {
            $arguments[] = 'queue='.($queue ? 'true' : 'false');
        }

        return $arguments === [] ? self::ALIAS : self::ALIAS.':'.implode(',', $arguments);
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

        // The bindings are resolved by now, whichever order the middleware ran in.
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        $route = $request->route();

        if (! $route instanceof Route) {
            return $response;
        }

        [$selectors, $options] = $this->parse(array_values($arguments));

        foreach ($this->viewables($route, $selectors) as $viewable) {
            $this->record($viewable, $options);
        }

        return $response;
    }

    /**
     * @param  array{collection: ?string, cooldown: ?int, queue: ?bool}  $options
     */
    private function record(Viewable $viewable, array $options): void
    {
        $views = views($viewable)->collection($options['collection'])->cooldown($options['cooldown']);

        if ($options['queue'] !== null) {
            $views->queue($options['queue']);
        }

        try {
            $views->record();
        } catch (RecordingFailed $exception) {
            $this->exceptions->report($exception);
        }
    }

    /**
     * @param  list<string>  $selectors
     * @return list<Viewable>
     *
     * @throws InvalidViewable
     */
    private function viewables(Route $route, array $selectors): array
    {
        $parameters = $route->parameters();

        if ($selectors === []) {
            $viewables = array_values(array_filter($parameters, fn (mixed $value): bool => $value instanceof Viewable));

            return $viewables === [] ? throw InvalidViewable::noneInRoute($route->uri()) : [array_last($viewables)];
        }

        $viewables = [];

        foreach ($selectors as $selector) {
            array_push($viewables, ...$this->select($route, $parameters, $selector));
        }

        return $viewables;
    }

    /**
     * A selector with a backslash names a model class, the way Laravel's `can`
     * middleware tells a class from a route parameter.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return list<Viewable>
     *
     * @throws InvalidViewable
     */
    private function select(Route $route, array $parameters, string $selector): array
    {
        if (str_contains($selector, '\\')) {
            $viewables = array_values(array_filter(
                $parameters,
                fn (mixed $value): bool => $value instanceof $selector && $value instanceof Viewable,
            ));

            return $viewables === [] ? throw InvalidViewable::notInRoute($selector, $route->uri()) : $viewables;
        }

        $value = $parameters[$selector] ?? throw InvalidViewable::notInRoute($selector, $route->uri());

        return $value instanceof Viewable ? [$value] : throw InvalidViewable::routeParameterNotViewable($selector, $route->uri());
    }

    /**
     * @param  list<string>  $arguments
     * @return array{list<string>, array{collection: ?string, cooldown: ?int, queue: ?bool}}
     *
     * @throws InvalidConfiguration
     */
    private function parse(array $arguments): array
    {
        $selectors = [];
        $options = ['collection' => null, 'cooldown' => null, 'queue' => null];

        foreach ($arguments as $argument) {
            if (! str_contains($argument, '=')) {
                $selectors[] = $argument;

                continue;
            }

            $value = Str::after($argument, '=');

            $options = match (Str::before($argument, '=')) {
                'collection' => [...$options, 'collection' => $value],
                'cooldown' => [...$options, 'cooldown' => $this->minutes($value, $argument)],
                'queue' => [...$options, 'queue' => $this->boolean($value, $argument)],
                default => throw InvalidConfiguration::invalidMiddlewareOption($argument),
            };
        }

        return [$selectors, $options];
    }

    /** @throws InvalidConfiguration */
    private function minutes(string $value, string $argument): int
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($minutes) ? $minutes : throw InvalidConfiguration::invalidMiddlewareOption($argument);
    }

    /** @throws InvalidConfiguration */
    private function boolean(string $value, string $argument): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw InvalidConfiguration::invalidMiddlewareOption($argument);
    }
}
