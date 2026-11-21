<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'original_path' => 'originals/books/'.fake()->uuid().'.pdf', 'original_name' => 'book.pdf', 'status' => 'pending'];
    }
}
