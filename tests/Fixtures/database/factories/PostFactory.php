<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Fixtures\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->words(5, true),
            'body' => $this->faker->text(),
        ];
    }
}
