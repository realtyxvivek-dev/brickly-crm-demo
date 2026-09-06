@extends('layouts.app')

@section('title', $pageMode === 'edit' ? 'Edit Expense' : 'Add Expense')
@section('page-title', $pageMode === 'edit' ? 'Edit Expense' : 'Add Expense')

@section('content')
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">{{ $pageMode === 'edit' ? 'Update Expense' : 'Create Expense' }}</h2>
                <p class="text-sm text-[#6B7280]">Company, category, subcategory aur vendor/person wise entry save karo.</p>
            </div>
            <a href="{{ route('admin.expenses.entries.index') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Back to list</a>
        </div>

        @if($isRejectedResubmission)
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <div class="font-semibold">Rejected for correction</div>
                <div class="mt-1">{{ $entry->reject_reason }}</div>
                <div class="mt-1 text-red-700">Rejected by {{ $entry->rejector?->name ?: 'Approver' }} on {{ optional($entry->rejected_at)->format('d M Y, h:i A') ?: 'N/A' }}. Updating this rejected expense will send it back to the approval queue.</div>
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            @if($formMethod === 'PUT')
                @method('PUT')
            @endif
            <input type="hidden" name="existing_attachment_path" value="{{ old('existing_attachment_path', $entry->attachment_path) }}">

            <div>
                <label class="block text-sm text-brand-primary mb-2">Company</label>
                <select name="company_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    <option value="">Select company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $entry->company_id) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Category</label>
                <select name="expense_category_id" id="expense_category_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('expense_category_id', $entry->expense_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Subcategory</label>
                <select name="expense_subcategory_id" id="expense_subcategory_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    <option value="">Select subcategory</option>
                    @foreach($categories as $category)
                        @foreach($category->subcategories as $subcategory)
                            <option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" @selected(old('expense_subcategory_id', $entry->expense_subcategory_id) == $subcategory->id)>{{ $subcategory->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Expense Date</label>
                <input type="date" name="expense_date" value="{{ old('expense_date', optional($entry->expense_date)->format('Y-m-d') ?: $entry->expense_date) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Amount</label>
                <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $entry->amount) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Payment Mode</label>
                <select name="payment_mode" id="payment_mode" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    @foreach($paymentModes as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_mode', $entry->payment_mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="payment_method_wrap">
                <label id="payment_method_label" class="block text-sm text-brand-primary mb-2">Payment Source</label>
                <select name="expense_payment_method_id" id="expense_payment_method_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">Select payment source</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" data-type="{{ $method->type }}" @selected((string) old('expense_payment_method_id', $entry->expense_payment_method_id) === (string) $method->id)>
                            {{ $method->name }}{{ $method->details ? ' - ' . $method->details : '' }}{{ !$method->is_active ? ' (Inactive)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div id="payment_method_help" class="mt-2 text-xs text-[#6B7280]">Bank, UPI, ya Credit Card ke liye source select karna required hai.</div>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Send For Approval To</label>
                <select name="approval_assigned_to" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    <option value="">Select approver</option>
                    @foreach($approvers as $approver)
                        <option value="{{ $approver->id }}" @selected((string) old('approval_assigned_to', $entry->approval_assigned_to) === (string) $approver->id)>{{ $approver->name }}{{ !$approver->is_active ? ' (Inactive)' : '' }}</option>
                    @endforeach
                </select>
                <div class="mt-2 text-xs text-[#6B7280]">Selected admin primary approval owner rahega. Queue sab admins ko visible rahegi.</div>
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Paid To</label>
                <input type="text" name="paid_to" value="{{ old('paid_to', $entry->paid_to) }}" placeholder="UPPCL / Rahul / Meta" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Reference Number</label>
                <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $entry->reference_no) }}" placeholder="Invoice / UPI / Cheque / Txn ID" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-brand-primary mb-2">Attachment</label>
                <input type="file" name="attachment" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg bg-white" accept=".jpg,.jpeg,.png,.pdf,.webp">
                @if($entry->attachment_path)
                    <div class="mt-2 text-sm"><a href="{{ route('admin.expenses.entries.attachment.download', $entry) }}" class="text-brand-primary font-semibold">Current attachment download</a></div>
                @endif
            </div>
            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ $isRejectedResubmission ? 'Updating this rejected expense will send it back to the approval queue as draft.' : ($pageMode === 'edit' ? 'Updating this expense will move it back to the approval queue as draft.' : 'Saving this expense will send it to the approval queue as draft.') }}
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm text-brand-primary mb-2">Remarks</label>
                <textarea name="remarks" rows="4" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" placeholder="Reason / context / notes">{{ old('remarks', $entry->remarks) }}</textarea>
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">{{ $isRejectedResubmission ? 'Update & Resubmit' : ($pageMode === 'edit' ? 'Update Expense' : 'Save Expense') }}</button>
                <a href="{{ route('admin.expenses.entries.index') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const categorySelect = document.getElementById('expense_category_id');
        const subcategorySelect = document.getElementById('expense_subcategory_id');
        if (!categorySelect || !subcategorySelect) {
            return;
        }

        const placeholder = subcategorySelect.querySelector('option[value=""]');

        function filterSubcategories() {
            const categoryId = categorySelect.value;
            const currentValue = subcategorySelect.value;
            let hasVisibleCurrent = false;

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
                    hasVisibleCurrent = true;
                }
            });

            if (!hasVisibleCurrent) {
                subcategorySelect.value = placeholder ? placeholder.value : '';
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
                referenceInput.placeholder = placeholders[mode] || 'Invoice / UPI / Cheque / Txn ID';
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
