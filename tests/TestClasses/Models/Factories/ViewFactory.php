<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories;

use CyrildeWit\EloquentViewable\View;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<View>
 */
class ViewFactory extends Factory
{
    #[\Override]
    protected $model = View::class;

    public function definition(): array
    {
        return [
            'visitor' => fake()->sha1(),
            'collection' => null,
            'viewed_at' => now(),
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

    public function viewedAt(DateTimeInterface $viewedAt): static
    {
        return $this->state(['viewed_at' => $viewedAt]);
    }
}
