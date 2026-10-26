<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price_cents' => fake()->numberBetween(500, 9000),
        ];
    }
}
