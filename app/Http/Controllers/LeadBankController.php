<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadBankAudit;
use App\Models\LeadTag;
use App\Services\LeadBankAvailabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadBankController extends Controller
{
    public function __construct(private readonly LeadBankAvailabilityService $availabilityService)
    {
    }

    public function index(Request $request)
    {
        $tags = $this->leadTags();

        return view('lead-bank.index', [
            'filters' => ['scope' => 'all'],
            'stats' => $this->stats(),
            'tags' => $tags,
            'folderTags' => $tags->where('is_folder', true)->values(),
            'systemFolders' => $this->systemFolders(),
        ]);
    }

    public function folder(Request $request, string $scope)
    {
        abort_unless(in_array($scope, ['all', 'unassigned'], true), 404);

        $request->merge([
            'scope' => $scope,
            'tag_id' => null,
        ]);

        return $this->inventory($request);
    }

    public function tagFolder(Request $request, LeadTag $tag)
    {
        abort_unless($tag->is_folder, 404);

        $request->merge([
            'scope' => 'all',
            'tag_id' => $tag->id,
        ]);

        return $this->inventory($request);
    }

    private function inventory(Request $request)
    {
        $filters = $request->only(['search', 'city', 'source', 'tag_id', 'availability', 'scope']);
        $filters['scope'] = in_array(($filters['scope'] ?? 'all'), ['all', 'unassigned', 'imported', 'existing', 'untagged'], true)
            ? ($filters['scope'] ?? 'all')
            : 'all';
        $folderFilters = [
            'scope' => $filters['scope'],
            'tag_id' => $filters['tag_id'] ?? null,
        ];
        $query = $this->availabilityService->inventoryQuery();
        $this->availabilityService->applyFilters($query, $filters);

        $leads = $query->paginate(25)->withQueryString();
        $leads->getCollection()->transform(function (Lead $lead) {
            $lead->lead_bank_state = $this->availabilityService->describe($lead);

            return $lead;
        });

        $tags = $this->leadTags();
        $activeFolder = $this->activeFolder($filters, $tags->where('is_folder', true)->values());

        return view('lead-bank.folder-show', [
            'leads' => $leads,
            'filters' => $filters,
            'stats' => $this->stats($folderFilters),
            'activeFolder' => $activeFolder,
            'tags' => $tags,
            'folderTags' => $tags->where('is_folder', true)->values(),
            'systemFolders' => $this->systemFolders(),
            'cities' => Lead::query()->whereNotNull('city')->where('city', '<>', '')->distinct()->orderBy('city')->pluck('city'),
            'sources' => Lead::query()->whereNotNull('source')->where('source', '<>', '')->distinct()->orderBy('source')->pluck('source'),
        ]);
    }

    public function storeTag(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(LeadTag::TYPES)],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_folder' => ['nullable', 'boolean'],
        ]);

        $slug = $this->uniqueTagSlug($data['name']);
        $tag = LeadTag::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'type' => $data['type'],
            'color' => $this->normalizedColor($data['color'] ?? null, $request->boolean('is_folder')),
            'is_folder' => $request->boolean('is_folder'),
            'created_by' => auth()->id(),
        ]);

        $this->audit('tag_created', null, $tag, null, $tag->only(['id', 'name', 'type', 'slug', 'color', 'is_folder']));

        return back()->with('success', 'Lead tag created successfully.');
    }

    public function updateTag(Request $request, LeadTag $tag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(LeadTag::TYPES)],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_folder' => ['nullable', 'boolean'],
        ]);

        $oldValues = $tag->only(['name', 'type', 'color', 'is_folder']);
        $tag->update([
            'name' => trim($data['name']),
            'type' => $data['type'],
            'color' => $this->normalizedColor($data['color'] ?? null, $request->boolean('is_folder')),
            'is_folder' => $request->boolean('is_folder'),
        ]);

        $this->audit('tag_updated', null, $tag, $oldValues, $tag->only(['name', 'type', 'color', 'is_folder']));

        return back()->with('success', 'Lead tag updated successfully.');
    }

    public function destroyTag(LeadTag $tag)
    {
        if ($tag->is_folder) {
            $oldValues = $tag->only(['id', 'name', 'type', 'slug', 'color', 'is_folder']);
            $tag->update(['is_folder' => false]);

            $this->audit('folder_deleted', null, $tag, $oldValues, $tag->only(['id', 'name', 'type', 'slug', 'color', 'is_folder']));

            return redirect()
                ->route('lead-bank.index')
                ->with('success', "Folder '{$tag->name}' removed. Lead tag assignments are safe.");
        }

        $leadCount = $tag->leads()->count();
        if ($leadCount > 0) {
            return back()->with('error', "Tag is assigned to {$leadCount} lead(s). Merge or remove it before deleting.");
        }

        $oldValues = $tag->only(['id', 'name', 'type', 'slug']);
        $tag->delete();
        $this->audit('tag_deleted', null, null, $oldValues, null);

        return back()->with('success', 'Lead tag deleted successfully.');
    }

    public function mergeTags(Request $request)
    {
        $data = $request->validate([
            'source_tag_id' => ['required', 'exists:lead_tags,id'],
            'target_tag_id' => ['required', 'exists:lead_tags,id', 'different:source_tag_id'],
        ]);

        $source = LeadTag::findOrFail($data['source_tag_id']);
        $target = LeadTag::findOrFail($data['target_tag_id']);
        $moved = 0;

        DB::transaction(function () use ($source, $target, &$moved) {
            $source->leads()->chunkById(200, function ($leads) use ($target, &$moved) {
                foreach ($leads as $lead) {
                    $target->leads()->syncWithoutDetaching([
                        $lead->id => ['assigned_by' => auth()->id()],
                    ]);
                    $moved++;
                }
            });

            if ($source->is_folder || $target->is_folder) {
                $target->update([
                    'is_folder' => true,
                    'color' => $target->color ?: ($source->color ?: '#205A44'),
                ]);
            }

            $oldValues = $source->only(['id', 'name', 'type', 'slug', 'color', 'is_folder']);
            $source->delete();
            $this->audit('tag_merged', null, $target, $oldValues, [
                'target_tag_id' => $target->id,
                'target_tag_name' => $target->name,
                'moved_leads' => $moved,
            ]);
        });

        return back()->with('success', "Merged {$moved} lead tag assignment(s) into {$target->name}.");
    }

    public function bulkTags(Request $request)
    {
        $data = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'tag_id' => ['required', 'exists:lead_tags,id'],
            'action' => ['required', Rule::in(['apply', 'remove'])],
        ]);

        $tag = LeadTag::findOrFail($data['tag_id']);
        $leadIds = collect($data['lead_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $count = 0;

        DB::transaction(function () use ($data, $tag, $leadIds, &$count) {
            if ($data['action'] === 'apply') {
                foreach ($leadIds as $leadId) {
                    $tag->leads()->syncWithoutDetaching([
                        $leadId => ['assigned_by' => auth()->id()],
                    ]);
                    $count++;
                }
            } else {
                $count = DB::table('lead_tag_assignments')
                    ->where('lead_tag_id', $tag->id)
                    ->whereIn('lead_id', $leadIds)
                    ->delete();
            }

            $this->audit('bulk_tag_' . $data['action'], null, $tag, null, [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
                'lead_ids' => $leadIds->all(),
                'count' => $count,
            ]);
        });

        $message = $data['action'] === 'apply'
            ? "Applied {$tag->name} to {$count} selected lead(s)."
            : "Removed {$tag->name} from {$count} selected lead(s).";

        return back()->with('success', $message);
    }

    private function stats(array $baseFilters = []): array
    {
        $totalQuery = Lead::query();
        $this->availabilityService->applyFilters($totalQuery, $baseFilters);

        $availableQuery = Lead::query();
        $this->availabilityService->applyFilters($availableQuery, array_merge($baseFilters, ['availability' => 'available']));

        $assignedQuery = Lead::query();
        $this->availabilityService->applyFilters($assignedQuery, $baseFilters);
        $assignedQuery->whereHas('activeAssignments');

        $coolingQuery = Lead::query();
        $this->availabilityService->applyFilters($coolingQuery, $baseFilters);
        $coolingQuery->whereHas('leadBankCooldown', fn (Builder $cooldownQuery) => $cooldownQuery->where('cooldown_until', '>', now()));

        $taggedQuery = Lead::query();
        $this->availabilityService->applyFilters($taggedQuery, $baseFilters);
        $taggedQuery->whereHas('leadTags');

        $blockedQuery = Lead::query();
        $this->availabilityService->applyFilters($blockedQuery, $baseFilters);
        $blockedQuery->where(function (Builder $query) {
            $query->where('is_blocked', true)
                ->orWhere('is_dead', true)
                ->orWhereIn('status', ['dead', 'junk', 'duplicate', 'invalid', 'wrong_number', 'dnd', 'closed_lost']);
        });

        return [
            'total' => $totalQuery->count(),
            'available' => $availableQuery->count(),
            'assigned' => $assignedQuery->count(),
            'cooling' => $coolingQuery->count(),
            'tagged' => $taggedQuery->count(),
            'blocked' => $blockedQuery->count(),
        ];
    }

    private function systemFolders(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Leads', 'icon' => 'fa-layer-group', 'color' => '#205A44', 'count' => Lead::query()->count()],
            ['key' => 'unassigned', 'label' => 'Unassigned Leads', 'icon' => 'fa-user-slash', 'color' => '#64748B', 'count' => Lead::query()->whereDoesntHave('activeAssignments')->count()],
        ];
    }

    private function leadTags()
    {
        return LeadTag::query()
            ->withCount('leads')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'color', 'is_folder']);
    }

    private function activeFolder(array $filters, $folderTags): array
    {
        if (!empty($filters['tag_id'])) {
            $tag = $folderTags->firstWhere('id', (int) $filters['tag_id']);
            if ($tag) {
                return [
                    'tag_id' => $tag->id,
                    'is_custom' => true,
                    'label' => $tag->name,
                    'icon' => 'fa-folder',
                    'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $tag->color) ? $tag->color : '#205A44',
                    'description' => 'Smart folder',
                ];
            }
        }

        $folder = collect($this->systemFolders())->firstWhere('key', $filters['scope'] ?? 'all')
            ?? collect($this->systemFolders())->firstWhere('key', 'all');

        return [
            'tag_id' => null,
            'is_custom' => false,
            'label' => $folder['label'],
            'icon' => $folder['icon'],
            'color' => $folder['color'],
            'description' => 'System folder',
        ];
    }

    private function normalizedColor(?string $color, bool $isFolder): ?string
    {
        $color = strtoupper(trim((string) $color));
        if ($color === '') {
            return $isFolder ? '#205A44' : null;
        }

        return $color;
    }

    private function uniqueTagSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $counter = 2;

        while (LeadTag::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function audit(string $action, ?Lead $lead, mixed $subject, ?array $oldValues, ?array $newValues): void
    {
        LeadBankAudit::create([
            'lead_id' => $lead?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => is_object($subject) && isset($subject->id) ? $subject->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => str_replace('_', ' ', ucfirst($action)),
        ]);
    }
}
