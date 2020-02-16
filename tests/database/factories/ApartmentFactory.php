<?php

declare(strict_types=1);

use Faker\Generator as Faker;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;

/*
 * This is the Apartment factory.
 *
 * @var \Illuminate\Database\Eloquent\Factory  $factory
 */
$factory->define(Apartment::class, function (Faker $faker) {
    return [
        'name' => $faker->title,
        'description' => $faker->paragraph,
    ];
});
