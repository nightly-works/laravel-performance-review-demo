<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'A'.fake()->unique()->numberBetween(100000, 999999),
            'customer_id' => Customer::factory(),
            'status' => 'paid',
        ];
    }

    /** Gives the order some lines, each with its own product. */
    public function withItems(int $lines = 2): static
    {
        return $this->has(OrderItem::factory()->count($lines), 'items');
    }
}
