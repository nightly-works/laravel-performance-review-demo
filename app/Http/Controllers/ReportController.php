<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /** Revenue per month over the last twelve months, cancelled orders excluded, for the dashboard chart. */
    public function revenue(): JsonResponse
    {
        $orders = Order::with('items')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->get();

        $months = $orders
            ->groupBy(fn (Order $order) => $order->created_at->format('Y-m'))
            ->map(fn ($group, $month) => [
                'month' => $month,
                'revenue_cents' => $group->sum(fn (Order $order) => $order->totalCents()),
                'orders' => $group->count(),
            ])
            ->sortKeys()
            ->values();

        return response()->json($months);
    }
}
