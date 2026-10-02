<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Factories;

use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    #[\Override]
    protected $model = Post::class;

    /** @return array<model-property<Post>, mixed> */
    public function definition(): array
    {
        return [
            'title' => $this->faker->title,
            'body' => $this->faker->paragraph,
        ];
    }
}
