@php
    $isFinance = ($context ?? 'user') === 'finance';
    $isAdmin = ($context ?? 'user') === 'admin';
    $routePrefix = $isFinance ? 'finance-manager.purchase-orders' : ($isAdmin ? 'admin.purchase-orders' : 'purchase-orders');
    $primaryItem = $order->items->first();
    $requestType = \App\Models\PurchaseOrder::requestTypes()[$order->request_type] ?? 'Need Purchase';
    $defaultPaymentCompanyId = old('company_id', $order->company_id ?: (($companies ?? collect())->first()?->id));
@endphp

<div class="po-card">
    <div class="po-head">
        <div>
            <h2 class="po-title">{{ $primaryItem?->item_name ?: $order->vendor_name }}</h2>
            <p class="po-sub">
                {{ $order->request_number ?: 'Draft request' }}
                @if($order->po_number) | {{ $order->po_number }} @endif
                | {{ $order->creator?->name ?: 'N/A' }}
            </p>
        </div>
        <div class="po-actions">
            @include('purchase-orders.partials.status', ['status' => $order->status])
            @if($order->attachment_path)
                <a class="po-btn po-btn-soft" href="{{ route($routePrefix . '.attachment.download', $order) }}">Attachment</a>
            @endif
            @if($order->po_number)
                <a class="po-btn po-btn-soft" href="{{ route($routePrefix . '.pdf.download', $order) }}">Download PO PDF</a>
            @endif
        </div>
    </div>

    <div class="po-grid">
        <div class="po-stat"><span>PO type</span><strong>{{ $requestType }}</strong></div>
        <div class="po-stat"><span>Company</span><strong>{{ $order->company?->name ?: 'N/A' }}</strong></div>
        <div class="po-stat"><span>Item categories</span><strong>{{ $order->items->pluck('expenseCategory.name')->filter()->unique()->take(2)->join(', ') ?: ($order->category?->name ?: 'N/A') }}{{ $order->items->pluck('expenseCategory.name')->filter()->unique()->count() > 2 ? ' +' . ($order->items->pluck('expenseCategory.name')->filter()->unique()->count() - 2) : '' }}</strong></div>
        <div class="po-stat"><span>Items</span><strong>{{ $order->items->count() }}</strong></div>
        <div class="po-stat"><span>Total amount</span><strong>Rs {{ number_format((float) $order->total_amount, 2) }}</strong></div>
        <div class="po-stat"><span>Needed by</span><strong>{{ optional($order->required_by_date)->format('d M Y') ?: 'N/A' }}</strong></div>
        <div class="po-stat"><span>Vendor / payee</span><strong>{{ $order->vendor_name ?: 'N/A' }}</strong></div>
        @if($order->expense_entry_id)
            <div class="po-stat"><span>Expense entry</span><strong>#{{ $order->expense_entry_id }}</strong></div>
        @endif
        <div class="po-stat"><span>Admin remark</span><strong>{{ $order->admin_remark ?: 'N/A' }}</strong></div>
        @if($order->delete_request_reason)
            <div class="po-stat"><span>Delete requested by</span><strong>{{ $order->deleteRequester?->name ?: 'N/A' }}</strong></div>
            <div class="po-stat"><span>Delete requested at</span><strong>{{ optional($order->delete_requested_at)->format('d M Y, h:i A') ?: 'N/A' }}</strong></div>
            <div class="po-field po-full">
                <label>Delete request reason</label>
                <div class="po-stat"><strong>{{ $order->delete_request_reason }}</strong></div>
            </div>
        @endif
        @if($order->delete_reject_reason)
            <div class="po-field po-full">
                <label>Delete reject reason</label>
                <div class="po-stat"><strong>{{ $order->delete_reject_reason }}</strong></div>
            </div>
        @endif
        <div class="po-field po-full">
            <label>Purpose / reason</label>
            <div class="po-stat"><strong>{{ $order->purpose }}</strong></div>
        </div>
    </div>
</div>

<div class="po-card">
    <h3 style="margin-top:0;">Items</h3>
    <table class="po-table">
        <thead>
            <tr>
                <th>Item / service</th>
                <th>Category</th>
                <th>Sub category</th>
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
                    <td><strong>Rs {{ number_format((float) $item->line_total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if(!$order->expense_category_id && ($order->delivery_location || $order->expected_delivery_date || $order->service_period || $order->renewal_date || $order->license_note))
<div class="po-card">
    <h3 style="margin-top:0;">Purchase Details</h3>
    <div class="po-grid">
        @if($order->purchase_type === \App\Models\PurchaseOrder::TYPE_PHYSICAL)
            <div class="po-stat"><span>Delivery location</span><strong>{{ $order->delivery_location ?: 'N/A' }}</strong></div>
            <div class="po-stat"><span>Expected delivery</span><strong>{{ optional($order->expected_delivery_date)->format('d M Y') ?: 'N/A' }}</strong></div>
            <div class="po-stat"><span>Receiver name</span><strong>{{ $order->receiver_name ?: 'N/A' }}</strong></div>
            <div class="po-stat"><span>Received by</span><strong>{{ $order->receiver?->name ?: 'N/A' }}</strong></div>
        @else
            <div class="po-stat"><span>Service period</span><strong>{{ $order->service_period ?: 'N/A' }}</strong></div>
            <div class="po-stat"><span>Renewal date</span><strong>{{ optional($order->renewal_date)->format('d M Y') ?: 'N/A' }}</strong></div>
            <div class="po-stat po-full"><span>License / service note</span><strong>{{ $order->license_note ?: 'N/A' }}</strong></div>
        @endif
    </div>
</div>
@endif

@if($isAdmin && $order->status === \App\Models\PurchaseOrder::STATUS_SUBMITTED)
    <div class="po-card">
        <h3 style="margin-top:0;">Admin Action</h3>
        <div class="po-actions">
            <form method="POST" action="{{ route('admin.purchase-orders.approve', $order) }}">
                @csrf
                <button class="po-btn po-btn-primary">Approve</button>
            </form>
            <form method="POST" action="{{ route('admin.purchase-orders.reject', $order) }}" style="display:grid; gap:10px; flex:1;">
                @csrf
                <textarea name="remark" required placeholder="Reject remark" style="width:100%; min-height:80px; border:1px solid #d7ddda; border-radius:12px; padding:12px;"></textarea>
                <button class="po-btn po-btn-danger">Reject</button>
            </form>
        </div>
    </div>
@endif

@if($isAdmin && $order->status === \App\Models\PurchaseOrder::STATUS_DELETE_REQUESTED)
    <div class="po-card">
        <h3 style="margin-top:0;">Admin Delete Approval</h3>
        <p class="po-sub">Finance Manager requested this PO to be deleted/voided. Approval will hide it from normal PO lists but keep audit records.</p>
        <div class="po-actions">
            <form method="POST" action="{{ route('admin.purchase-orders.approve-delete', $order) }}" onsubmit="return confirm('Approve delete request for this PO?')">
                @csrf
                <button class="po-btn po-btn-danger">Approve Delete</button>
            </form>
            <form method="POST" action="{{ route('admin.purchase-orders.reject-delete', $order) }}" style="display:grid; gap:10px; flex:1;">
                @csrf
                <textarea name="delete_reject_reason" required placeholder="Reject delete reason" style="width:100%; min-height:80px; border:1px solid #d7ddda; border-radius:12px; padding:12px;"></textarea>
                <button class="po-btn po-btn-soft">Reject Delete</button>
            </form>
        </div>
    </div>
@endif

@if($isFinance && $order->canDeleteDirectlyByFinanceManager())
    <div class="po-card">
        <h3 style="margin-top:0;">Delete PO</h3>
        <p class="po-sub">Finance Manager direct PO delete/void kar sakta hai. Record hard delete nahi hoga; audit ke liye timeline rahegi.</p>
        <form method="POST" action="{{ route('finance-manager.purchase-orders.destroy', $order) }}" onsubmit="return confirm('Delete this PO? It will be hidden from normal PO lists but audit history will remain.')" style="display:grid; gap:10px;">
            @csrf
            <textarea name="delete_reason" required placeholder="Delete reason" style="width:100%; min-height:90px; border:1px solid #d7ddda; border-radius:12px; padding:12px;">{{ old('delete_reason') }}</textarea>
            <div><button class="po-btn po-btn-danger">Delete PO</button></div>
        </form>
    </div>
@endif

@if($isFinance && $order->status === \App\Models\PurchaseOrder::STATUS_PAYMENT_PENDING)
    <div class="po-card">
        <h3 style="margin-top:0;">Finance Payment</h3>
        <form method="POST" action="{{ route('finance-manager.purchase-orders.mark-paid', $order) }}" enctype="multipart/form-data" class="po-grid">
            @csrf
            <div class="po-field"><label>Amount</label><input type="number" step="0.01" name="amount" value="{{ $order->total_amount }}" required></div>
            <div class="po-field">
                <label>Company</label>
                <select name="company_id" required>
                    <option value="">Select company</option>
                    @foreach(($companies ?? collect()) as $company)
                        <option value="{{ $company->id }}" @selected((string) $defaultPaymentCompanyId === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <small>Finance can correct company before payment.</small>
            </div>
            <div class="po-field">
                <label>Payment mode</label>
                <select name="payment_mode" id="poPaymentMode" onchange="togglePoPaymentMethod()" required>
                    @foreach($paymentModes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="po-field" id="poPaymentMethodWrap">
                <label>Payment source</label>
                <select name="expense_payment_method_id" id="poPaymentMethod">
                    <option value="">Select source</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" data-type="{{ $method->type }}">{{ $method->name }}{{ $method->details ? ' - ' . $method->details : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="po-field"><label>Reference no</label><input name="reference_no"></div>
            <div class="po-field"><label>Payment date</label><input type="date" name="paid_at" value="{{ now()->toDateString() }}" required></div>
            <div class="po-field"><label>Payment proof</label><input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <div class="po-field po-full"><label>Remark</label><textarea name="remark"></textarea></div>
            <div class="po-full"><button class="po-btn po-btn-primary">Mark Paid</button></div>
        </form>
    </div>
    @push('scripts')
    <script>
        function togglePoPaymentMethod() {
            const mode = document.getElementById('poPaymentMode').value;
            const wrap = document.getElementById('poPaymentMethodWrap');
            const select = document.getElementById('poPaymentMethod');
            const required = ['bank', 'upi', 'credit_card'].includes(mode);
            wrap.style.display = required ? '' : 'none';
            select.required = required;
            [...select.options].forEach((option) => {
                option.hidden = option.value && option.dataset.type !== mode;
            });
            if (select.selectedOptions[0] && select.selectedOptions[0].hidden) select.value = '';
        }
        document.addEventListener('DOMContentLoaded', togglePoPaymentMethod);
    </script>
    @endpush
@endif

@if($isFinance && !$order->expense_category_id && $order->purchase_type === \App\Models\PurchaseOrder::TYPE_PHYSICAL && $order->status === \App\Models\PurchaseOrder::STATUS_PAID)
    <div class="po-card">
        <h3 style="margin-top:0;">Receiving</h3>
        <form method="POST" action="{{ route('finance-manager.purchase-orders.receive', $order) }}" enctype="multipart/form-data" class="po-grid">
            @csrf
            <div class="po-field"><label>Received date</label><input type="date" name="received_date" value="{{ now()->toDateString() }}" required></div>
            <div class="po-field"><label>Receiving attachment</label><input type="file" name="received_attachment" accept=".jpg,.jpeg,.png,.pdf,.webp"></div>
            <div class="po-field po-full"><label>Receiving note</label><textarea name="receiving_note"></textarea></div>
            <div class="po-full"><button class="po-btn po-btn-primary">Mark Received</button></div>
        </form>
    </div>
@endif

@if($order->payments->isNotEmpty())
    <div class="po-card">
        <h3 style="margin-top:0;">Payment History</h3>
        <table class="po-table">
            <thead><tr><th>Amount</th><th>Mode</th><th>Reference</th><th>Paid by</th><th>Date</th></tr></thead>
            <tbody>
                @foreach($order->payments as $payment)
                    <tr>
                        <td>Rs {{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ $payment->paymentMethod?->name ?: ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}</td>
                        <td>{{ $payment->reference_no ?: 'N/A' }}</td>
                        <td>{{ $payment->payer?->name ?: 'N/A' }}</td>
                        <td>{{ optional($payment->paid_at)->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="po-card">
    <h3 style="margin-top:0;">Timeline</h3>
    <div class="po-timeline">
        @forelse($order->logs as $log)
            <div class="po-log">
                <strong>{{ ucfirst($log->action) }}</strong>
                <div>{{ $log->remark ?: 'No remark' }}</div>
                <small>{{ $log->actor?->name ?: 'System' }} | {{ optional($log->action_at)->format('d M Y, h:i A') }}</small>
            </div>
        @empty
            <p class="po-sub">No timeline yet.</p>
        @endforelse
    </div>
</div>
