<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Revenue per month over the last twelve months, cancelled orders excluded, for the dashboard chart. */
    public function revenue(): JsonResponse
    {
        $month = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', orders.created_at)",
            'pgsql' => "to_char(orders.created_at, 'YYYY-MM')",
            default => "date_format(orders.created_at, '%Y-%m')",
        };

        // One aggregate query: the database adds the lines up, and PHP receives one row per month.
        $months = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->subMonthsNoOverflow(11)->startOfMonth())
            ->groupByRaw($month)
            ->orderByRaw($month)
            ->selectRaw("$month as month, sum(order_items.quantity * order_items.unit_price_cents) as revenue_cents, count(distinct orders.id) as orders")
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'revenue_cents' => (int) $row->revenue_cents,
                'orders' => (int) $row->orders,
            ]);

        return response()->json($months);
    }
}
