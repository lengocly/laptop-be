<h2>Chào {{ $order->full_name }},</h2>
<p>Cảm ơn bạn đã đặt hàng tại <strong>BetaTech Shop</strong>.</p>
<p>Mã đơn: <strong>{{ $order->order_code }}</strong></p>
<p>Ngày tạo: {{ $order->created_at->format('d/m/Y H:i') }}</p>

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
    <tr style="background:#16a34a;color:#fff;">
        <th>Sản phẩm</th><th>Giá</th><th>SL</th><th>Thành tiền</th>
    </tr>
    @foreach($order->items as $item)
    <tr>
        <td>{{ $item->product_name }}</td>
        <td>{{ number_format($item->price) }} ₫</td>
        <td>{{ $item->quantity }}</td>
        <td>{{ number_format($item->line_total) }} ₫</td>
    </tr>
    @endforeach
</table>

<p><strong>Tổng tiền:</strong> {{ number_format($order->subtotal) }} ₫</p>
<p>Phương thức: {{ $order->payment_method === 'stripe' ? 'Stripe' : 'COD' }}</p>