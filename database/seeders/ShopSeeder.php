<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shop with about two years of history: 2,000 customers, 40 products and SHOP_ORDERS orders (20,000 by
 * default) of one to four lines each. Rows are inserted in bulk so a large shop seeds in seconds.
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        $orders = (int) env('SHOP_ORDERS', 20000);
        $now = Carbon::parse('2026-10-26 12:00:00');

        $customers = [];
        for ($i = 1; $i <= 2000; $i++) {
            $customers[] = ['name' => "Customer $i", 'email' => "customer$i@example.test", 'city' => ['Lyon', 'Nantes', 'Lille', 'Brest'][$i % 4], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($customers, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        $prices = [];
        $products = [];
        for ($i = 1; $i <= 40; $i++) {
            $prices[$i] = mt_rand(500, 9000);
            $products[] = ['name' => "Product $i", 'price_cents' => $prices[$i], 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('products')->insert($products);

        $statuses = ['paid', 'shipped', 'shipped', 'shipped', 'shipped', 'shipped', 'pending', 'cancelled'];
        $orderRows = [];
        $itemRows = [];
        for ($id = 1; $id <= $orders; $id++) {
            // Spread over 730 days, oldest first, so ids and dates grow together as in a real shop.
            $at = $now->copy()->subMinutes((int) (($orders - $id) * 730 * 1440 / $orders))->toDateTimeString();
            $orderRows[] = ['id' => $id, 'number' => sprintf('A%06d', $id), 'customer_id' => mt_rand(1, 2000), 'status' => $statuses[mt_rand(0, 7)], 'created_at' => $at, 'updated_at' => $at];
            for ($l = mt_rand(1, 4); $l > 0; $l--) {
                $p = mt_rand(1, 40);
                $itemRows[] = ['order_id' => $id, 'product_id' => $p, 'quantity' => mt_rand(1, 3), 'unit_price_cents' => $prices[$p], 'created_at' => $at, 'updated_at' => $at];
            }
            if (count($orderRows) === 500) {
                DB::table('orders')->insert($orderRows);
                foreach (array_chunk($itemRows, 500) as $chunk) {
                    DB::table('order_items')->insert($chunk);
                }
                $orderRows = $itemRows = [];
            }
        }
        if ($orderRows) {
            DB::table('orders')->insert($orderRows);
            foreach (array_chunk($itemRows, 500) as $chunk) {
                DB::table('order_items')->insert($chunk);
            }
        }
    }
}
