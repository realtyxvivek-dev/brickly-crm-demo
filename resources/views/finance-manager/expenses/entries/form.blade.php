@extends('finance-manager.layout')

@section('title', $pageMode === 'edit' ? 'Edit Expense' : 'Create Expense')
@section('page_title', $pageMode === 'edit' ? 'Edit Expense' : 'Create Expense')
@section('page_subtitle', 'Capture audit-ready expense data with company, category, vendor, reference, and status.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>{{ $pageMode === 'edit' ? 'Update Expense Entry' : 'New Expense Entry' }}</h2>
                <p>Company select karo, category choose karo, uske baad related subcategory ke saath audit-friendly entry save karo.</p>
            </div>
            <a href="{{ route('finance-manager.expenses.entries.index') }}" class="expense-btn soft"><i class="fas fa-arrow-left"></i> Back to Ledger</a>
        </div>

        @if($isRejectedResubmission)
            <div class="expense-rejection-note">
                <strong>Rejected for correction</strong>
                <div>{{ $entry->reject_reason }}</div>
                <div class="expense-rejection-meta">
                    Rejected by {{ $entry->rejector?->name ?: 'Approver' }} on {{ optional($entry->rejected_at)->format('d M Y, h:i A') ?: 'N/A' }}.
                    Updating this rejected expense will send it back to the approval queue.
                </div>
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="expense-form-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
            @csrf
            @if($formMethod === 'PUT')
                @method('PUT')
            @endif
            <input type="hidden" name="existing_attachment_path" value="{{ old('existing_attachment_path', $entry->attachment_path) }}">

            <div class="expense-field">
                <label>Company</label>
                <select name="company_id" class="expense-input" required>
                    <option value="">Select company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $entry->company_id) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Category</label>
                <select name="expense_category_id" id="expense_category_id" class="expense-input" required>
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('expense_category_id', $entry->expense_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Subcategory</label>
                <select name="expense_subcategory_id" id="expense_subcategory_id" class="expense-input" required>
                    <option value="">Select subcategory</option>
                    @foreach($categories as $category)
                        @foreach($category->subcategories as $subcategory)
                            <option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" @selected(old('expense_subcategory_id', $entry->expense_subcategory_id) == $subcategory->id)>{{ $subcategory->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="expense-field"><label>Expense Date</label><input class="expense-input" type="date" name="expense_date" value="{{ old('expense_date', optional($entry->expense_date)->format('Y-m-d') ?: $entry->expense_date) }}" required></div>
            <div class="expense-field"><label>Amount</label><input class="expense-input" type="number" min="0" step="0.01" name="amount" value="{{ old('amount', $entry->amount) }}" required></div>
            <div class="expense-field">
                <label>Payment Mode</label>
                <select name="payment_mode" id="payment_mode" class="expense-input" required>
                    @foreach($paymentModes as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_mode', $entry->payment_mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field" id="payment_method_wrap">
                <label id="payment_method_label">Payment Source</label>
                <select name="expense_payment_method_id" id="expense_payment_method_id" class="expense-input">
                    <option value="">Select payment source</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" data-type="{{ $method->type }}" @selected((string) old('expense_payment_method_id', $entry->expense_payment_method_id) === (string) $method->id)>
                            {{ $method->name }}{{ $method->details ? ' - ' . $method->details : '' }}{{ !$method->is_active ? ' (Inactive)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div id="payment_method_help" style="margin-top:8px;font-size:12px;color:#697771;">Bank, UPI, ya Credit Card ke liye source select karna required hai.</div>
            </div>
            <div class="expense-field">
                <label>Send For Approval To</label>
                <select name="approval_assigned_to" class="expense-input" required>
                    <option value="">Select approver</option>
                    @foreach($approvers as $approver)
                        <option value="{{ $approver->id }}" @selected((string) old('approval_assigned_to', $entry->approval_assigned_to) === (string) $approver->id)>{{ $approver->name }}{{ !$approver->is_active ? ' (Inactive)' : '' }}</option>
                    @endforeach
                </select>
                <div style="margin-top:8px;font-size:12px;color:#697771;">Selected admin ki queue me expense jayegi. Sab admins dekh payenge, but ye primary assignee hoga.</div>
            </div>
            <div class="expense-field"><label>Paid To</label><input class="expense-input" type="text" name="paid_to" value="{{ old('paid_to', $entry->paid_to) }}" placeholder="UPPCL / Rahul / Meta"></div>
            <div class="expense-field"><label>Reference No</label><input class="expense-input" id="reference_no" type="text" name="reference_no" value="{{ old('reference_no', $entry->reference_no) }}" placeholder="Invoice / UPI / Cheque / Txn"></div>
            <div class="expense-field">
                <label>Attachment</label>
                <input class="expense-input" style="background:#fff;" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.webp">
                @if($entry->attachment_path)
                    <div style="margin-top:8px;"><a href="{{ route('finance-manager.expenses.entries.attachment.download', $entry) }}" class="expense-btn soft" style="padding:10px 14px;">Current attachment</a></div>
                @endif
            </div>
            <div class="expense-field" style="grid-column:1 / -1;">
                <div style="border:1px solid #f3d08a;background:#fff6df;color:#6e4b00;border-radius:16px;padding:14px 16px;font-size:14px;font-weight:600;">
                    {{ $isRejectedResubmission ? 'Update ke baad rejected expense correction ke liye dubara approval queue me draft ban kar jayegi.' : ($pageMode === 'edit' ? 'Update ke baad entry dubara approval queue me draft ban kar jayegi.' : 'Save karte hi entry approval queue me draft status ke saath chali jayegi.') }}
                </div>
            </div>
            <div class="expense-field" style="grid-column:1 / -1;">
                <label>Remarks</label>
                <textarea name="remarks" rows="4" class="expense-input" placeholder="Reason / context / notes">{{ old('remarks', $entry->remarks) }}</textarea>
            </div>
            <div style="grid-column:1 / -1; display:flex; flex-wrap:wrap; gap:12px;">
                <button type="submit" class="expense-btn primary"><i class="fas fa-floppy-disk"></i> {{ $isRejectedResubmission ? 'Update & Resubmit' : ($pageMode === 'edit' ? 'Update Expense' : 'Save Expense') }}</button>
                <a href="{{ route('finance-manager.expenses.entries.index') }}" class="expense-btn soft">Cancel</a>
            </div>
        </form>
    </section>
</div>

<script>
    (function () {
        const categorySelect = document.getElementById('expense_category_id');
        const subcategorySelect = document.getElementById('expense_subcategory_id');
        if (!categorySelect || !subcategorySelect) {
            return;
        }

        function filterSubcategories() {
            const categoryId = categorySelect.value;
            const currentValue = subcategorySelect.value;
            let currentVisible = false;

            Array.from(subcategorySelect.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const matches = option.dataset.category === categoryId;
                option.hidden = !matches;
                if (!matches && option.selected) {
                    option.selected = false;
                }
                if (matches && option.value === currentValue) {
                    currentVisible = true;
                }
            });

            if (!currentVisible) {
                subcategorySelect.value = '';
            }
        }

        categorySelect.addEventListener('change', filterSubcategories);
        filterSubcategories();
    })();

    (function () {
        const modeSelect = document.getElementById('payment_mode');
        const methodWrap = document.getElementById('payment_method_wrap');
        const methodSelect = document.getElementById('expense_payment_method_id');
        const methodLabel = document.getElementById('payment_method_label');
        const methodHelp = document.getElementById('payment_method_help');
        const referenceInput = document.getElementById('reference_no');

        if (!modeSelect || !methodWrap || !methodSelect) {
            return;
        }

        const labels = {
            bank: 'Bank Account',
            upi: 'UPI Account',
            credit_card: 'Credit Card',
            cash: 'Payment Source',
        };

        const helps = {
            bank: 'Select karo kis bank account se payment hua.',
            upi: 'Select karo kis UPI account se payment hua.',
            credit_card: 'Select karo kis credit card se payment hua.',
            cash: 'Cash me source optional hai.',
        };

        const placeholders = {
            bank: 'Cheque / NEFT / IMPS / Txn No',
            upi: 'UPI Txn ID',
            credit_card: 'Card Txn / Statement Ref',
            cash: 'Voucher / Receipt No',
        };

        function syncPaymentMethod() {
            const mode = modeSelect.value;
            const requiresMethod = ['bank', 'upi', 'credit_card'].includes(mode);
            let selectedVisible = false;

            Array.from(methodSelect.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const matches = option.dataset.type === mode;
                option.hidden = !matches;
                if (matches && option.selected) {
                    selectedVisible = true;
                }
            });

            methodWrap.style.display = requiresMethod ? '' : 'none';
            methodSelect.required = requiresMethod;
            methodLabel.textContent = labels[mode] || 'Payment Source';
            methodHelp.textContent = helps[mode] || '';
            if (referenceInput) {
                referenceInput.placeholder = placeholders[mode] || 'Invoice / UPI / Cheque / Txn';
            }

            if (!selectedVisible) {
                methodSelect.value = '';
            }
        }

        modeSelect.addEventListener('change', syncPaymentMethod);
        syncPaymentMethod();
    })();
</script>
@endsection
