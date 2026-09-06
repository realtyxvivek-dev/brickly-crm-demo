<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseAssignment;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\Project;
use App\Models\User;
use App\Services\KnowledgeBaseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class KnowledgeBaseReportController extends Controller
{
    public function __construct(private readonly KnowledgeBaseProgressService $progressService)
    {
    }

    public function index(Request $request): View
    {
        $categoryId = $request->input('category_id');
        $projectId = $request->input('project_id');

        $assignments = KnowledgeBaseAssignment::query()
            ->with([
                'item.category:id,name',
                'item.project:id,name',
                'user:id,name',
            ])
            ->when($categoryId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('category_id', $categoryId)))
            ->when($projectId, fn ($query) => $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('project_id', $projectId)))
            ->get();

        $progressByKey = \App\Models\KnowledgeBaseProgress::query()
            ->whereIn('knowledge_base_item_id', $assignments->pluck('knowledge_base_item_id')->unique())
            ->whereIn('user_id', $assignments->pluck('user_id')->unique())
            ->get()
            ->keyBy(fn ($progress) => $progress->knowledge_base_item_id . ':' . $progress->user_id);

        $statusCounts = collect([
            'assigned' => $assignments->count(),
            'not_started' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'completed_late' => 0,
        ]);

        $assignments->each(function ($assignment) use ($progressByKey, $statusCounts) {
            $progress = $progressByKey->get($assignment->knowledge_base_item_id . ':' . $assignment->user_id);
            $status = $this->progressService->resolveAssignmentStatus($assignment, $progress);
            $assignment->display_status = $status;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        });

        $userStats = $assignments
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                return [
                    'user' => $group->first()->user,
                    'assigned' => $group->count(),
                    'completed' => $group->where('display_status', 'completed')->count(),
                    'in_progress' => $group->where('display_status', 'in_progress')->count(),
                    'overdue' => $group->where('display_status', 'overdue')->count(),
                    'completed_late' => $group->where('display_status', 'completed_late')->count(),
                ];
            })
            ->sortByDesc('assigned')
            ->values();

        $topicStats = $assignments
            ->groupBy('knowledge_base_item_id')
            ->map(function (Collection $group) {
                return [
                    'item' => $group->first()->item,
                    'assigned' => $group->count(),
                    'completed' => $group->whereIn('display_status', ['completed', 'completed_late'])->count(),
                    'overdue' => $group->where('display_status', 'overdue')->count(),
                ];
            })
            ->sortByDesc('assigned')
            ->values();

        $overdueAssignments = $assignments
            ->where('display_status', 'overdue')
            ->sortBy('due_date')
            ->values();

        $categoryStats = $assignments
            ->groupBy(fn ($assignment) => $assignment->item?->category?->name ?: 'Uncategorized')
            ->map(fn (Collection $group, string $name) => [
                'label' => $name,
                'assigned' => $group->count(),
                'completed' => $group->whereIn('display_status', ['completed', 'completed_late'])->count(),
            ])
            ->values();

        $projectStats = $assignments
            ->groupBy(fn ($assignment) => $assignment->item?->project?->name ?: 'No Project')
            ->map(fn (Collection $group, string $name) => [
                'label' => $name,
                'assigned' => $group->count(),
                'completed' => $group->whereIn('display_status', ['completed', 'completed_late'])->count(),
            ])
            ->values();

        return view('admin.knowledge-base.reports.index', [
            'statusCounts' => $statusCounts,
            'userStats' => $userStats,
            'topicStats' => $topicStats,
            'overdueAssignments' => $overdueAssignments,
            'categoryStats' => $categoryStats,
            'projectStats' => $projectStats,
            'categories' => KnowledgeBaseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedCategoryId' => $categoryId,
            'selectedProjectId' => $projectId,
        ]);
    }
}
