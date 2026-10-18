<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * The options a page or a route passes along with the model to record: the
 * collection, the cooldown in minutes and whether to queue the write. One
 * that is not set is not applied, so the config still decides it.
 */
final readonly class RecordingOptions
{
    public function __construct(
        public ?string $collection = null,
        public ?int $cooldown = null,
        public ?bool $queue = null,
    ) {}

    /**
     * The options among the arguments of the `views` middleware, as
     * `name=value`, and the arguments left over, which select the models.
     *
     * @param  array<string>  $arguments
     * @return array{self, list<string>}
     *
     * @throws InvalidConfiguration
     */
    public static function fromMiddlewareArguments(array $arguments): array
    {
        $options = new self;
        $selectors = [];

        foreach ($arguments as $argument) {
            if (! str_contains($argument, '=')) {
                $selectors[] = $argument;

                continue;
            }

            $value = Str::after($argument, '=');

            $options = match (Str::before($argument, '=')) {
                'collection' => new self($value, $options->cooldown, $options->queue),
                'cooldown' => new self($options->collection, self::minutes($value, $argument), $options->queue),
                'queue' => new self($options->collection, $options->cooldown, filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? throw InvalidConfiguration::invalidMiddlewareOption($argument)),
                default => throw InvalidConfiguration::invalidMiddlewareOption($argument),
            };
        }

        return [$options, $selectors];
    }

    /**
     * The options a signed URL carries. An option the URL leaves out is not
     * set at all.
     *
     * @param  InputBag<string>  $query
     */
    public static function fromQuery(InputBag $query): self
    {
        return new self(
            $query->has('collection') ? $query->getString('collection') : null,
            $query->has('cooldown') ? $query->getInt('cooldown') : null,
            $query->has('queue') ? $query->getBoolean('queue') : null,
        );
    }

    /** @return list<string> */
    public function toMiddlewareArguments(): array
    {
        $arguments = [];

        if ($this->collection !== null) {
            $arguments[] = "collection={$this->collection}";
        }

        if ($this->cooldown !== null) {
            $arguments[] = "cooldown={$this->cooldown}";
        }

        if ($this->queue !== null) {
            $arguments[] = 'queue='.($this->queue ? 'true' : 'false');
        }

        return $arguments;
    }

    /** @return array<string, int|string> */
    public function toQuery(): array
    {
        $parameters = [];

        if ($this->collection !== null) {
            $parameters['collection'] = $this->collection;
        }

        if ($this->cooldown !== null) {
            $parameters['cooldown'] = $this->cooldown;
        }

        if ($this->queue !== null) {
            $parameters['queue'] = $this->queue ? 1 : 0;
        }

        return $parameters;
    }

    public function applyTo(Views $views): Views
    {
        if ($this->collection !== null) {
            $views->collection($this->collection);
        }

        if ($this->cooldown !== null) {
            $views->cooldown($this->cooldown);
        }

        if ($this->queue !== null) {
            $views->queue($this->queue);
        }

        return $views;
    }

    /** @throws InvalidConfiguration */
    private static function minutes(string $value, string $argument): int
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($minutes) ? $minutes : throw InvalidConfiguration::invalidMiddlewareOption($argument);
    }
}
