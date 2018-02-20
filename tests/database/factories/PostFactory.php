<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Faker\Generator as Faker;
use CyrildeWit\EloquentVisitable\Tests\Stubs\Models\Post;

/**
 * This is the Post factory.
 *
 * @var \Illuminate\Database\Eloquent\Factory  $factory
 */
$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->title,
        'body' => $faker->paragraph,
    ];
});
