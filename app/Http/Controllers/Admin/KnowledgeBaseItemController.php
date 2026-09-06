<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeBaseItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');

        $items = KnowledgeBaseItem::query()
            ->with(['category:id,name', 'project:id,name', 'creator:id,name'])
            ->withCount('assignments')
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%' . $search . '%'))
            ->when(in_array($status, ['draft', 'published'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.knowledge-base.index', [
            'items' => $items,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $item = new KnowledgeBaseItem([
            'status' => 'draft',
            'visibility_type' => 'all_users',
            'is_featured' => false,
            'display_order' => 0,
        ]);

        return view('admin.knowledge-base.form', [
            'item' => $item,
            'categories' => $this->categories(),
            'projects' => $this->projects(),
            'assignableUsers' => $this->assignableUsers(),
            'assignedUserIds' => [],
            'assignableRoles' => $this->assignableRoles(),
            'similarItems' => $this->findSimilarItems((string) $request->input('title')),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateItem($request);

        $item = new KnowledgeBaseItem();
        $item->fill($this->payloadForPersistence($data, $item));
        $item->created_by = $request->user()->id;
        $item->updated_by = $request->user()->id;
        $item->save();

        $this->syncAssignments($item, $data, $request->user()->id);

        return redirect()
            ->route('admin.knowledge-base.edit', $item)
            ->with('success', 'Knowledge topic created successfully.');
    }

    public function edit(KnowledgeBaseItem $item): View
    {
        $item->load('assignments:user_id');

        return view('admin.knowledge-base.form', [
            'item' => $item,
            'categories' => $this->categories(),
            'projects' => $this->projects(),
            'assignableUsers' => $this->assignableUsers(),
            'assignedUserIds' => $item->assignments->pluck('user_id')->all(),
            'assignableRoles' => $this->assignableRoles(),
            'similarItems' => $this->findSimilarItems($item->title, $item->id),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, KnowledgeBaseItem $item): RedirectResponse
    {
        $data = $this->validateItem($request);

        $item->fill($this->payloadForPersistence($data, $item));
        $item->updated_by = $request->user()->id;
        $item->save();

        $this->syncAssignments($item, $data, $request->user()->id);

        return redirect()
            ->route('admin.knowledge-base.edit', $item)
            ->with('success', 'Knowledge topic updated successfully.');
    }

    public function destroy(KnowledgeBaseItem $item): RedirectResponse
    {
        if ($item->pdf_path) {
            Storage::disk('public')->delete($item->pdf_path);
        }

        if ($item->thumbnail_path) {
            Storage::disk('public')->delete($item->thumbnail_path);
        }

        $item->delete();

        return redirect()
            ->route('admin.knowledge-base.index')
            ->with('success', 'Knowledge topic deleted successfully.');
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|exists:knowledge_base_categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'short_summary' => 'nullable|string|max:1000',
            'article_content' => 'nullable|string',
            'video_url' => 'nullable|url|max:1000',
            'pdf_file' => 'nullable|file|mimes:pdf|max:20480',
            'thumbnail_file' => 'nullable|image|max:5120',
            'status' => 'required|in:draft,published',
            'visibility_type' => 'required|in:all_users,selected_roles,selected_users',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'integer|exists:users,id',
            'visible_role_slugs' => 'nullable|array',
            'visible_role_slugs.*' => 'string',
            'visible_user_ids' => 'nullable|array',
            'visible_user_ids.*' => 'integer|exists:users,id',
            'is_required' => 'nullable|boolean',
            'due_date' => 'nullable|date',
            'change_summary' => 'nullable|string|max:1000',
        ]);
    }

    private function payloadForPersistence(array $data, KnowledgeBaseItem $item): array
    {
        $contentFingerprint = md5(json_encode([
            trim($data['title']),
            $data['short_summary'] ?? null,
            $data['article_content'] ?? null,
            $data['video_url'] ?? null,
            $data['status'] ?? null,
            $data['visibility_type'] ?? null,
            $data['visible_role_slugs'] ?? [],
            $data['visible_user_ids'] ?? [],
            $data['change_summary'] ?? null,
        ]));
        $existingFingerprint = md5(json_encode([
            $item->title,
            $item->short_summary,
            $item->article_content,
            $item->video_url,
            $item->status,
            $item->visibility_type,
            $item->visible_role_slugs ?? [],
            $item->visible_user_ids ?? [],
            $item->change_summary,
        ]));

        $payload = [
            'category_id' => $data['category_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'title' => trim($data['title']),
            'slug' => $this->uniqueSlug(trim($data['title']), $item->id),
            'short_summary' => $data['short_summary'] ?? null,
            'article_content' => $data['article_content'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'change_summary' => $data['change_summary'] ?? null,
            'status' => $data['status'],
            'visibility_type' => $data['visibility_type'] ?? 'all_users',
            'visible_role_slugs' => ($data['visibility_type'] ?? 'all_users') === 'selected_roles'
                ? array_values(array_unique($data['visible_role_slugs'] ?? []))
                : null,
            'visible_user_ids' => ($data['visibility_type'] ?? 'all_users') === 'selected_users'
                ? array_values(array_unique(array_map('intval', $data['visible_user_ids'] ?? [])))
                : null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'published_at' => ($data['status'] ?? 'draft') === 'published'
                ? ($item->published_at ?: now())
                : null,
            'content_updated_at' => $item->exists
                ? ($contentFingerprint !== $existingFingerprint || request()->hasFile('pdf_file') || request()->hasFile('thumbnail_file')
                    ? now()
                    : ($item->content_updated_at ?: $item->updated_at ?: now()))
                : now(),
        ];

        if (request()->hasFile('pdf_file')) {
            if ($item->pdf_path) {
                Storage::disk('public')->delete($item->pdf_path);
            }

            $payload['pdf_path'] = request()->file('pdf_file')->store('knowledge-base/pdfs', 'public');
        }

        if (request()->hasFile('thumbnail_file')) {
            if ($item->thumbnail_path) {
                Storage::disk('public')->delete($item->thumbnail_path);
            }

            $payload['thumbnail_path'] = request()->file('thumbnail_file')->store('knowledge-base/thumbnails', 'public');
        }

        return $payload;
    }

    private function syncAssignments(KnowledgeBaseItem $item, array $data, int $assignerId): void
    {
        $userIds = array_unique(array_map('intval', $data['assigned_user_ids'] ?? []));
        $dueDate = $data['due_date'] ?? null;
        $isRequired = (bool) ($data['is_required'] ?? true);

        $item->assignments()->whereNotIn('user_id', $userIds)->delete();

        $existingAssignments = $item->assignments()->get()->keyBy('user_id');
        $now = now();

        foreach ($userIds as $userId) {
            $assignment = $existingAssignments->get($userId);

            if ($assignment) {
                $assignment->update([
                    'is_required' => $isRequired,
                    'due_date' => $dueDate,
                ]);
                continue;
            }

            $created = $item->assignments()->create([
                'user_id' => $userId,
                'assigned_by' => $assignerId,
                'assigned_at' => $now,
                'is_required' => $isRequired,
                'due_date' => $dueDate,
            ]);

            AppNotification::create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_KNOWLEDGE_BASE_ASSIGNED,
                'title' => 'New Knowledge Topic Assigned',
                'message' => $item->title . ' aapko assign kiya gaya hai.',
                'data' => [
                    'knowledge_base_item_id' => $item->id,
                    'knowledge_base_assignment_id' => $created->id,
                    'due_date' => $dueDate,
                ],
                'action_type' => AppNotification::ACTION_KNOWLEDGE_BASE,
                'action_url' => route('knowledge-base.show', $item->slug),
            ]);
        }
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'knowledge-topic';
        $slug = $base;
        $counter = 2;

        while (
            KnowledgeBaseItem::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function findSimilarItems(string $title, ?int $ignoreId = null): Collection
    {
        $value = trim($title);
        if ($value === '') {
            return collect();
        }

        $query = KnowledgeBaseItem::query()
            ->select('id', 'title', 'status', 'updated_at')
            ->when($ignoreId, fn ($builder) => $builder->where('id', '!=', $ignoreId))
            ->where(function ($builder) use ($value) {
                $builder->where('title', 'like', '%' . $value . '%');

                foreach (array_filter(explode(' ', $value)) as $word) {
                    if (mb_strlen($word) >= 4) {
                        $builder->orWhere('title', 'like', '%' . $word . '%');
                    }
                }
            })
            ->orderByDesc('updated_at')
            ->limit(5);

        return $query->get();
    }

    private function categories(): Collection
    {
        return KnowledgeBaseCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function projects(): Collection
    {
        return Project::query()
            ->orderBy('name')
            ->get(['id', 'name']);
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

    private function assignableRoles(): Collection
    {
        return Role::query()
            ->whereIn('slug', [
                Role::CRM,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
                Role::HR_MANAGER,
                Role::FINANCE_MANAGER,
                Role::MARKETING_MANAGER,
                Role::MARKETING_EXECUTIVE,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
