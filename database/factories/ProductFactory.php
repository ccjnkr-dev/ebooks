<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'type' => 'book', 'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'creator' => fake()->name(), 'description' => fake()->paragraph(), 'price' => 34900, 'stock' => 1, 'status' => 'draft', 'accent' => 'violet'];
    }
}
