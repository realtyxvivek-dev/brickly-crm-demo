<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeBaseCategoryController extends Controller
{
    public function index(): View
    {
        $categories = KnowledgeBaseCategory::query()
            ->withCount('items')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.knowledge-base.categories', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        KnowledgeBaseCategory::create([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug(trim($data['name'])),
            'description' => $data['description'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.knowledge-base.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, KnowledgeBaseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug(trim($data['name']), $category->id),
            'description' => $data['description'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.knowledge-base.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(KnowledgeBaseCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return redirect()
                ->route('admin.knowledge-base.categories.index')
                ->with('error', 'Category still has knowledge items. Reassign them before delete.');
        }

        $category->delete();

        return redirect()
            ->route('admin.knowledge-base.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (
            KnowledgeBaseCategory::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
