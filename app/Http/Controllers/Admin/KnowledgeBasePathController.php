<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\KnowledgeBaseItem;
use App\Models\KnowledgeBasePath;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeBasePathController extends Controller
{
    public function index(): View
    {
        $paths = KnowledgeBasePath::query()
            ->withCount(['pathItems', 'assignments'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('admin.knowledge-base.paths.index', [
            'paths' => $paths,
        ]);
    }

    public function create(): View
    {
        return view('admin.knowledge-base.paths.form', [
            'path' => new KnowledgeBasePath([
                'status' => 'draft',
                'display_order' => 0,
            ]),
            'items' => $this->publishedItems(),
            'assignableUsers' => $this->assignableUsers(),
            'assignedUserIds' => [],
            'selectedItemIds' => [],
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $path = new KnowledgeBasePath();
        $path->fill($this->payload($data, $path));
        $path->created_by = $request->user()->id;
        $path->updated_by = $request->user()->id;
        $path->save();

        $this->syncPathItems($path, $data['knowledge_base_item_ids'] ?? []);
        $this->syncAssignments($path, $data, $request->user()->id);

        return redirect()
            ->route('admin.knowledge-base.paths.edit', $path)
            ->with('success', 'Learning path created successfully.');
    }

    public function edit(KnowledgeBasePath $path): View
    {
        $path->load(['pathItems', 'assignments']);

        return view('admin.knowledge-base.paths.form', [
            'path' => $path,
            'items' => $this->publishedItems(),
            'assignableUsers' => $this->assignableUsers(),
            'assignedUserIds' => $path->assignments->pluck('user_id')->all(),
            'selectedItemIds' => $path->pathItems->pluck('knowledge_base_item_id')->all(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, KnowledgeBasePath $path): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $path->fill($this->payload($data, $path));
        $path->updated_by = $request->user()->id;
        $path->save();

        $this->syncPathItems($path, $data['knowledge_base_item_ids'] ?? []);
        $this->syncAssignments($path, $data, $request->user()->id);

        return redirect()
            ->route('admin.knowledge-base.paths.edit', $path)
            ->with('success', 'Learning path updated successfully.');
    }

    public function destroy(KnowledgeBasePath $path): RedirectResponse
    {
        $path->delete();

        return redirect()
            ->route('admin.knowledge-base.paths.index')
            ->with('success', 'Learning path deleted successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'short_summary' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,published',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'knowledge_base_item_ids' => 'nullable|array',
            'knowledge_base_item_ids.*' => 'integer|exists:knowledge_base_items,id',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'integer|exists:users,id',
            'is_required' => 'nullable|boolean',
            'due_date' => 'nullable|date',
        ]);
    }

    private function payload(array $data, KnowledgeBasePath $path): array
    {
        return [
            'title' => trim($data['title']),
            'slug' => $this->uniqueSlug(trim($data['title']), $path->id),
            'short_summary' => $data['short_summary'] ?? null,
            'status' => $data['status'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'published_at' => ($data['status'] ?? 'draft') === 'published'
                ? ($path->published_at ?: now())
                : null,
        ];
    }

    private function syncPathItems(KnowledgeBasePath $path, array $itemIds): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $path->pathItems()->whereNotIn('knowledge_base_item_id', $itemIds)->delete();

        foreach ($itemIds as $index => $itemId) {
            $path->pathItems()->updateOrCreate(
                ['knowledge_base_item_id' => $itemId],
                ['display_order' => $index]
            );
        }
    }

    private function syncAssignments(KnowledgeBasePath $path, array $data, int $assignerId): void
    {
        $userIds = array_unique(array_map('intval', $data['assigned_user_ids'] ?? []));
        $dueDate = $data['due_date'] ?? null;
        $isRequired = (bool) ($data['is_required'] ?? true);

        $path->assignments()->whereNotIn('user_id', $userIds)->delete();
        $existingAssignments = $path->assignments()->get()->keyBy('user_id');

        foreach ($userIds as $userId) {
            $assignment = $existingAssignments->get($userId);

            if ($assignment) {
                $assignment->update([
                    'is_required' => $isRequired,
                    'due_date' => $dueDate,
                ]);
                continue;
            }

            $created = $path->assignments()->create([
                'user_id' => $userId,
                'assigned_by' => $assignerId,
                'assigned_at' => now(),
                'is_required' => $isRequired,
                'due_date' => $dueDate,
            ]);

            AppNotification::create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_KNOWLEDGE_BASE_PATH_ASSIGNED,
                'title' => 'New Learning Path Assigned',
                'message' => $path->title . ' learning path assign kiya gaya hai.',
                'data' => [
                    'knowledge_base_path_id' => $path->id,
                    'knowledge_base_path_assignment_id' => $created->id,
                    'due_date' => $dueDate,
                ],
                'action_type' => AppNotification::ACTION_KNOWLEDGE_BASE,
                'action_url' => route('knowledge-base.index', ['tab' => 'assigned']),
            ]);
        }
    }

    private function publishedItems(): Collection
    {
        return KnowledgeBaseItem::query()
            ->published()
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    private function assignableUsers(): Collection
    {
        return User::query()
            ->with('role:id,slug,name')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::CRM,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
                Role::HR_MANAGER,
                Role::FINANCE_MANAGER,
                Role::MARKETING_MANAGER,
                Role::MARKETING_EXECUTIVE,
            ]))
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'learning-path';
        $slug = $base;
        $counter = 2;

        while (
            KnowledgeBasePath::query()
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
