<?php

namespace App\Http\Controllers;

use App\Mail\OrderShipped;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** The staff's main screen: orders in one status, newest first. Paid orders are the ones to ship. */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'paid');

        $orders = Order::where('status', $status)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('orders.index', ['orders' => $orders, 'status' => $status]);
    }

    /** Every order as CSV, for the accountant. */
    public function export()
    {
        $orders = Order::with(['customer', 'items'])->orderBy('id')->get();

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['number', 'date', 'customer', 'email', 'status', 'items', 'total_eur']);
        foreach ($orders as $order) {
            fputcsv($out, [
                $order->number,
                $order->created_at->toDateString(),
                $order->customer->name,
                $order->customer->email,
                $order->status,
                $order->items->sum('quantity'),
                number_format($order->totalCents() / 100, 2, '.', ''),
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ]);
    }

    /** Marks a paid order as shipped and tells the customer. */
    public function ship(Order $order): RedirectResponse
    {
        abort_unless($order->status === 'paid', 422, 'Only paid orders can be shipped.');

        $order->forceFill(['status' => 'shipped', 'shipped_at' => now()])->save();

        Mail::to($order->customer->email)->send(new OrderShipped($order));

        return back()->with('status', "Order {$order->number} marked as shipped.");
    }
}
