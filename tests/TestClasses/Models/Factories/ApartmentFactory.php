<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories;

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apartment>
 */
class ApartmentFactory extends Factory
{
    protected $model = Apartment::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->title,
            'description' => $this->faker->paragraph,
        ];
    }
}
