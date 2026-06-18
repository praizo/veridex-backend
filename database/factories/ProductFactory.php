<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'item_type' => 'goods',
            'name' => fake()->words(3, true),
            'hs_code' => '010121',
            'item_category' => 'STANDARD_VAT',
            'description' => fake()->sentence(),
            'quantity' => 1,
            'unit_price' => fake()->randomFloat(2, 1000, 50000),
            'unit_code' => 'EA',
            'tax_category' => 'STANDARD_VAT',
            'tax_rate' => 7.5,
        ];
    }
}
