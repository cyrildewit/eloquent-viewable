<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

/**
 * Resolves every dimension for a view while the request is still there. A
 * dimension that throws is reported and leaves its value null, so one broken
 * dimension never stops a view from being recorded.
 */
final readonly class DimensionResolver
{
    public function __construct(
        private DimensionRegistry $registry,
        private Config $config,
        private Request $request,
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * Without an arrival, the referrer and the landing page are read from the
     * current request.
     *
     * @param  ?array<string, mixed>  $context
     */
    public function resolve(Visitor $visitor, Viewable $viewable, ?string $collection = null, ?array $context = null, ?Arrival $arrival = null): ResolvedDimensions
    {
        if ($this->registry->isEmpty()) {
            return new ResolvedDimensions;
        }

        $input = $this->input($visitor, $viewable, $collection, $context, $arrival ?? Arrival::fromRequest($this->request));
        $values = [];

        foreach ($this->registry->all() as $name => $definition) {
            $values[$name] = [$definition, $this->resolveOne($definition, $input)];
        }

        return new ResolvedDimensions($values);
    }

    /** @param  ?array<string, mixed>  $context */
    private function input(Visitor $visitor, Viewable $viewable, ?string $collection, ?array $context, Arrival $arrival): DimensionInput
    {
        $headers = [];

        foreach ($this->request->headers->all() as $name => $values) {
            $value = $values[0] ?? null;

            if (is_string($value)) {
                $headers[strtolower($name)] = $value;
            }
        }

        return new DimensionInput(
            visitor: $visitor,
            viewable: $viewable,
            collection: $collection,
            context: $context,
            referrer: $arrival->referrer,
            landing: $arrival->landing,
            appHosts: $this->appHosts(),
            headers: $headers,
        );
    }

    /**
     * The host of `app.url`, the host the request was made to, and the
     * internal hosts in config.
     *
     * @return list<string>
     */
    private function appHosts(): array
    {
        $hosts = [];

        foreach ([Arrival::hostOf($this->config->applicationUrl()), $this->request->getHost(), ...$this->config->internalHosts()] as $host) {
            if ($host === null) {
                continue;
            }

            if ($host === '') {
                continue;
            }

            $hosts[] = strtolower($host);
        }

        return array_values(array_unique($hosts));
    }

    private function resolveOne(DimensionDefinition $definition, DimensionInput $input): ?string
    {
        try {
            return $definition->resolve($input);
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);

            return null;
        }
    }
}
