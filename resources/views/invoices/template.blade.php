<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info {
            float: left;
            width: 50%;
        }
        .invoice-info {
            float: right;
            width: 50%;
            text-align: right;
        }
        .clear {
            clear: both;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }
        .invoice-number {
            font-size: 16px;
            margin: 5px 0;
        }
        .customer-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            color: #007bff;
        }
        .payment-info {
            margin-top: 30px;
            padding: 15px;
            background: #e7f3ff;
            border-left: 4px solid #007bff;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-info">
            <h1 class="invoice-title">INVOICE</h1>
            <p class="invoice-number">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
            <p><strong>Date:</strong> {{ $order->created_at->format('d/m/Y') }}</p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="customer-info">
        <h3>Bill To:</h3>
        <p><strong>{{ $order->name ?? 'Customer' }}</strong></p>
        <p>Order Date: {{ $order->created_at->format('d F Y, H:i') }}</p>
        <p>Payment Method: {{ ucfirst($order->metode_pembayaran ?? 'N/A') }}</p>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @if($order->items && count($order->items) > 0)
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item['name'] ?? 'Item' }}</td>
                    <td>{{ $item['quantity'] ?? 1 }}</td>
                    <td>IDR {{ number_format($item['price'] ?? 0, 0, ',', '.') }}</td>
                    <td>IDR {{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td>Order Items</td>
                    <td>{{ $order->items ?? '1 item(s)' }}</td>
                    <td>IDR {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</td>
                    <td>IDR {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="clear"></div>
    
    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>IDR {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</span>
        </div>
        <div class="total-row">
            <span>Tax (0%):</span>
            <span>IDR 0</span>
        </div>
        <div class="total-row final">
            <span>Total:</span>
            <span>IDR {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="clear"></div>

    <div class="payment-info">
        <h4>Payment Information</h4>
        <p><strong>Method:</strong> {{ ucfirst($order->metode_pembayaran ?? 'N/A') }}</p>
        <p><strong>Status:</strong> Paid</p>
        @if($order->metode_pembayaran === 'card')
            <p><em>Payment processed via credit card</em></p>
        @else
            <p><em>Payment received in cash</em></p>
        @endif
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is a computer-generated invoice and does not require a signature.</p>
    </div>
</body>
</html>