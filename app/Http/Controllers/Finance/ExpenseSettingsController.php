<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpensePaymentMethod;
use App\Models\PurchaseOrderAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseSettingsController extends Controller
{
    public function index(): View
    {
        return view('finance-manager.settings', [
            'types' => ExpensePaymentMethod::types(),
            'paymentMethods' => ExpensePaymentMethod::query()
                ->with('creator')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'nextSortOrder' => ((int) ExpensePaymentMethod::query()->max('sort_order')) + 10,
            'poUsers' => User::query()
                ->with(['role', 'purchaseOrderAccess'])
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                    Role::FINANCE_MANAGER,
                    Role::HR_MANAGER,
                    Role::CRM,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                    Role::SALES_EXECUTIVE,
                    Role::MARKETING_MANAGER,
                    Role::MARKETING_EXECUTIVE,
                ]))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $validated = $this->validatePaymentMethod($request);
        $validated['created_by'] = $request->user()->id;

        ExpensePaymentMethod::create($validated);

        return back()->with('success', 'Payment method added successfully.');
    }

    public function updatePaymentMethod(Request $request, ExpensePaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($this->validatePaymentMethod($request));

        return back()->with('success', 'Payment method updated successfully.');
    }

    public function updatePoAccess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions.*.can_raise_need_purchase' => ['nullable', 'boolean'],
            'permissions.*.can_raise_reimbursement' => ['nullable', 'boolean'],
            'permissions.*.can_mark_payment_done' => ['nullable', 'boolean'],
            'permissions.*.payment_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'permissions.*.is_active' => ['nullable', 'boolean'],
        ]);

        foreach (($validated['permissions'] ?? []) as $permission) {
            PurchaseOrderAccess::updateOrCreate(
                ['user_id' => $permission['user_id']],
                [
                    'can_raise_need_purchase' => (bool) ($permission['can_raise_need_purchase'] ?? false),
                    'can_raise_reimbursement' => (bool) ($permission['can_raise_reimbursement'] ?? false),
                    'can_mark_payment_done' => (bool) ($permission['can_mark_payment_done'] ?? false),
                    'payment_limit' => $permission['payment_limit'] ?? null,
                    'is_active' => (bool) ($permission['is_active'] ?? true),
                    'updated_by' => $request->user()->id,
                ]
            );
        }

        return back()->with('success', 'PO access permissions updated successfully.');
    }

    private function validatePaymentMethod(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(ExpensePaymentMethod::types()))],
            'name' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => false,
            'sort_order' => 0,
        ];
    }
}
