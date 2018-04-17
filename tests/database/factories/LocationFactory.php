<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Faker\Generator as Faker;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Location;

/*
 * This is the Location factory.
 *
 * @var \Illuminate\Database\Eloquent\Factory  $factory
 */
$factory->define(Location::class, function (Faker $faker) {
    return [
        'name' => $faker->title,
        'description' => $faker->paragraph,
    ];
});
