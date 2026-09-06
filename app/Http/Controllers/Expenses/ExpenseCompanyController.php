<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ResolvesExpenseView;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCompanyController extends Controller
{
    use ResolvesExpenseView;

    public function index(): View
    {
        $companies = Company::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view($this->expenseView('companies'), [
            'companies' => $companies,
            'nextSortOrder' => ((int) Company::query()->max('sort_order')) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        Company::create($validated);

        return back()->with('success', 'Company saved successfully.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $this->validatePayload($request, $company);
        $company->update($validated);

        return back()->with('success', 'Company updated successfully.');
    }

    private function validatePayload(Request $request, ?Company $company = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('companies', 'code')->ignore($company?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null
            ? (int) $validated['sort_order']
            : ($company?->sort_order ?? (((int) Company::query()->max('sort_order')) + 1));

        return $validated;
    }
}
