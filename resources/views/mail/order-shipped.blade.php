<p>Hello {{ $order->customer->name }},</p>
<p>Your order {{ $order->number }} left the workshop today.</p>
<ul>
@foreach ($order->items as $item)
    <li>{{ $item->quantity }} × {{ $item->product->name }}</li>
@endforeach
</ul>
<p>Thank you for your order.</p>
