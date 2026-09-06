<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseAssignment;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\KnowledgeBasePath;
use App\Models\KnowledgeBaseProgress;
use App\Models\Project;
use App\Services\KnowledgeBaseProgressService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KnowledgeBaseProgressService $progressService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user()->loadMissing('role');
        $tab = $request->string('tab')->value() ?: 'assigned';
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $projectId = $request->input('project_id');
        $statusFilter = $request->string('status_filter')->value() ?: '';

        $baseItemQuery = KnowledgeBaseItem::query()
            ->with([
                'category:id,name',
                'project:id,name',
                'assignments' => fn ($query) => $query->where('user_id', $user->id),
                'progressRecords' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->published()
            ->visibleToUser($user)
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%' . $search . '%'))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('content_updated_at')
            ->orderByDesc('updated_at');

        if ($tab === 'assigned') {
            $baseItemQuery->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id));
        }

        $itemsCollection = $baseItemQuery->get();
        $itemsCollection = $itemsCollection->map(fn (KnowledgeBaseItem $item) => $this->decorateItemForUser($item, $user->id))
            ->filter(function (KnowledgeBaseItem $item) use ($statusFilter, $tab) {
                if ($tab !== 'assigned' || $statusFilter === '') {
                    return true;
                }

                return ($item->display_status ?? 'not_started') === $statusFilter;
            })
            ->values();

        $items = $this->paginateCollection($itemsCollection, 12, $request);

        $featuredItems = collect();
        if ($tab === 'library') {
            $featuredItems = KnowledgeBaseItem::query()
                ->with(['category:id,name', 'project:id,name'])
                ->published()
                ->visibleToUser($user)
                ->where('is_featured', true)
                ->when($search !== '', fn ($query) => $query->where('title', 'like', '%' . $search . '%'))
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
                ->orderBy('display_order')
                ->orderByDesc('content_updated_at')
                ->limit(6)
                ->get()
                ->map(fn (KnowledgeBaseItem $item) => $this->decorateItemForUser($item, $user->id));
        }

        $assignedPaths = collect();
        $featuredPaths = collect();

        $basePaths = KnowledgeBasePath::query()
            ->with([
                'pathItems.item:id,title,status',
                'assignments' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->published()
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%' . $search . '%'))
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('updated_at');

        if ($tab === 'assigned') {
            $assignedPaths = (clone $basePaths)
                ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id))
                ->limit(6)
                ->get()
                ->map(fn ($path) => $this->decoratePathForUser($path, $user->id));
        } else {
            $featuredPaths = (clone $basePaths)
                ->where('is_featured', true)
                ->limit(6)
                ->get()
                ->map(fn ($path) => $this->decoratePathForUser($path, $user->id));
        }

        $categories = KnowledgeBaseCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::query()
            ->whereIn('id', KnowledgeBaseItem::query()->whereNotNull('project_id')->distinct()->pluck('project_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('knowledge-base.index', [
            'tab' => $tab,
            'items' => $items,
            'featuredItems' => $featuredItems,
            'assignedPaths' => $assignedPaths,
            'featuredPaths' => $featuredPaths,
            'categories' => $categories,
            'projects' => $projects,
            'search' => $search,
            'selectedCategoryId' => $categoryId,
            'selectedProjectId' => $projectId,
            'selectedStatusFilter' => $statusFilter,
            'canManageKnowledgeBase' => $user->canManageKnowledgeBase(),
            'canViewKnowledgeBaseReports' => $user->canViewKnowledgeBaseReports(),
            'statusOptions' => [
                'not_started' => 'Not Started',
                'in_progress' => 'In Progress',
                'due_soon' => 'Due Soon',
                'overdue' => 'Overdue',
                'completed' => 'Completed',
                'completed_late' => 'Completed Late',
            ],
        ]);
    }

    public function show(Request $request, KnowledgeBaseItem $item)
    {
        $user = $request->user()->loadMissing('role');
        $canManage = $user->canManageKnowledgeBase();

        if ($item->status !== 'published' && !$canManage) {
            abort(404);
        }

        if (!$canManage && !$this->itemVisibleToUser($item, $user)) {
            abort(404);
        }

        $item->load([
            'category:id,name',
            'project:id,name',
            'assignments' => fn ($query) => $query->where('user_id', $user->id),
            'progressRecords' => fn ($query) => $query->where('user_id', $user->id),
        ]);

        $item = $this->decorateItemForUser($item, $user->id);
        $progress = $item->current_progress;
        $assignment = $item->current_assignment;

        return view('knowledge-base.show', [
            'item' => $item,
            'progress' => $progress,
            'assignment' => $assignment,
            'canManageKnowledgeBase' => $canManage,
            'videoMeta' => $this->buildVideoMeta($item->video_url),
            'statusLabel' => $this->progressService->statusLabel($item->display_status ?? 'not_started'),
        ]);
    }

    public function progress(Request $request, KnowledgeBaseItem $item): JsonResponse
    {
        $user = $request->user()->loadMissing('role');
        $canManage = $user->canManageKnowledgeBase();

        if ($item->status !== 'published' && !$canManage) {
            abort(404);
        }

        if (!$canManage && !$this->itemVisibleToUser($item, $user)) {
            abort(404);
        }

        $payload = $request->validate([
            'event' => 'required|string|in:opened,article_tick,article_scroll,video_progress,pdf_opened,pdf_qualified',
            'seconds' => 'nullable|integer|min:0|max:3600',
            'percent' => 'nullable|integer|min:0|max:100',
            'position' => 'nullable|integer|min:0|max:86400',
        ]);

        $progress = KnowledgeBaseProgress::firstOrCreate(
            [
                'knowledge_base_item_id' => $item->id,
                'user_id' => $user->id,
            ],
            [
                'status' => 'not_started',
            ]
        );

        $assignment = KnowledgeBaseAssignment::query()
            ->where('knowledge_base_item_id', $item->id)
            ->where('user_id', $user->id)
            ->first();

        $now = now();
        $progress->started_at = $progress->started_at ?: $now;
        $progress->last_interaction_at = $now;

        if ($progress->status === 'not_started') {
            $progress->status = 'in_progress';
        }

        switch ($payload['event']) {
            case 'article_tick':
                $progress->article_seconds_viewed = max($progress->article_seconds_viewed, (int) ($payload['seconds'] ?? 0));
                break;
            case 'article_scroll':
                $progress->article_scroll_percent = max($progress->article_scroll_percent, (int) ($payload['percent'] ?? 0));
                break;
            case 'video_progress':
                $progress->video_progress_percent = max($progress->video_progress_percent, (int) ($payload['percent'] ?? 0));
                $progress->video_last_position_seconds = max($progress->video_last_position_seconds, (int) ($payload['position'] ?? 0));
                break;
            case 'pdf_opened':
                $progress->pdf_opened_at = $progress->pdf_opened_at ?: $now;
                break;
            case 'pdf_qualified':
                $progress->pdf_opened_at = $progress->pdf_opened_at ?: $now->copy()->subSeconds(5);
                $progress->pdf_interacted_at = $now;
                $progress->pdf_seconds_viewed = max($progress->pdf_seconds_viewed, (int) ($payload['seconds'] ?? 5));
                break;
            default:
                break;
        }

        if ($this->shouldMarkCompleted($item, $progress)) {
            $progress->status = 'completed';
            $progress->completed_at = $progress->completed_at ?: $now;

            if ($assignment?->due_date && $progress->completed_at->gt($assignment->due_date->copy()->endOfDay())) {
                $assignment->forceFill(['completed_late' => true])->save();
            }
        }

        $progress->save();

        $status = $this->progressService->resolveAssignmentStatus($assignment, $progress);

        return response()->json([
            'ok' => true,
            'status' => $status,
            'status_label' => $this->progressService->statusLabel($status),
            'completed_at' => optional($progress->completed_at)->toIso8601String(),
        ]);
    }

    private function shouldMarkCompleted(KnowledgeBaseItem $item, KnowledgeBaseProgress $progress): bool
    {
        if (filled($item->article_content) && $progress->article_seconds_viewed >= 15 && $progress->article_scroll_percent >= 20) {
            return true;
        }

        if (filled($item->video_url) && $progress->video_progress_percent >= 35) {
            return true;
        }

        if (filled($item->pdf_path) && $progress->pdf_opened_at && $progress->pdf_interacted_at && $progress->pdf_seconds_viewed >= 5) {
            return true;
        }

        return false;
    }

    private function itemVisibleToUser(KnowledgeBaseItem $item, $user): bool
    {
        if ($item->visibility_type === 'all_users') {
            return true;
        }

        if ($item->assignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($item->visibility_type === 'selected_roles') {
            return in_array(optional($user->role)->slug, $item->visible_role_slugs ?? [], true);
        }

        if ($item->visibility_type === 'selected_users') {
            return in_array($user->id, array_map('intval', $item->visible_user_ids ?? []), true);
        }

        return false;
    }

    private function decorateItemForUser(KnowledgeBaseItem $item, int $userId): KnowledgeBaseItem
    {
        $assignment = $item->relationLoaded('assignments')
            ? $item->assignments->first()
            : $item->assignments()->where('user_id', $userId)->first();
        $progress = $item->relationLoaded('progressRecords')
            ? $item->progressRecords->first()
            : $item->progressRecords()->where('user_id', $userId)->first();

        $displayStatus = $this->progressService->resolveAssignmentStatus($assignment, $progress);

        $item->setAttribute('display_status', $displayStatus);
        $item->setAttribute('display_status_label', $this->progressService->statusLabel($displayStatus));
        $item->setAttribute('is_updated_for_user', (bool) (
            $assignment?->is_required
            && $progress?->completed_at
            && ($item->content_updated_at ?: $item->updated_at)?->gt($progress->completed_at)
        ));
        $item->setRelation('current_assignment', $assignment);
        $item->setRelation('current_progress', $progress);

        return $item;
    }

    private function decoratePathForUser(KnowledgeBasePath $path, int $userId): KnowledgeBasePath
    {
        $assignment = $path->assignments->first();
        $itemIds = $path->pathItems->pluck('knowledge_base_item_id')->all();
        $progressRecords = KnowledgeBaseProgress::query()
            ->where('user_id', $userId)
            ->whereIn('knowledge_base_item_id', $itemIds)
            ->whereNotNull('completed_at')
            ->pluck('knowledge_base_item_id')
            ->all();

        $completedCount = collect($itemIds)->filter(fn ($itemId) => in_array($itemId, $progressRecords, true))->count();
        $totalItems = count($itemIds);

        $displayStatus = $assignment
            ? $this->progressService->resolvePathAssignmentStatus($assignment, $completedCount, $totalItems)
            : ($completedCount > 0 ? 'in_progress' : 'not_started');

        $path->setAttribute('completed_items_count', $completedCount);
        $path->setAttribute('total_items_count', $totalItems);
        $path->setAttribute('display_status', $displayStatus);
        $path->setAttribute('display_status_label', $this->progressService->statusLabel($displayStatus));
        $path->setRelation('current_assignment', $assignment);

        return $path;
    }

    private function paginateCollection(Collection $collection, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage() ?: 1;
        $results = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $results,
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function buildVideoMeta(?string $url): array
    {
        $value = trim((string) $url);
        if ($value === '') {
            return ['type' => null];
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([^&?/]+)~i', $value, $matches)) {
            return [
                'type' => 'youtube',
                'embed_url' => 'https://www.youtube.com/embed/' . $matches[1] . '?enablejsapi=1&rel=0',
            ];
        }

        if (preg_match('~\.(mp4|webm|ogg)(\?.*)?$~i', $value)) {
            return [
                'type' => 'html5',
                'embed_url' => $value,
            ];
        }

        return [
            'type' => 'external',
            'embed_url' => $value,
        ];
    }
}
