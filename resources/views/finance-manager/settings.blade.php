@extends('finance-manager.layout')

@section('title', 'Finance Settings')
@section('page_title', 'Finance Settings')
@section('page_subtitle', 'Manage finance defaults. Payment methods yahan se add/edit/disable honge.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Payment Methods</h2>
                <p>Bank, UPI, Credit Card aur Cash sources maintain karo. Expense entry me selected mode ke hisaab se ye options show honge.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('finance-manager.settings.payment-methods.store') }}" class="expense-form-grid" style="grid-template-columns:1fr 1.5fr 1.5fr .8fr .8fr;">
            @csrf
            <div class="expense-field">
                <label>Type</label>
                <select name="type" class="expense-input" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Name</label>
                <input class="expense-input" type="text" name="name" placeholder="HDFC Bank / SBI Card / Base UPI" required>
            </div>
            <div class="expense-field">
                <label>Details</label>
                <input class="expense-input" type="text" name="details" placeholder="Last 4 digits / UPI ID / notes">
            </div>
            <div class="expense-field">
                <label>Sort</label>
                <input class="expense-input" type="number" min="0" name="sort_order" value="{{ $nextSortOrder }}">
            </div>
            <div class="expense-field" style="display:flex;align-items:end;gap:12px;">
                <label style="display:flex;align-items:center;gap:8px;margin:0;text-transform:none;letter-spacing:0;font-size:14px;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active
                </label>
                <button type="submit" class="expense-btn primary"><i class="fas fa-plus"></i> Add</button>
            </div>
        </form>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Saved Payment Sources</h2>
                <p>Inactive source old entries me visible rahega, new expense form me selectable nahi hoga.</p>
            </div>
        </div>

        @foreach($paymentMethods as $method)
            <form id="payment-method-form-{{ $method->id }}" method="POST" action="{{ route('finance-manager.settings.payment-methods.update', $method) }}" style="display:none;">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <div class="expense-table-wrap">
            <table class="expense-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Details</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paymentMethods as $method)
                        <tr>
                            <td>
                                <select name="type" form="payment-method-form-{{ $method->id }}" class="expense-input" required>
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}" @selected($method->type === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="expense-input" form="payment-method-form-{{ $method->id }}" type="text" name="name" value="{{ $method->name }}" required></td>
                            <td><input class="expense-input" form="payment-method-form-{{ $method->id }}" type="text" name="details" value="{{ $method->details }}"></td>
                            <td><input class="expense-input" form="payment-method-form-{{ $method->id }}" type="number" min="0" name="sort_order" value="{{ $method->sort_order }}"></td>
                            <td>
                                <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#0f5b42;">
                                    <input type="checkbox" form="payment-method-form-{{ $method->id }}" name="is_active" value="1" @checked($method->is_active)>
                                    Active
                                </label>
                            </td>
                            <td>{{ optional($method->updated_at)->format('d M Y, h:i A') }}</td>
                            <td><button type="submit" form="payment-method-form-{{ $method->id }}" class="expense-btn soft"><i class="fas fa-floppy-disk"></i> Save</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No payment methods configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>PO Access Control</h2>
                <p>Control who can raise purchase PO, reimbursement PO, and who can mark PO payment done.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('finance-manager.settings.po-access.update') }}">
            @csrf
            <div class="expense-table-wrap">
                <table class="expense-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Need Purchase</th>
                            <th>Reimbursement</th>
                            <th>Payment Done</th>
                            <th>Payment Limit</th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($poUsers as $index => $user)
                            @php($access = $user->purchaseOrderAccess)
                            <tr>
                                <td>
                                    <strong>{{ $user->name }}</strong><br>
                                    <small>{{ $user->email ?: $user->phone }}</small>
                                    <input type="hidden" name="permissions[{{ $index }}][user_id]" value="{{ $user->id }}">
                                </td>
                                <td>{{ $user->role?->name ?: 'N/A' }}</td>
                                <td>
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#0f5b42;">
                                        <input type="checkbox" name="permissions[{{ $index }}][can_raise_need_purchase]" value="1" @checked($access?->can_raise_need_purchase)>
                                        Allow
                                    </label>
                                </td>
                                <td>
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#0f5b42;">
                                        <input type="checkbox" name="permissions[{{ $index }}][can_raise_reimbursement]" value="1" @checked($access?->can_raise_reimbursement)>
                                        Allow
                                    </label>
                                </td>
                                <td>
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#0f5b42;">
                                        <input type="checkbox" name="permissions[{{ $index }}][can_mark_payment_done]" value="1" @checked($access?->can_mark_payment_done)>
                                        Allow
                                    </label>
                                </td>
                                <td>
                                    <input class="expense-input" type="number" step="0.01" min="0" name="permissions[{{ $index }}][payment_limit]" value="{{ $access?->payment_limit }}" placeholder="No limit">
                                    <small>Blank means no limit.</small>
                                </td>
                                <td>
                                    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#0f5b42;">
                                        <input type="checkbox" name="permissions[{{ $index }}][is_active]" value="1" @checked($access?->is_active ?? true)>
                                        Active
                                    </label>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No users found for PO access setup.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                <button type="submit" class="expense-btn primary"><i class="fas fa-floppy-disk"></i> Save PO Access</button>
            </div>
        </form>
    </section>

    <section class="expense-dual-grid">
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Future Settings</h2>
                    <p>Next phase me approval defaults, report defaults, aur finance rules yahin add kar sakte hain.</p>
                </div>
            </div>
        </div>
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Current Rule</h2>
                    <p>Bank, UPI, Credit Card select karne par related source required hai. Cash me optional hai.</p>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
