<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ResolvesExpenseView;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    use ResolvesExpenseView;

    public function index(): View
    {
        $categories = ExpenseCategory::query()
            ->withCount('subcategories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view($this->expenseView('categories'), [
            'categories' => $categories,
            'nextSortOrder' => ((int) ExpenseCategory::query()->max('sort_order')) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        ExpenseCategory::create($validated);

        return back()->with('success', 'Category saved successfully.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $validated = $this->validatePayload($request, $category);
        $category->update($validated);

        return back()->with('success', 'Category updated successfully.');
    }

    private function validatePayload(Request $request, ?ExpenseCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'code')->ignore($category?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null
            ? (int) $validated['sort_order']
            : ($category?->sort_order ?? (((int) ExpenseCategory::query()->max('sort_order')) + 1));

        return $validated;
    }
}
