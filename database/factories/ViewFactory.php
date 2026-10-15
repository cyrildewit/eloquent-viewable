<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Database\Factories;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds views for seeders and tests. Reach it through `View::factory()`;
 * a model that extends `View` gets instances of its own class back.
 *
 * @extends Factory<View>
 */
class ViewFactory extends Factory
{
    /** @var class-string<View> */
    #[\Override]
    protected $model = View::class;

    /** @return array<model-property<View>, mixed> */
    public function definition(): array
    {
        return [
            'visitor' => $this->faker->sha1(),
            'collection' => null,
            'viewed_at' => Carbon::now(),
        ];
    }

    public function fromVisitor(string $visitor): static
    {
        return $this->state(['visitor' => $visitor]);
    }

    public function inCollection(?string $collection): static
    {
        return $this->state(['collection' => $collection]);
    }

    public function by(Model $viewer): static
    {
        return $this->for($viewer, 'viewer');
    }

    /** @param  array<string, mixed>|null  $context */
    public function withContext(?array $context): static
    {
        return $this->state(['context' => $context]);
    }

    /**
     * The values of dimensions kept in a column, by name, such as
     * `['source' => 'Google']`. A dimension kept in `context` is set through
     * `withContext()`.
     *
     * @param  array<string, ?string>  $dimensions
     */
    public function withDimensions(array $dimensions): static
    {
        return $this->state($dimensions);
    }

    public function viewedAt(DateTimeInterface $viewedAt): static
    {
        return $this->state(['viewed_at' => $viewedAt]);
    }

    /**
     * Build instances of the given model instead of the base `View`.
     *
     * @param  class-string<View>  $model
     */
    public function forModel(string $model): static
    {
        $factory = $this->newInstance();
        $factory->model = $model;

        return $factory;
    }

    /**
     * Carry the model class into the next instance. The parent only passes
     * its constructor arguments on, so a model set by `forModel()` would be
     * lost after the first `state()` or `count()` call without this.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    #[\Override]
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->model = $this->model;

        return $factory;
    }
}
