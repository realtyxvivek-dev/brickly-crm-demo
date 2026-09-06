<?php

namespace App\Http\Controllers;

use App\Models\MarketingManagerNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketingManagerNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $manager = $this->abortUnlessMarketingManager($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'note' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        MarketingManagerNote::query()->create([
            'manager_id' => $manager->id,
            'title' => $validated['title'],
            'note' => $validated['note'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        return back()->with('success', 'Manager note added.');
    }

    public function update(Request $request, MarketingManagerNote $note): RedirectResponse
    {
        $manager = $this->abortUnlessMarketingManager($request);
        abort_unless((int) $note->manager_id === (int) $manager->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'note' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $note->update([
            'title' => $validated['title'],
            'note' => $validated['note'],
            'sort_order' => $validated['sort_order'] ?? $note->sort_order,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $note->is_active,
            'updated_by' => $manager->id,
        ]);

        return back()->with('success', 'Manager note updated.');
    }

    public function archive(Request $request, MarketingManagerNote $note): RedirectResponse
    {
        $manager = $this->abortUnlessMarketingManager($request);
        abort_unless((int) $note->manager_id === (int) $manager->id, 404);

        $note->update([
            'is_active' => false,
            'updated_by' => $manager->id,
        ]);

        return back()->with('success', 'Manager note archived.');
    }

    private function abortUnlessMarketingManager(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isMarketingManager(), 404);

        return $user;
    }
}
