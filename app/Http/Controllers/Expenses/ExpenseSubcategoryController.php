<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ResolvesExpenseView;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseSubcategoryController extends Controller
{
    use ResolvesExpenseView;

    public function index(): View
    {
        $categories = ExpenseCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $subcategories = ExpenseSubcategory::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view($this->expenseView('subcategories'), [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'nextSortOrder' => ((int) ExpenseSubcategory::query()->max('sort_order')) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        ExpenseSubcategory::create($validated);

        return back()->with('success', 'Subcategory saved successfully.');
    }

    public function update(Request $request, ExpenseSubcategory $subcategory): RedirectResponse
    {
        $validated = $this->validatePayload($request, $subcategory);
        $subcategory->update($validated);

        return back()->with('success', 'Subcategory updated successfully.');
    }

    private function validatePayload(Request $request, ?ExpenseSubcategory $subcategory = null): array
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_subcategories', 'code')->ignore($subcategory?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null
            ? (int) $validated['sort_order']
            : ($subcategory?->sort_order ?? (((int) ExpenseSubcategory::query()->max('sort_order')) + 1));

        return $validated;
    }
}
