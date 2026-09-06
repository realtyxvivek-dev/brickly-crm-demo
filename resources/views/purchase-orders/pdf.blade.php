<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $order->po_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #10241d; }
        h1 { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #cfc8b8; padding: 9px; text-align: left; }
        th { background: #f2efe6; }
        .meta { margin: 3px 0; color: #526058; }
    </style>
</head>
<body>
    <h1>Purchase Order</h1>
    <p class="meta">PO No: {{ $order->po_number }}</p>
    <p class="meta">Request No: {{ $order->request_number }}</p>
    <p class="meta">PO Type: {{ \App\Models\PurchaseOrder::requestTypes()[$order->request_type] ?? 'Need Purchase' }}</p>
    <p class="meta">Company: {{ $order->company?->name ?: 'N/A' }}</p>
    <p class="meta">Vendor / Payee: {{ $order->vendor_name }}</p>
    <p class="meta">Item categories: {{ $order->items->pluck('expenseCategory.name')->filter()->unique()->join(', ') ?: ($order->category?->name ?: 'N/A') }}</p>
    <p class="meta">Created by: {{ $order->creator?->name ?: 'N/A' }}</p>
    <p><strong>Purpose:</strong> {{ $order->purpose }}</p>
    <table>
        <thead>
            <tr>
                <th>Item / Service</th>
                <th>Category</th>
                <th>Sub Category</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Tax/GST</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td>{{ $item->expenseCategory?->name ?: ($order->category?->name ?: (\App\Models\PurchaseOrder::itemCategories()[$item->category] ?? $item->category)) }}</td>
                    <td>{{ $item->expenseSubcategory?->name ?: ($order->subcategory?->name ?: 'N/A') }}</td>
                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>Rs {{ number_format((float) $item->rate, 2) }}</td>
                    <td>Rs {{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>Rs {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h3 style="text-align:right;">Grand Total: Rs {{ number_format((float) $order->total_amount, 2) }}</h3>
</body>
</html>
