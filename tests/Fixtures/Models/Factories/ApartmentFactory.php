<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Factories;

use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Apartment> */
class ApartmentFactory extends Factory
{
    #[\Override]
    protected $model = Apartment::class;

    /** @return array<model-property<Apartment>, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->title,
            'description' => $this->faker->paragraph,
        ];
    }
}
