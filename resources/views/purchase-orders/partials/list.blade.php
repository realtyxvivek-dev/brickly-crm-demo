<div class="po-card">
    <table class="po-table">
        <thead>
            <tr>
                <th>Request</th>
                <th>Item / type</th>
                <th>Category</th>
                <th>Status</th>
                <th>Total</th>
                <th>Created by</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    @php
                        $primaryItem = $order->items->first();
                        $extraItems = max(0, $order->items->count() - 1);
                        $categoryNames = $order->items->pluck('expenseCategory.name')->filter()->unique();
                        $subcategoryNames = $order->items->pluck('expenseSubcategory.name')->filter()->unique();
                        $categorySummary = $categoryNames->take(2)->join(', ') ?: ($order->category?->name ?: 'N/A');
                        $subcategorySummary = $subcategoryNames->take(2)->join(', ') ?: ($order->subcategory?->name ?: 'N/A');
                    @endphp
                    <td data-label="Request">
                        <strong>{{ $order->request_number ?: 'Draft' }}</strong><br>
                        <small>{{ $order->po_number ?: 'PO pending' }}</small>
                    </td>
                    <td data-label="Item / Type">
                        {{ $primaryItem?->item_name ?: $order->vendor_name }}
                        @if($extraItems > 0)
                            <span class="po-badge">+{{ $extraItems }} items</span>
                        @endif
                        <br><small>{{ \App\Models\PurchaseOrder::requestTypes()[$order->request_type] ?? 'Need Purchase' }}</small>
                    </td>
                    <td data-label="Category">
                        {{ $categorySummary }}{{ $categoryNames->count() > 2 ? ' +' . ($categoryNames->count() - 2) : '' }}
                        <br><small>{{ $subcategorySummary }}{{ $subcategoryNames->count() > 2 ? ' +' . ($subcategoryNames->count() - 2) : '' }}</small>
                    </td>
                    <td data-label="Status">@include('purchase-orders.partials.status', ['status' => $order->status])</td>
                    <td data-label="Total">Rs {{ number_format((float) $order->total_amount, 2) }}</td>
                    <td data-label="Created by">{{ $order->creator?->name ?: 'N/A' }}</td>
                    <td data-label="Action">
                        <div class="po-actions">
                            <a href="{{ route($routePrefix . '.show', $order) }}" class="po-btn po-btn-primary">Open</a>
                            @if($routePrefix === 'finance-manager.purchase-orders' && $order->isEditableByFinanceManager())
                                <a href="{{ route($routePrefix . '.edit', $order) }}" class="po-btn po-btn-soft">Edit</a>
                                @if($order->canDeleteDirectlyByFinanceManager())
                                    <form method="POST" action="{{ route($routePrefix . '.destroy', $order) }}" onsubmit="return confirm('Delete this PO? It will be hidden from normal PO lists but audit history will remain.')" style="display:inline-flex;">
                                        @csrf
                                        <input type="hidden" name="delete_reason" value="Deleted from Finance Manager PO list.">
                                        <button type="submit" class="po-btn po-btn-danger">Delete</button>
                                    </form>
                                @endif
                            @elseif($routePrefix === 'purchase-orders' && $order->isEditableByCreator())
                                <a href="{{ route($routePrefix . '.edit', $order) }}" class="po-btn po-btn-soft">Edit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; padding:28px;">No purchase orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:14px;">{{ $orders->links() }}</div>
</div>
