@php
    $selectedCompanyId = old('company_id', $order->company_id);
    $selectedRequestType = old('request_type', $order->request_type ?: \App\Models\PurchaseOrder::REQUEST_TYPE_NEED_PURCHASE);
    $canSubmitRequest = !$order->exists || in_array($order->status, [
        \App\Models\PurchaseOrder::STATUS_DRAFT,
        \App\Models\PurchaseOrder::STATUS_REJECTED,
    ], true);
    $categoryPayload = $categories->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'subcategories' => $category->subcategories->map(fn ($subcategory) => [
            'id' => $subcategory->id,
            'name' => $subcategory->name,
        ])->values(),
    ])->values();
    $oldItems = old('items');
    $itemRows = collect($oldItems ?: ($order->items?->isNotEmpty() ? $order->items->map(fn ($item) => [
        'item_name' => $item->item_name,
        'expense_category_id' => $item->expense_category_id ?: $order->expense_category_id,
        'expense_subcategory_id' => $item->expense_subcategory_id ?: $order->expense_subcategory_id,
        'quantity' => $item->quantity ?: 1,
        'rate' => $item->rate ?: $item->line_total,
        'tax_amount' => $item->tax_amount ?: 0,
    ])->all() : [[
        'item_name' => '',
        'expense_category_id' => '',
        'expense_subcategory_id' => '',
        'quantity' => 1,
        'rate' => '',
        'tax_amount' => 0,
    ]]));
@endphp

@csrf
@if($method !== 'POST')
    @method($method)
@endif

<div class="po-grid">
    <div class="po-field">
        <label>PO type</label>
        <select name="request_type" id="poRequestType" required>
            @foreach($requestTypes as $key => $label)
                <option value="{{ $key }}" @selected($selectedRequestType === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <small id="poTypeHelp" hidden></small>
    </div>

    <div class="po-field">
        <label>PO No.</label>
        <input value="{{ $order->po_number ?: 'Auto generated after admin approval' }}" disabled>
    </div>

    <div class="po-field">
        <label>Raised by</label>
        <input value="{{ $order->creator?->name ?: auth()->user()->name }}" disabled>
    </div>

    <div class="po-field">
        <label>Company</label>
        <select name="company_id" required>
            <option value="">Select company</option>
            @foreach(($companies ?? collect()) as $company)
                <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="po-field">
        <label>Needed by date</label>
        <input type="date" name="required_by_date" value="{{ old('required_by_date', optional($order->required_by_date)->format('Y-m-d')) }}">
    </div>

    <div class="po-field">
        <label>Vendor / payee name</label>
        <input name="vendor_name" value="{{ old('vendor_name', $order->vendor_name === 'Not specified' ? '' : $order->vendor_name) }}" placeholder="Optional">
    </div>

    <div class="po-field po-full">
        <label>Products / services</label>
        <div class="po-items-wrap">
            <div class="po-items-head">
                <div>
                    <strong>Item-wise accounting</strong>
                    <small>Category and sub category are selected for every product/service.</small>
                </div>
                <button type="button" class="po-btn po-btn-soft" id="poAddItemBtn">+ Add Product</button>
            </div>

            <div class="po-items-table-wrap">
                <table class="po-table po-items-table">
                    <thead>
                        <tr>
                            <th>Item / service</th>
                            <th>Category</th>
                            <th>Sub category</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Tax/GST</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody">
                        @foreach($itemRows as $index => $item)
                            <tr class="po-item-row" data-index="{{ $index }}">
                                <td data-label="Item / service">
                                    <input name="items[{{ $index }}][item_name]" value="{{ $item['item_name'] ?? '' }}" placeholder="Example: Charger, repair, bill" required>
                                </td>
                                <td data-label="Category">
                                    <select name="items[{{ $index }}][expense_category_id]" class="po-item-category" required>
                                        <option value="">Select category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($item['expense_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-label="Sub category">
                                    <select name="items[{{ $index }}][expense_subcategory_id]" class="po-item-subcategory" data-selected="{{ $item['expense_subcategory_id'] ?? '' }}" required>
                                        <option value="">Select sub category</option>
                                    </select>
                                </td>
                                <td data-label="Qty"><input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" class="po-item-quantity" value="{{ $item['quantity'] ?? 1 }}" required></td>
                                <td data-label="Rate"><input type="number" step="0.01" min="0.01" name="items[{{ $index }}][rate]" class="po-item-rate" value="{{ $item['rate'] ?? '' }}" placeholder="0.00" required></td>
                                <td data-label="Tax/GST"><input type="number" step="0.01" min="0" name="items[{{ $index }}][tax_amount]" class="po-item-tax" value="{{ $item['tax_amount'] ?? 0 }}" placeholder="0.00"></td>
                                <td data-label="Total"><strong class="po-line-total">Rs 0.00</strong></td>
                                <td data-label="Action"><button type="button" class="po-item-remove" title="Remove item">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="po-total-row">
                <span>Grand Total</span>
                <strong id="poGrandTotal">Rs 0.00</strong>
            </div>
        </div>
    </div>

    <div class="po-field po-full">
        <label>Purpose / reason</label>
        <textarea name="purpose" placeholder="Why is this purchase or reimbursement needed?" required>{{ old('purpose', $order->purpose) }}</textarea>
    </div>

    <div class="po-field po-full">
        <label>Attachment</label>
        <input type="file" name="attachment" id="poAttachment" accept=".jpg,.jpeg,.png,.pdf,.webp">
        <small id="poAttachmentHelp" hidden></small>
        @if($order->attachment_path)
            <input type="hidden" name="existing_attachment_path" value="{{ $order->attachment_path }}">
        @endif
    </div>
</div>

<div class="po-actions" style="margin-top:20px;">
    <button class="po-btn po-btn-soft" type="submit" name="intent" value="draft">{{ $order->exists ? 'Save Changes' : 'Save Draft' }}</button>
    @if($canSubmitRequest)
        <button class="po-btn po-btn-primary" type="submit" name="intent" value="submit">Submit to Admin</button>
    @endif
</div>

@push('scripts')
<script>
    const poCategoryPayload = @json($categoryPayload);

    function poMoney(amount) {
        return 'Rs ' + Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updatePoSubcategoriesForRow(row) {
        const categorySelect = row.querySelector('.po-item-category');
        const subcategorySelect = row.querySelector('.po-item-subcategory');
        if (!categorySelect || !subcategorySelect) return;

        const selectedSubcategory = subcategorySelect.dataset.selected || subcategorySelect.value;
        const category = poCategoryPayload.find((item) => String(item.id) === String(categorySelect.value));
        const subcategories = category ? category.subcategories : [];

        subcategorySelect.innerHTML = '<option value="">Select sub category</option>';
        subcategories.forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            option.selected = String(selectedSubcategory) === String(subcategory.id);
            subcategorySelect.appendChild(option);
        });
    }

    function updatePoTotals() {
        let grandTotal = 0;
        document.querySelectorAll('.po-item-row').forEach((row) => {
            const quantity = parseFloat(row.querySelector('.po-item-quantity')?.value || 0);
            const rate = parseFloat(row.querySelector('.po-item-rate')?.value || 0);
            const tax = parseFloat(row.querySelector('.po-item-tax')?.value || 0);
            const total = Math.max(0, (quantity * rate) + tax);
            grandTotal += total;
            const totalEl = row.querySelector('.po-line-total');
            if (totalEl) totalEl.textContent = poMoney(total);
        });

        const grandTotalEl = document.getElementById('poGrandTotal');
        if (grandTotalEl) grandTotalEl.textContent = poMoney(grandTotal);
    }

    function reindexPoItems() {
        document.querySelectorAll('.po-item-row').forEach((row, index) => {
            row.dataset.index = index;
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
    }

    function addPoItemRow() {
        const body = document.getElementById('poItemsBody');
        const template = body?.querySelector('.po-item-row');
        if (!body || !template) return;

        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach((input) => {
            if (input.classList.contains('po-item-quantity')) input.value = '1';
            else if (input.classList.contains('po-item-tax')) input.value = '0';
            else input.value = '';
        });
        row.querySelectorAll('select').forEach((select) => {
            select.value = '';
            select.dataset.selected = '';
            if (select.classList.contains('po-item-subcategory')) {
                select.innerHTML = '<option value="">Select sub category</option>';
            }
        });
        body.appendChild(row);
        reindexPoItems();
        updatePoTotals();
    }

    function updatePoTypeHelp() {
        const type = document.getElementById('poRequestType')?.value;
        const help = document.getElementById('poTypeHelp');
        const attachmentHelp = document.getElementById('poAttachmentHelp');
        const attachment = document.getElementById('poAttachment');
        if (!help || !attachmentHelp) return;

        if (type === '{{ \App\Models\PurchaseOrder::REQUEST_TYPE_ALREADY_PURCHASED }}') {
            help.textContent = 'Use this when the item/service is already purchased and reimbursement/payment is needed.';
            attachmentHelp.textContent = 'Bill or receipt attachment is optional for already purchased reimbursement.';
            if (attachment) attachment.required = false;
        } else {
            help.textContent = 'Use this when purchase/payment is needed after approval.';
            attachmentHelp.textContent = 'Quotation or supporting attachment is optional for need purchase requests.';
            if (attachment) attachment.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.po-item-row').forEach(updatePoSubcategoriesForRow);
        updatePoTotals();
        updatePoTypeHelp();

        document.getElementById('poAddItemBtn')?.addEventListener('click', addPoItemRow);
        document.getElementById('poItemsBody')?.addEventListener('input', (event) => {
            if (event.target.matches('.po-item-quantity, .po-item-rate, .po-item-tax')) updatePoTotals();
        });
        document.getElementById('poItemsBody')?.addEventListener('change', (event) => {
            if (event.target.matches('.po-item-category')) {
                const row = event.target.closest('.po-item-row');
                const subcategorySelect = row?.querySelector('.po-item-subcategory');
                if (subcategorySelect) subcategorySelect.dataset.selected = '';
                if (row) updatePoSubcategoriesForRow(row);
            }
        });
        document.getElementById('poItemsBody')?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.po-item-remove');
            if (!removeButton) return;
            const rows = document.querySelectorAll('.po-item-row');
            if (rows.length <= 1) return;
            removeButton.closest('.po-item-row')?.remove();
            reindexPoItems();
            updatePoTotals();
        });
        document.getElementById('poRequestType')?.addEventListener('change', updatePoTypeHelp);
    });
</script>
@endpush
