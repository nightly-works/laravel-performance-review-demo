<?php

namespace Tests\Feature;

use App\Mail\OrderShipped;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * The five findings of the performance review, each written as a budget the page must keep. On the app
 * as delivered (tag as-delivered) every numbered test fails; after the fixes they pass. The other tests
 * check that the pages still show the right orders and figures.
 */
class PerformanceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 12:00:00');
        $this->actingAs(User::factory()->create());
    }

    /** Creates paid orders, a minute apart, each with $lines lines. */
    private function paidOrders(int $count, int $lines = 2): void
    {
        $products = Product::factory()->count(5)->create();
        for ($i = 0; $i < $count; $i++) {
            $order = Order::factory()->create(['status' => 'paid', 'created_at' => now()->subMinutes($count - $i)]);
            foreach ($products->take($lines) as $product) {
                OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2, 'unit_price_cents' => 1000]);
            }
        }
    }

    /** @return list<QueryExecuted> the queries run while $request runs */
    private function queriesDuring(callable $request): array
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });
        $request();
        DB::getEventDispatcher()->forget(QueryExecuted::class);

        return $queries;
    }

    private function body(TestResponse $response): string
    {
        return $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : $response->getContent();
    }

    // Finding 1: the order list runs two queries per order shown (its customer, its lines).

    public function test_1_order_list_runs_the_same_few_queries_for_ten_or_fifty_orders(): void
    {
        $this->paidOrders(10);
        $withTen = count($this->queriesDuring(fn () => $this->get('/orders')->assertOk()));

        $this->paidOrders(40);
        $withFifty = count($this->queriesDuring(fn () => $this->get('/orders')->assertOk()));

        $this->assertSame($withTen, $withFifty, "10 orders: $withTen queries, 50 orders: $withFifty queries");
        $this->assertLessThanOrEqual(4, $withFifty);
    }

    // Finding 2: the order list's queries scan whole tables and sort in a temporary b-tree.

    public function test_2_every_query_behind_the_order_list_uses_an_index(): void
    {
        $this->paidOrders(3);
        $queries = $this->queriesDuring(fn () => $this->get('/orders?status=paid')->assertOk());

        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $plan = collect(DB::select('EXPLAIN QUERY PLAN '.$query->sql, $query->bindings))->pluck('detail')->implode(' | ');
            $this->assertDoesNotMatchRegularExpression('/SCAN (orders|order_items)\b(?! USING (COVERING )?INDEX)|TEMP B-TREE/', $plan, "$query->sql\n  plan: $plan");
        }
    }

    // Finding 3: the revenue report loads every order of the year and every line into PHP to add them up.

    public function test_3_revenue_report_is_added_up_by_the_database(): void
    {
        $product = Product::factory()->create();
        $make = function (string $date, string $status, array $lines) use ($product) {
            $order = Order::factory()->create(['status' => $status, 'created_at' => $date]);
            foreach ($lines as [$quantity, $cents]) {
                OrderItem::factory()->for($order)->for($product)->create(['quantity' => $quantity, 'unit_price_cents' => $cents]);
            }
        };
        $make('2025-10-31 23:00:00', 'shipped', [[1, 9999]]);   // before the twelve months: left out
        $make('2025-11-01 00:00:00', 'shipped', [[2, 1500]]);   // first day of the window
        $make('2026-03-15 10:00:00', 'paid', [[1, 1000], [3, 250]]);
        $make('2026-03-20 10:00:00', 'cancelled', [[5, 1000]]); // cancelled: left out
        $make('2026-03-28 10:00:00', 'pending', [[1, 499]]);
        $make('2026-10-26 09:00:00', 'shipped', [[1, 2000]]);

        $models = 0;
        Event::listen('eloquent.retrieved: *', function () use (&$models) {
            $models++;
        });
        $queries = $this->queriesDuring(fn () => $this->getJson('/reports/revenue')->assertOk()->assertExactJson([
            ['month' => '2025-11', 'revenue_cents' => 3000, 'orders' => 1],
            ['month' => '2026-03', 'revenue_cents' => 2249, 'orders' => 2],
            ['month' => '2026-10', 'revenue_cents' => 2000, 'orders' => 1],
        ]));

        $this->assertSame(0, $models, "the report loaded $models Eloquent models");
        $this->assertCount(1, $queries);
    }

    // Finding 4: the CSV export loads every order, customer and line at once, then builds the whole file in memory.

    public function test_4_csv_export_streams_orders_in_batches(): void
    {
        $customer = Customer::factory()->create(['name' => 'Durand, Léa']);
        $product = Product::factory()->create();
        $rows = [];
        for ($i = 1; $i <= 1200; $i++) {
            $rows[] = ['number' => sprintf('B%05d', $i), 'customer_id' => $customer->id, 'status' => 'shipped', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00'];
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            Order::insert($chunk);
        }
        OrderItem::insert(Order::pluck('id')->map(fn ($id) => ['order_id' => $id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price_cents' => 1250])->all());

        $response = null;
        $body = '';
        $queries = $this->queriesDuring(function () use (&$response, &$body) {
            $response = $this->get('/orders/export')->assertOk();
            $body = $this->body($response);
        });

        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse, 'the file is built in memory before it is sent');
        $orderSelects = array_filter($queries, fn ($q) => preg_match('/^select .* from "orders"/i', $q->sql));
        $this->assertGreaterThan(1, count($orderSelects), 'all orders are read in one query');
        foreach ($orderSelects as $q) {
            $this->assertMatchesRegularExpression('/limit (\d{1,3}|1000)$/i', $q->sql, "unbounded: $q->sql");
        }

        $lines = explode("\n", trim($body));
        $this->assertCount(1201, $lines);
        $this->assertSame('B00001,2026-10-01,"Durand, Léa",'.$customer->email.',shipped,2,25.00', $lines[1]);
        $this->assertStringStartsWith('B01200,', $lines[1200]);
    }

    // Finding 5: marking an order shipped sends the customer's email inside the request.

    public function test_5_shipping_an_order_queues_the_customer_email(): void
    {
        Mail::fake();
        $this->paidOrders(1);
        $order = Order::first();

        $this->post("/orders/{$order->id}/ship")->assertRedirect();

        Mail::assertNothingSent();
        Mail::assertQueued(OrderShipped::class, fn (OrderShipped $mail) => $mail->hasTo($order->customer->email));
        $this->assertSame('shipped', $order->fresh()->status);
    }

    // Normal use.

    public function test_order_list_shows_the_chosen_status_newest_first_with_totals(): void
    {
        $this->paidOrders(3);
        Order::factory()->create(['status' => 'pending', 'number' => 'P1']);
        [$older, , $newest] = Order::with('customer')->where('status', 'paid')->orderBy('created_at')->get()->all();

        $this->get('/orders')
            ->assertOk()
            ->assertSeeInOrder([$newest->number, $newest->customer->name, '4', '40.00 €', $older->number])
            ->assertDontSee('P1');
        $this->get('/orders?status=pending')->assertOk()->assertSee('P1')->assertDontSee($newest->number);
    }

    public function test_only_paid_orders_can_be_shipped(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->post("/orders/{$order->id}/ship")->assertStatus(422);
        $this->assertSame('pending', $order->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_shipping_email_lists_the_order_lines(): void
    {
        $this->paidOrders(1, lines: 3);
        $order = Order::first();

        $html = (new OrderShipped($order))->render();

        $this->assertStringContainsString($order->customer->name, $html);
        foreach ($order->items as $item) {
            $this->assertStringContainsString("{$item->quantity} × {$item->product->name}", $html);
        }
    }

    public function test_pages_need_a_signed_in_staff_member(): void
    {
        auth()->logout();

        foreach (['/orders', '/orders/export', '/reports/revenue'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }
}
