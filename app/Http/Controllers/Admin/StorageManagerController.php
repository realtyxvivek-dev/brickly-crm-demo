<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaTrashItem;
use App\Services\StorageManagerService;
use Illuminate\Http\Request;

class StorageManagerController extends Controller
{
    public function __construct(private StorageManagerService $storageManager)
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $tab = $request->input('tab', 'overview');
        $items = $this->storageManager->activeItems($filters);
        $trashItems = $this->storageManager->trashItems($filters);
        $overview = $this->storageManager->overview($filters);

        if ($tab === 'photos') {
            $items = $items->where('media_type', 'photo')->values();
        } elseif ($tab === 'recordings') {
            $items = $items->where('media_type', 'recording')->values();
        }

        return view('admin.storage-manager.index', [
            'tab' => $tab,
            'filters' => $filters,
            'items' => $items,
            'trashItems' => $trashItems,
            'overview' => $overview,
            'formatBytes' => fn (?int $bytes) => $this->storageManager->formatBytes($bytes),
        ]);
    }

    public function trash(Request $request)
    {
        $validated = $request->validate([
            'media_key' => ['required', 'string'],
        ]);

        $trash = $this->storageManager->trashMedia($validated['media_key'], $request->user());

        if (!$trash) {
            return back()->withErrors(['media' => 'This file is protected, external, or already unavailable.']);
        }

        return back()->with('success', 'File moved to trash. You can restore it within 7 days.');
    }

    public function bulkTrash(Request $request)
    {
        $validated = $request->validate([
            'media_keys' => ['required', 'array'],
            'media_keys.*' => ['string'],
        ]);

        $count = 0;
        foreach ($validated['media_keys'] as $key) {
            if ($this->storageManager->trashMedia($key, $request->user())) {
                $count++;
            }
        }

        return back()->with('success', $count . ' file(s) moved to trash.');
    }

    public function restore(Request $request, MediaTrashItem $trashItem)
    {
        $this->storageManager->restore($trashItem, $request->user());

        return back()->with('success', 'File restored.');
    }

    public function destroy(Request $request, MediaTrashItem $trashItem)
    {
        if (!$this->storageManager->permanentlyDelete($trashItem, $request->user())) {
            return back()->withErrors(['media' => 'This file cannot be permanently deleted.']);
        }

        return back()->with('success', 'File permanently deleted.');
    }

    private function filters(Request $request): array
    {
        return [
            'type' => $request->input('type', 'all'),
            'module' => $request->input('module', 'all'),
            'search' => trim((string) $request->input('search', '')),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
        ];
    }
}
