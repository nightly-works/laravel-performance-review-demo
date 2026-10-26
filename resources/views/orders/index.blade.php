<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Orders — {{ $status }}</title>
</head>
<body>
<h1>Orders: {{ $status }}</h1>
@if (session('status'))
    <p>{{ session('status') }}</p>
@endif
<nav>
    @foreach (['pending', 'paid', 'shipped', 'cancelled'] as $s)
        <a href="{{ route('orders.index', ['status' => $s]) }}">{{ $s }}</a>
    @endforeach
    <a href="{{ route('orders.export') }}">Export CSV</a>
</nav>
<table>
    <thead>
    <tr><th>Order</th><th>Date</th><th>Customer</th><th>City</th><th>Items</th><th>Total</th><th></th></tr>
    </thead>
    <tbody>
    @foreach ($orders as $order)
        <tr>
            <td>{{ $order->number }}</td>
            <td>{{ $order->created_at->toDateString() }}</td>
            <td>{{ $order->customer->name }}</td>
            <td>{{ $order->customer->city }}</td>
            <td>{{ $order->items->sum('quantity') }}</td>
            <td>{{ number_format($order->totalCents() / 100, 2) }} €</td>
            <td>
                @if ($order->status === 'paid')
                    <form method="post" action="{{ route('orders.ship', $order) }}">
                        @csrf
                        <button>Mark shipped</button>
                    </form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $orders->links() }}
</body>
</html>
