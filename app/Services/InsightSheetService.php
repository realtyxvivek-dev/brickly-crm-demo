<?php

namespace App\Services;

use App\Models\InsightSheetCellOverride;
use App\Models\InsightSheetCellAudit;
use App\Models\CallLog;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\Prospect;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class InsightSheetService
{
    public const SHEET_MASTER = 'master';

    public function __construct(private readonly LeadDisplayStatusResolver $displayStatusResolver)
    {
    }

    private const STATUS_LABELS = [
        'new' => 'New',
        'fresh_transfer' => 'Fresh Transfer',
        'connected' => 'Connected',
        'verified_prospect' => 'Prospect',
        'meeting_scheduled' => 'Mtg Scheduled',
        'meeting_completed' => 'Mtg Done',
        'visit_scheduled' => 'Visit Scheduled',
        'visit_done' => 'Visit Done',
        'revisited_scheduled' => 'Revisit Sched.',
        'revisited_completed' => 'Revisit Done',
        'closed' => 'Closed',
        'dead' => 'Dead',
        'junk' => 'Junk',
        'not_interested' => 'Not Interested',
        'on_hold' => 'On Hold',
        'cnp' => 'CNP',
        'follow_up' => 'Follow Up',
        'cnp_quarantine' => 'CNP Quarantine',
    ];

    public function columns(bool $includeInternalAudit = false): array
    {
        $columns = [
            ['key' => 'serial', 'label' => 'S. NO'],
            ['key' => 'source', 'label' => 'SOURCE'],
            ['key' => 'ad_id_type', 'label' => 'AD ID/TYPE'],
            ['key' => 'date', 'label' => 'DATE'],
            ['key' => 'number', 'label' => 'NUMBER'],
            ['key' => 'customer', 'label' => 'CUSTOMER'],
            ['key' => 'view', 'label' => 'CUSTOMER DETAILS'],
            ['key' => 'all_remarks', 'label' => 'ALL REMARKS'],
            ['key' => 'advisor', 'label' => 'ADVISOR'],
            ['key' => 'alternate_number', 'label' => 'ALTERNATE NUMBER'],
            ['key' => 'shared_link', 'label' => 'SHARED / LINK'],
            ['key' => 'crm_status', 'label' => 'CRM STATUS'],
            ['key' => 'stage', 'label' => 'STAGE'],
            ['key' => 'resident', 'label' => 'RESIDENT'],
            ['key' => 'currently_residing', 'label' => 'CURRENTLY RESIDING'],
            ['key' => 'category', 'label' => 'CATEGORY'],
            ['key' => 'type', 'label' => 'TYPE'],
            ['key' => 'location', 'label' => 'LOCATION'],
            ['key' => 'purpose', 'label' => 'PURPOSE'],
            ['key' => 'budget', 'label' => 'BUDGET'],
            ['key' => 'possession', 'label' => 'POSSESSION'],
            ['key' => 'project', 'label' => 'PROJECT'],
            ['key' => 'fund', 'label' => 'FUND'],
            ['key' => 'customer_profiling', 'label' => 'CUSTOMER PROFILING'],
            ['key' => 'profession', 'label' => 'PROFESSION'],
            ['key' => 'visit_status', 'label' => 'VISIT STATUS'],
            ['key' => 'visit_date', 'label' => 'VISIT DATE'],
            ['key' => 'revisit_date', 'label' => 'REVISIT DATE'],
            ['key' => 'remark_1', 'label' => 'REMARK-1'],
            ['key' => 'follow_up_date_2', 'label' => 'FOLLOW UP DATE-2'],
            ['key' => 'remark_2', 'label' => 'REMARK-2'],
            ['key' => 'follow_up_date_3', 'label' => 'FOLLOW UP DATE-3'],
            ['key' => 'remark_3', 'label' => 'REMARK-3'],
            ['key' => 'follow_up_date_4', 'label' => 'FOLLOW UP DATE-4'],
            ['key' => 'last_remark', 'label' => 'LAST REMARK'],
            ['key' => 'final', 'label' => 'FINAL'],
        ];
        if ($includeInternalAudit) {
            array_splice($columns, 8, 0, [
                ['key' => 'internal_stage', 'label' => 'INTERNAL STAGE'],
                ['key' => 'internal_remark', 'label' => 'INTERNAL REMARK'],
            ]);
        }
        return $columns;
    }

    public function columnKeys(bool $includeInternalAudit = false): array
    {
        return collect($this->columns($includeInternalAudit))->pluck('key')->all();
    }

    public function paginatedRows(Request $request, bool $includeInternalAudit = false): array
    {
        $perPage = min(500, max(25, (int) $request->integer('per_page', 100)));
        $page = max(1, (int) $request->integer('page', 1));
        $query = $this->baseQuery($request);
        $columnFilters = $this->normalizedServerColumnFilters($request);

        $advisorNames = $columnFilters['advisor'] ?? collect();
        $prioritizeFilledRequirements = $advisorNames->isNotEmpty();
        if ($advisorNames->isNotEmpty()) {
            $query->whereHas('activeAssignments.assignedTo', function (Builder $advisorQuery) use ($advisorNames) {
                $advisorQuery->whereIn('users.name', $advisorNames->all());
            });
        }

        if ($this->needsDisplayStatusFiltering($columnFilters)) {
            $crmStatuses = $columnFilters['crm_status'] ?? collect();

            if ($this->canFilterStatusesInDatabase($crmStatuses)) {
                $query->whereIn('leads.status', $crmStatuses->all());
            } else {
                $query->whereIntegerInRaw('leads.id', $this->matchingLeadIds($query, $columnFilters));
            }

            $paginator = $this->paginateRows($query, $perPage, $page, $prioritizeFilledRequirements);
            $this->applyDisplayStatuses($paginator->getCollection());
        } else {
            $paginator = $this->paginateRows($query, $perPage, $page, $prioritizeFilledRequirements);
            $this->applyDisplayStatuses($paginator->getCollection());
        }

        return [
            'columns' => $this->columns($includeInternalAudit),
            'rows' => $this->buildRows($paginator->getCollection(), ($paginator->currentPage() - 1) * $paginator->perPage(), $includeInternalAudit),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => $this->filterOptions(),
        ];
    }

    public function exportRows(Request $request, bool $full, bool $includeInternalAudit = false): Collection
    {
        $query = $this->baseQuery($full ? new Request() : $request);

        if (!$full) {
            $query->limit(min(5000, max(1, (int) $request->integer('limit', 5000))));
        }

        return $this->buildRows($query->latest('leads.created_at')->get(), 0, $includeInternalAudit);
    }

    public function sourceValue(string $rowKey, string $columnKey): ?string
    {
        $leadId = $this->leadIdFromRowKey($rowKey);
        if (!$leadId || !in_array($columnKey, $this->columnKeys(true), true)) {
            return null;
        }

        $lead = $this->leadQuery()->where('leads.id', $leadId)->first();
        if (!$lead) {
            return null;
        }

        $row = $this->sourceValuesForLead($lead, 0);

        return array_key_exists($columnKey, $row) ? (string) $row[$columnKey] : null;
    }

    private function paginateRows(Builder $query, int $perPage, int $page, bool $prioritizeFilledRequirements)
    {
        if ($prioritizeFilledRequirements) {
            $query->orderByDesc('leads.form_filled_by_manager');
        }

        return $query->latest('leads.created_at')->paginate($perPage, ['leads.*'], 'page', $page);
    }

    public function remarkHistory(string $rowKey): array
    {
        $leadId = $this->leadIdFromRowKey($rowKey);
        if (! $leadId) {
            return [];
        }

        $entries = collect();
        $add = function (mixed $value, ?string $actor, mixed $at, string $source) use (&$entries): void {
            $remark = $this->cleanRemarkText((string) $value);
            if (! $this->isDisplayableRemark($remark)) {
                return;
            }

            $entries->push([
                'remark' => $remark,
                'actor' => $actor ?: 'System',
                'source' => $source,
                'recorded_at' => $at ? Carbon::parse($at)->toDateTimeString() : null,
                '_sort' => $at ? Carbon::parse($at)->timestamp : 0,
            ]);
        };

        $lead = Lead::query()->find($leadId);
        foreach ([$lead?->notes, $lead?->verification_notes, $lead?->hr_remark] as $remark) {
            $add($remark, null, $lead?->updated_at, 'Lead note');
        }

        if (Schema::hasTable('follow_ups')) {
        FollowUp::withTrashed()->withQueueHidden()->with('creator:id,name')->where('lead_id', $leadId)->get()
            ->each(fn (FollowUp $item) => $add($item->remarks ?: $item->notes, $item->creator?->name, $item->created_at, 'Follow-up'));
        }
        if (Schema::hasTable('call_logs')) {
        CallLog::withTrashed()->with(['user:id,name', 'telecaller:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (CallLog $item) use ($add): void {
                $actor = $item->user?->name ?: $item->telecaller?->name;
                $at = $item->start_time ?: $item->created_at;
                $add($item->notes, $actor, $at, 'Call');
            });
        }
        if (Schema::hasTable('prospects')) {
        Prospect::with(['createdBy:id,name', 'manager:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (Prospect $item) use ($add): void {
                foreach ([$item->remark, $item->employee_remark, $item->manager_remark, $item->notes] as $remark) {
                    $add($remark, $item->manager?->name ?: $item->createdBy?->name, $item->updated_at, 'Prospect');
                }
            });
        }
        if (Schema::hasTable('meetings')) {
        Meeting::withTrashed()->withoutGlobalScope('visible_in_queue')->with(['creator:id,name', 'assignedTo:id,name', 'verifiedBy:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (Meeting $item) use ($add): void {
                $actor = $item->verifiedBy?->name ?: $item->assignedTo?->name ?: $item->creator?->name;
                foreach ([$item->meeting_notes, $item->feedback, $item->rejection_reason, $item->reschedule_reason] as $remark) {
                    $add($remark, $actor, $item->updated_at, 'Meeting');
                }
            });
        }
        if (Schema::hasTable('tasks')) {
        Task::withTrashed()->withoutGlobalScope('visible_in_queue')->with(['creator:id,name', 'assignedTo:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (Task $item) use ($add): void {
                $actor = $item->assignedTo?->name ?: $item->creator?->name;
                $add($item->outcome_remark, $actor, $item->outcome_recorded_at ?: $item->completed_at ?: $item->updated_at, 'Task');
            });
        }
        if (Schema::hasTable('telecaller_tasks')) {
        TelecallerTask::withTrashed()->withoutGlobalScope('visible_in_queue')->with(['createdBy:id,name', 'assignedTo:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (TelecallerTask $item) use ($add): void {
                $actor = $item->assignedTo?->name ?: $item->createdBy?->name;
                $add($item->notes, $actor, $item->completed_at ?: $item->updated_at, 'Calling task');
            });
        }
        if (Schema::hasTable('site_visits')) {
        SiteVisit::withTrashed()->withoutGlobalScope('visible_in_queue')->with(['creator:id,name', 'assignedTo:id,name', 'verifiedBy:id,name'])->where('lead_id', $leadId)->get()
            ->each(function (SiteVisit $item) use ($add): void {
                $actor = $item->verifiedBy?->name ?: $item->assignedTo?->name ?: $item->creator?->name;
                foreach ([$item->visit_notes, $item->feedback, $item->closer_review_remark, $item->rejection_reason, $item->closer_rejection_reason, $item->closing_rejection_reason, $item->revenue_note] as $remark) {
                    $add($remark, $actor, $item->updated_at, 'Site visit');
                }
            });
        }
        if (Schema::hasTable('lead_assignments')) {
        LeadAssignment::with(['assignedBy:id,name', 'assignedTo:id,name'])->where('lead_id', $leadId)->get()
            ->each(fn (LeadAssignment $item) => $add($item->notes, $item->assignedBy?->name ?: $item->assignedTo?->name, $item->assigned_at ?: $item->created_at, 'Assignment'));
        }

        return $entries->sortByDesc('_sort')->values()->map(function (array $entry) {
            unset($entry['_sort']);
            return $entry;
        })->all();
    }

    public function leadTimeline(string $rowKey): array
    {
        $leadId = $this->leadIdFromRowKey($rowKey);
        $lead = $leadId ? Lead::query()->find($leadId) : null;
        if (! $lead) {
            return [];
        }

        $events = collect();
        $users = Schema::hasTable('users') ? DB::table('users')->pluck('name', 'id') : collect();
        $name = fn ($id) => $id ? ($users[$id] ?? 'User #'.$id) : 'System';
        $add = function (string $title, mixed $at, ?string $description = null, ?string $actor = null, ?string $stage = null, ?string $remark = null, string $type = 'activity') use ($events): void {
            if (! $at) return;
            $date = Carbon::parse($at);
            $events->push([
                'title' => $title,
                'description' => $description,
                'actor' => $actor ?: 'System',
                'stage' => $stage ? ucwords(str_replace('_', ' ', $stage)) : null,
                'remark' => $this->isDisplayableRemark($this->cleanRemarkText((string) $remark)) ? $this->cleanRemarkText((string) $remark) : null,
                'recorded_at' => $date->toDateTimeString(),
                'type' => $type,
                '_sort' => $date->timestamp,
            ]);
        };

        $add('Lead Received', $lead->created_at, 'Source: '.($lead->source ?: 'Unknown'), null, $lead->status, $lead->notes, 'created');

        if (Schema::hasTable('lead_assignments')) {
            DB::table('lead_assignments')->where('lead_id', $leadId)->orderBy('created_at')->get()->each(function ($row) use ($add, $name): void {
                $add('Lead Assigned', $row->assigned_at ?? $row->created_at, 'Assigned to '.$name($row->assigned_to ?? null), $name($row->assigned_by ?? null), null, $row->notes ?? null, 'assignment');
            });
        }
        if (Schema::hasTable('tasks')) {
            DB::table('tasks')->where('lead_id', $leadId)->orderBy('created_at')->get()->each(function ($row) use ($add, $name): void {
                $actor = $name($row->assigned_to ?? $row->created_by ?? null);
                $type = (string) ($row->type ?? 'task');
                $label = ucwords(str_replace('_', ' ', $type));
                $add($label.' Scheduled', $row->created_at, $row->title ?? $row->description ?? null, $name($row->created_by ?? null), $row->status ?? null, $row->notes ?? null, 'task');
                $actionAt = $row->outcome_recorded_at ?? $row->completed_at ?? null;
                if ($actionAt) {
                    $outcome = (string) ($row->outcome ?? $row->status ?? 'completed');
                    $title = strtolower($outcome) === 'cnp' ? 'Call Not Picked (CNP)' : $label.' '.ucwords(str_replace('_', ' ', $outcome));
                    $add($title, $actionAt, $row->description ?? null, $actor, $outcome, $row->outcome_remark ?? $row->notes ?? null, 'task');
                }
            });
        }
        if (Schema::hasTable('call_logs')) {
            DB::table('call_logs')->where('lead_id', $leadId)->orderBy('created_at')->get()->each(function ($row) use ($add, $name): void {
                $outcome = strtolower((string) ($row->call_outcome ?? $row->status ?? ''));
                $cnp = in_array($outcome, ['cnp', 'no_answer', 'missed', 'busy', 'rejected'], true);
                $title = $cnp ? 'Call Not Picked (CNP)' : ucwords((string) ($row->call_type ?? 'Call')).' Call';
                $description = filled($row->duration ?? null) ? 'Duration: '.$row->duration.' seconds' : null;
                $add($title, $row->start_time ?? $row->created_at, $description, $name($row->user_id ?? $row->telecaller_id ?? null), $outcome, $row->notes ?? null, 'call');
            });
        }
        if (Schema::hasTable('follow_ups')) {
            DB::table('follow_ups')->where('lead_id', $leadId)->orderBy('created_at')->get()->each(function ($row) use ($add, $name): void {
                $when = $row->follow_up_date ?? $row->scheduled_at ?? null;
                $description = $when ? 'Next action: '.Carbon::parse($when)->toDateTimeString() : null;
                $add('Follow Up '.ucwords((string) ($row->status ?? 'Scheduled')), $row->created_at, $description, $name($row->created_by ?? null), $row->status ?? 'follow_up', $row->remarks ?? $row->notes ?? null, 'follow_up');
            });
        }
        foreach (['meetings' => 'Meeting', 'site_visits' => 'Site Visit'] as $table => $label) {
            if (! Schema::hasTable($table)) continue;
            DB::table($table)->where('lead_id', $leadId)->orderBy('created_at')->get()->each(function ($row) use ($add, $name, $label): void {
                $project = $row->visited_projects ?? $row->project ?? $row->property_name ?? null;
                $add($label.' Scheduled', $row->created_at, $project ? 'Project: '.$project : null, $name($row->created_by ?? null), $row->status ?? null, $row->meeting_notes ?? $row->visit_notes ?? null, strtolower(str_replace(' ', '_', $label)));
                if (! empty($row->completed_at)) {
                    $add($label.' Completed', $row->completed_at, $project ? 'Project: '.$project : null, $name($row->assigned_to ?? $row->created_by ?? null), 'completed', $row->feedback ?? $row->meeting_notes ?? $row->visit_notes ?? null, strtolower(str_replace(' ', '_', $label)));
                }
            });
        }
        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')->where('model_id', $leadId)->whereIn('model_type', ['Lead', Lead::class])->orderBy('created_at')->get()->each(function ($row) use ($add, $name): void {
                $old = json_decode((string) ($row->old_values ?? ''), true) ?: [];
                $new = json_decode((string) ($row->new_values ?? ''), true) ?: [];
                $stage = $new['status'] ?? null;
                $description = $row->description ?? null;
                if (($old['status'] ?? null) && $stage) $description = ucwords(str_replace('_', ' ', $old['status'])).' to '.ucwords(str_replace('_', ' ', $stage));
                $add(ucwords(str_replace('_', ' ', (string) ($row->action ?? 'Activity'))), $row->created_at, $description, $name($row->user_id ?? null), $stage, $new['remark'] ?? $new['notes'] ?? null, 'status');
            });
        }
        if (Schema::hasTable('insight_sheet_cell_audits')) {
            InsightSheetCellAudit::query()->with('editor:id,name')->where('sheet_key', self::SHEET_MASTER)->where('row_key', $rowKey)->where('column_key', 'customer_details')->get()->each(function ($audit) use ($add): void {
                $add('Customer Details Overwritten', $audit->edited_at ?: $audit->created_at, 'Internal audit override updated', $audit->editor?->name, 'Auditor Override', null, 'audit');
            });
        }

        return $events->unique(fn (array $event) => implode('|', [$event['title'], $event['recorded_at'], $event['remark']]))
            ->sortByDesc('_sort')->values()->map(function (array $event) { unset($event['_sort']); return $event; })->all();
    }

    public function leadDetails(string $rowKey): ?array
    {
        $leadId = $this->leadIdFromRowKey($rowKey);
        if (! $leadId) {
            return null;
        }

        $lead = $this->leadQuery()->where('leads.id', $leadId)->first();
        if (! $lead) {
            return null;
        }

        $source = $this->sourceValuesForLead($lead, 0);
        $displayValue = function (mixed $value): ?string {
            if ($value === null || trim((string) $value) === '' || in_array(trim((string) $value), ['[]', '{}', 'null'], true)) {
                return null;
            }

            if (is_string($value) && in_array(substr(trim($value), 0, 1), ['[', '{'], true)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $values = collect($decoded)->map(function ($item, $key): string {
                        $text = is_array($item) ? implode(', ', array_filter(array_map('strval', $item))) : (string) $item;
                        return is_string($key) ? ucwords(str_replace('_', ' ', $key)).': '.$text : $text;
                    })->filter()->implode(', ');

                    return $values !== '' ? $values : null;
                }
            }

            return trim((string) $value);
        };
        $formValues = $lead->formFieldValues->pluck('field_value', 'field_key')->map($displayValue);
        $latestProspect = $lead->prospects->sortByDesc('updated_at')->first();
        $profile = [
            'Job / Profession' => $formValues->get('customer_job'),
            'Industry' => $formValues->get('industry_sector'),
            'Buying Preference' => $formValues->get('buying_frequency'),
            'Current City' => $formValues->get('living_city') ?: $lead->city,
            'City Type' => $formValues->get('city_type'),
            'Resident' => $source['resident'],
            'Lead Quality' => $formValues->get('lead_quality') ?: $latestProspect?->lead_score,
            'Lead Temperature' => $formValues->get('lead_status') ?: $latestProspect?->lead_status,
        ];
        $requirements = [
            'Category' => $source['category'] ?: $formValues->get('category'),
            'Type' => $source['type'] ?: $formValues->get('type') ?: $lead->property_type,
            'Preferred Location' => $source['location'] ?: $formValues->get('preferred_location') ?: $lead->preferred_location ?: $latestProspect?->preferred_location,
            'Purpose' => $source['purpose'] ?: $formValues->get('purpose') ?: $lead->use_end_use ?: $latestProspect?->purpose,
            'Budget' => $source['budget'] ?: $formValues->get('budget') ?: $lead->budget ?: $latestProspect?->budget,
            'Possession' => $source['possession'] ?: $formValues->get('possession') ?: $lead->possession_status ?: $latestProspect?->possession,
            'Preferred Size' => $formValues->get('preferred_size') ?: $lead->preferred_size ?: $latestProspect?->size,
            'Project' => $source['project'] ?: $formValues->get('interested_projects') ?: $lead->preferred_projects,
            'Fund / Payment Mode' => $source['fund'] ?: $formValues->get('payment_mode'),
            'Other Requirements' => $lead->requirements,
        ];
        $knownKeys = collect([
            'customer_job', 'industry_sector', 'buying_frequency', 'living_city', 'city_type', 'resident', 'lead_quality',
            'category', 'type', 'property_type', 'preferred_location', 'purpose', 'budget', 'possession', 'preferred_size',
            'interested_projects', 'payment_mode', 'fund', 'lead_status', 'manager_remark',
        ]);
        $additional = $formValues->reject(fn ($value, $key) => blank($value) || $knownKeys->contains($key))
            ->mapWithKeys(fn ($value, $key) => [ucwords(str_replace('_', ' ', (string) $key)) => $value])
            ->all();

        $latestTask = Schema::hasTable('tasks')
            ? Task::withTrashed()->withoutGlobalScope('visible_in_queue')
                ->with(['creator:id,name', 'assignedTo:id,name'])
                ->where('lead_id', $lead->id)
                ->orderByDesc(DB::raw('COALESCE(outcome_recorded_at, completed_at, updated_at)'))
                ->first()
            : null;
        $salesUpdate = $latestTask ? [
            'Handled By' => $latestTask->assignedTo?->name ?: $latestTask->creator?->name,
            'Outcome' => $latestTask->outcome ? ucwords(str_replace('_', ' ', $latestTask->outcome)) : null,
            'Remark' => $latestTask->outcome_remark ?: $latestTask->notes,
            'Manager Remark' => $formValues->get('manager_remark') ?: $latestProspect?->manager_remark,
            'Task Status' => $latestTask->status ? ucwords(str_replace('_', ' ', $latestTask->status)) : null,
            'Completed At' => $latestTask->outcome_recorded_at?->toDateTimeString() ?: $latestTask->completed_at?->toDateTimeString(),
            'Next Action' => $latestTask->next_action_at?->toDateTimeString() ?: $latestTask->scheduled_at?->toDateTimeString(),
        ] : [
            'Manager Remark' => $formValues->get('manager_remark') ?: $latestProspect?->manager_remark,
        ];

        $contact = [
            'Phone' => $source['number'],
            'Alternate Number' => $source['alternate_number'],
            'Email' => $lead->email,
            'Address' => $lead->address,
            'City' => $lead->city,
            'State' => $lead->state,
            'Pincode' => $lead->pincode,
            'Source' => $source['source'],
        ];
        $editableSources = [
            'customer' => ['group' => 'Contact', 'label' => 'Customer Name', 'value' => $source['customer']],
            'phone' => ['group' => 'Contact', 'label' => 'Phone', 'value' => $source['number']],
            'alternate_number' => ['group' => 'Contact', 'label' => 'Alternate Number', 'value' => $source['alternate_number']],
            'email' => ['group' => 'Contact', 'label' => 'Email', 'value' => $lead->email],
            'address' => ['group' => 'Contact', 'label' => 'Address', 'value' => $lead->address],
            'city' => ['group' => 'Contact', 'label' => 'City', 'value' => $lead->city],
            'state' => ['group' => 'Contact', 'label' => 'State', 'value' => $lead->state],
            'pincode' => ['group' => 'Contact', 'label' => 'Pincode', 'value' => $lead->pincode],
            'customer_job' => ['group' => 'Customer Profiling', 'label' => 'Job / Profession', 'value' => $profile['Job / Profession']],
            'industry_sector' => ['group' => 'Customer Profiling', 'label' => 'Industry', 'value' => $profile['Industry']],
            'buying_frequency' => ['group' => 'Customer Profiling', 'label' => 'Buying Preference', 'value' => $profile['Buying Preference']],
            'living_city' => ['group' => 'Customer Profiling', 'label' => 'Current City', 'value' => $profile['Current City']],
            'city_type' => ['group' => 'Customer Profiling', 'label' => 'City Type', 'value' => $profile['City Type']],
            'resident' => ['group' => 'Customer Profiling', 'label' => 'Resident', 'value' => $profile['Resident']],
            'lead_quality' => ['group' => 'Customer Profiling', 'label' => 'Lead Quality', 'value' => $profile['Lead Quality']],
            'lead_status' => ['group' => 'Customer Profiling', 'label' => 'Lead Temperature', 'value' => $profile['Lead Temperature']],
            'category' => ['group' => 'Requirements', 'label' => 'Category', 'value' => $requirements['Category']],
            'type' => ['group' => 'Requirements', 'label' => 'Type', 'value' => $requirements['Type']],
            'preferred_location' => ['group' => 'Requirements', 'label' => 'Preferred Location', 'value' => $requirements['Preferred Location']],
            'purpose' => ['group' => 'Requirements', 'label' => 'Purpose', 'value' => $requirements['Purpose']],
            'budget' => ['group' => 'Requirements', 'label' => 'Budget', 'value' => $requirements['Budget']],
            'possession' => ['group' => 'Requirements', 'label' => 'Possession', 'value' => $requirements['Possession']],
            'preferred_size' => ['group' => 'Requirements', 'label' => 'Preferred Size', 'value' => $requirements['Preferred Size']],
            'project' => ['group' => 'Requirements', 'label' => 'Interested Projects', 'value' => $requirements['Project']],
            'fund' => ['group' => 'Requirements', 'label' => 'Fund / Payment Mode', 'value' => $requirements['Fund / Payment Mode']],
            'other_requirements' => ['group' => 'Requirements', 'label' => 'Other Requirements', 'value' => $requirements['Other Requirements']],
            'manager_remark' => ['group' => 'Sales Update', 'label' => 'Manager Remark', 'value' => $salesUpdate['Manager Remark'] ?? null],
        ];
        $detailOverride = InsightSheetCellOverride::query()
            ->where('sheet_key', self::SHEET_MASTER)
            ->where('row_key', $rowKey)
            ->where('column_key', 'customer_details')
            ->first();
        $overrideValues = json_decode((string) ($detailOverride?->value ?? ''), true);
        $overrideValues = is_array($overrideValues) ? $overrideValues : [];
        $editableFields = collect($editableSources)->map(function (array $definition, string $key) use ($overrideValues, $displayValue): array {
            $hasOverride = array_key_exists($key, $overrideValues);
            $value = $hasOverride ? $displayValue($overrideValues[$key]) : $displayValue($definition['value']);
            if ($key === 'manager_remark') {
                $value = $this->cleanRemarkText((string) $value);
                $value = $this->isDisplayableRemark($value) ? $value : null;
            }

            return [
                'key' => $key,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'source_value' => $displayValue($definition['value']),
                'value' => $value,
                'is_overridden' => $hasOverride,
            ];
        })->values();
        foreach ($editableFields as $field) {
            if ($field['group'] === 'Contact') {
                $contact[$field['label']] = $field['value'];
            } elseif ($field['group'] === 'Customer Profiling') {
                $profile[$field['label']] = $field['value'];
            } elseif ($field['group'] === 'Requirements') {
                $requirements[$field['label']] = $field['value'];
            } else {
                $salesUpdate[$field['label']] = $field['value'];
            }
        }
        $effectiveCustomer = $editableFields->firstWhere('key', 'customer')['value'] ?? $source['customer'];
        $salesUpdate = collect($salesUpdate)->map(function ($value, string $label) {
            if (! in_array($label, ['Remark', 'Manager Remark'], true)) {
                return $value;
            }

            $remark = $this->cleanRemarkText((string) $value);

            return $this->isDisplayableRemark($remark) ? $remark : null;
        })->filter(fn ($value) => filled($value))->all();

        return [
            'lead_id' => $lead->id,
            'customer' => $effectiveCustomer,
            'phone' => $editableFields->firstWhere('key', 'phone')['value'] ?? $source['number'],
            'email' => $lead->email,
            'source' => $source['source'],
            'advisor' => $source['advisor'],
            'status' => $source['crm_status'],
            'contact' => collect($contact)->filter(fn ($value) => filled($value))->all(),
            'profile' => collect($profile)->map(fn ($value) => filled($value) ? $value : 'Not saved')->all(),
            'requirements' => collect($requirements)->map(fn ($value) => filled($value) ? $value : 'Not saved')->all(),
            'additional' => $additional,
            'sales_update' => $salesUpdate,
            'latest_remark' => $source['last_remark'],
            'remark_history' => $this->remarkHistory($rowKey),
            'timeline' => $this->leadTimeline($rowKey),
            'editable_fields' => $editableFields->all(),
            'editor_options' => $this->leadRequirementEditorOptions(),
            'editor_type_options' => $this->leadRequirementTypeOptions(),
            'has_detail_override' => $detailOverride !== null,
        ];
    }

    private function leadRequirementEditorOptions(): array
    {
        $defaults = [
            'category' => ['Residential', 'Commercial', 'Both', 'N.A'],
            'preferred_location' => ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'],
            'budget' => ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'],
            'purpose' => ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'],
            'possession' => ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'],
            'lead_status' => ['hot', 'warm', 'cold', 'junk'],
            'lead_quality' => ['1', '2', '3', '4', '5'],
            'project' => ['Jashn Elevate', 'Oro Constella', 'Skyom City'],
            'industry_sector' => ['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other'],
            'buying_frequency' => ['Regular', 'Occasional', 'First-time'],
            'city_type' => ['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident'],
        ];

        if (! Schema::hasTable('dynamic_forms') || ! Schema::hasTable('dynamic_form_fields')) {
            $defaults['type'] = collect($this->leadRequirementTypeOptions())->flatten()->unique()->values()->all();

            return $defaults;
        }

        $form = app(DynamicFormService::class)->getPublishedFormByLocation('lead-detail.requirements');
        $fieldOptions = function (string $key, array $fallback) use ($form): array {
            $options = $form?->fields?->firstWhere('field_key', $key)?->options;

            return collect(is_array($options) && count($options) ? $options : $fallback)
                ->map(fn ($option) => is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : (string) $option)
                ->map(fn ($option) => trim((string) $option))
                ->filter()
                ->unique()
                ->values()
                ->all();
        };

        foreach (array_keys($defaults) as $key) {
            $formKey = $key === 'project' ? 'interested_projects' : $key;
            $defaults[$key] = $fieldOptions($formKey, $defaults[$key]);
        }
        $defaults['type'] = collect($this->leadRequirementTypeOptions())->flatten()->unique()->values()->all();

        return $defaults;
    }

    private function leadRequirementTypeOptions(): array
    {
        return [
            'Residential' => ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
            'Commercial' => ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
            'Both' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
            'N.A' => ['N.A'],
        ];
    }

    public function completionDetails(string $rowKey): ?array
    {
        $leadId = $this->leadIdFromRowKey($rowKey);
        $lead = $leadId ? Lead::query()->find($leadId) : null;
        if (! $lead) {
            return null;
        }

        $activities = collect();

        SiteVisit::withTrashed()->withoutGlobalScope('visible_in_queue')
            ->with(['creator:id,name', 'assignedTo:id,name'])
            ->where('lead_id', $leadId)
            ->where('status', 'completed')
            ->get()
            ->each(function (SiteVisit $visit) use ($activities): void {
                $propertyTypes = collect($visit->visited_property_types ?? [])->filter()->map(
                    fn ($value) => ucwords(str_replace('_', ' ', (string) $value))
                )->implode(', ');
                $fields = collect([
                    'Visited Projects' => $visit->visited_projects ?: $visit->project ?: $visit->property_name,
                    'Property Types' => $propertyTypes ?: $visit->property_type,
                    'Tentative Closing' => $visit->tentative_closing_time
                        ? ucwords(str_replace('_', ' ', $visit->tentative_closing_time))
                        : null,
                    'Feedback' => $visit->feedback,
                    'Rating' => $visit->rating ? $visit->rating.'/5' : null,
                    'Visit Notes' => $visit->visit_notes,
                    'Verification' => $visit->verification_status
                        ? ucwords(str_replace('_', ' ', $visit->verification_status))
                        : null,
                ])->filter(fn ($value) => filled($value))->all();

                $activities->push([
                    'type' => 'visit',
                    'title' => strcasecmp((string) $visit->lead_type, 'Revisited') === 0 ? 'Revisit Completed' : 'Site Visit Completed',
                    'completed_at' => $visit->completed_at?->toDateTimeString(),
                    'completed_by' => $visit->assignedTo?->name ?: $visit->creator?->name ?: 'Sales user',
                    'fields' => $fields,
                    'proof_photos' => $this->completionPhotoUrls($visit->completion_proof_photos),
                    '_sort' => $visit->completed_at?->timestamp ?? 0,
                ]);
            });

        Meeting::withTrashed()->withoutGlobalScope('visible_in_queue')
            ->with(['creator:id,name', 'assignedTo:id,name'])
            ->where('lead_id', $leadId)
            ->where('status', 'completed')
            ->get()
            ->each(function (Meeting $meeting) use ($activities): void {
                $fields = collect([
                    'Project' => $meeting->project,
                    'Meeting Mode' => $meeting->meeting_mode
                        ? ucwords(str_replace('_', ' ', $meeting->meeting_mode))
                        : null,
                    'Location' => $meeting->location,
                    'Feedback' => $meeting->feedback,
                    'Rating' => $meeting->rating ? $meeting->rating.'/5' : null,
                    'Meeting Notes' => $meeting->meeting_notes,
                    'Verification' => $meeting->verification_status
                        ? ucwords(str_replace('_', ' ', $meeting->verification_status))
                        : null,
                ])->filter(fn ($value) => filled($value))->all();

                $activities->push([
                    'type' => 'meeting',
                    'title' => 'Meeting Completed',
                    'completed_at' => $meeting->completed_at?->toDateTimeString(),
                    'completed_by' => $meeting->assignedTo?->name ?: $meeting->creator?->name ?: 'Sales user',
                    'fields' => $fields,
                    'proof_photos' => $this->completionPhotoUrls($meeting->completion_proof_photos),
                    '_sort' => $meeting->completed_at?->timestamp ?? 0,
                ]);
            });

        return [
            'lead_id' => $lead->id,
            'customer' => $lead->name ?: 'Lead',
            'activities' => $activities->sortByDesc('_sort')->values()->map(function (array $activity) {
                unset($activity['_sort']);
                return $activity;
            })->all(),
        ];
    }

    private function completionPhotoUrls(mixed $photos): array
    {
        return collect(is_array($photos) ? $photos : [])->filter()->map(function ($path): string {
            $path = (string) $path;
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            $path = preg_replace('#^(public/|storage/)#', '', ltrim($path, '/'));
            return asset('storage/'.$path);
        })->values()->all();
    }

    private function baseQuery(Request $request): Builder
    {
        $query = $this->leadQuery()->visibleInAllLeadsInventory();

        if ($search = trim((string) $request->input('search', ''))) {
            $phoneDigits = preg_replace('/\D+/', '', $search);
            $hasNormalizedPhone = $phoneDigits !== '' && Schema::hasColumn('leads', 'normalized_phone');
            $query->where(function (Builder $searchQuery) use ($search, $phoneDigits, $hasNormalizedPhone) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                if ($hasNormalizedPhone) {
                    $searchQuery->orWhere('normalized_phone', 'like', "%{$phoneDigits}%");
                }
            });
        }

        if ($source = trim((string) $request->input('source', ''))) {
            $query->where('source', Lead::normalizeSource($source));
        }

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($advisor = trim((string) $request->input('advisor', ''))) {
            app(LeadAuditorAccessService::class)->authorizeUser((int) $advisor);
            $query->whereHas('activeAssignments', function (Builder $assignmentQuery) use ($advisor) {
                $assignmentQuery->where('assigned_to', $advisor)->where('is_active', true);
            });
        }

        [$start, $end] = $this->resolveDateRange($request);
        if ($start && $end) {
            $query->whereBetween('leads.created_at', [$start, $end]);
        }

        return $query;
    }

    private function leadQuery(): Builder
    {
        return app(LeadAuditorAccessService::class)->scopeLeads(Lead::query())->with([
            'formFieldValues:lead_id,field_key,field_value',
            'activeAssignments.assignedTo',
            'currentAssignment.assignedTo',
            'latestAssignment.assignedTo',
            'prospects.interestedProjects:id,name',
            'siteVisits' => fn ($query) => $query->latest('updated_at'),
            'followUps' => fn ($query) => $query->latest('created_at'),
        ]);
    }

    private function buildRows(Collection $leads, int $offset, bool $includeInternalAudit = false): Collection
    {
        $rowKeys = $leads->map(fn (Lead $lead) => $this->rowKey($lead))->values();
        $overrides = InsightSheetCellOverride::query()
            ->with('editor:id,name')
            ->where('sheet_key', self::SHEET_MASTER)
            ->whereIn('row_key', $rowKeys)
            ->get()
            ->groupBy('row_key');

        return $leads->values()->map(function (Lead $lead, int $index) use ($offset, $overrides, $includeInternalAudit) {
            $rowKey = $this->rowKey($lead);
            $source = $this->sourceValuesForLead($lead, $offset + $index + 1);
            $cells = [];

            foreach ($this->columnKeys($includeInternalAudit) as $columnKey) {
                $override = $overrides->get($rowKey, collect())->firstWhere('column_key', $columnKey);
                $sourceValue = (string) ($source[$columnKey] ?? '');
                if (in_array($columnKey, ['last_remark', 'customer_profiling'], true) && $override && !$this->isDisplayableRemark((string) $override->value)) {
                    $override = null;
                }
                $cells[$columnKey] = [
                    'value' => $override ? (string) $override->value : $sourceValue,
                    'source_value' => $sourceValue,
                    'is_overridden' => (bool) $override,
                    'edited_by' => $override?->edited_by,
                    'edited_by_name' => $override?->editor?->name,
                    'edited_at' => $override?->updated_at?->toDateTimeString(),
                ];
            }

            return [
                'row_key' => $rowKey,
                'lead_id' => $lead->id,
                'completion_type' => match ($this->displayStatusForLead($lead)) {
                    'meeting_completed' => 'meeting',
                    'visit_done', 'revisited_completed' => 'visit',
                    default => null,
                },
                'cells' => $cells,
            ];
        });
    }

    private function sourceValuesForLead(Lead $lead, int $serial): array
    {
        $displayStatus = $this->displayStatusForLead($lead);
        $formValues = $lead->relationLoaded('formFieldValues')
            ? $lead->formFieldValues->pluck('field_value', 'field_key')
            : collect();
        $latestVisit = $lead->siteVisits->first();
        $latestProspect = $lead->prospects->sortByDesc('updated_at')->first();
        $latestFollowUp = $lead->followUps->first();
        $lastRemark = $this->firstDisplayableRemark([
            $latestFollowUp?->remarks ?? null,
            $latestFollowUp?->notes ?? null,
            $latestProspect?->manager_remark ?? null,
            $latestProspect?->employee_remark ?? null,
            $latestProspect?->remark ?? null,
            $lead->notes ?? null,
        ]);
        $advisor = app(LeadAuditorAccessService::class)->restricted() ? ($lead->currentAssignment?->assignedTo?->name ?? 'Unassigned') : ($lead->activeAssignments->first()?->assignedTo?->name
            ?? $lead->latestAssignment?->assignedTo?->name
            ?? 'Unassigned');

        $pick = function (array $keys) use ($lead, $formValues): string {
            foreach ($keys as $key) {
                $value = $lead->{$key} ?? $formValues->get($key);
                if ($value !== null && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }

            return '';
        };
        $withProspectFallback = function (array $keys, $prospectValue) use ($pick): string {
            return $pick($keys) ?: trim((string) ($prospectValue ?? ''));
        };
        $prospectProjects = $latestProspect?->interestedProjects
            ?->pluck('name')
            ->filter()
            ->implode(', ') ?? '';
        $customerProfiling = array_filter([
            $pick(['customer_job']) ? 'Job: '.$pick(['customer_job']) : '',
            $pick(['industry_sector']) ? 'Industry: '.$pick(['industry_sector']) : '',
            $pick(['buying_frequency']) ? 'Buying: '.$pick(['buying_frequency']) : '',
            $pick(['city_type']) ? 'City: '.$pick(['city_type']) : '',
        ]);

        return [
            'serial' => $serial ? (string) $serial : '',
            'source' => Lead::displaySourceLabel((string) ($lead->source ?? '')),
            'ad_id_type' => $pick(['ad_id_type', 'ad_id', 'form_name']),
            'date' => $lead->created_at?->format('Y-m-d') ?? '',
            'number' => (string) ($lead->phone ?? ''),
            'customer' => (string) ($lead->name ?? ''),
            'view' => '',
            'all_remarks' => '',
            'internal_stage' => '',
            'internal_remark' => '',
            'advisor' => $advisor,
            'alternate_number' => $pick(['alternate_phone', 'secondary_phone', 'alternate_number']),
            'shared_link' => $pick(['shared_link', 'channel']),
            'crm_status' => $this->statusLabel($displayStatus),
            'stage' => $pick(['stage']) ?: ucfirst(str_replace('_', ' ', (string) ($latestProspect?->verification_status ?? ''))),
            'resident' => $pick(['resident']),
            'currently_residing' => $pick(['currently_residing', 'living_city', 'city']),
            'category' => $pick(['category', 'property_category']) ?: $this->categoryForType($pick(['property_type', 'type', 'apartment_type'])),
            'type' => $pick(['property_type', 'type', 'apartment_type']),
            'location' => $withProspectFallback(['preferred_location', 'location'], $latestProspect?->preferred_location),
            'purpose' => $withProspectFallback(['purpose', 'buying_purpose', 'use_end_use', 'investment'], $latestProspect?->purpose),
            'budget' => $withProspectFallback(['budget', 'budget_range', 'apartment_budget'], $latestProspect?->budget),
            'possession' => $withProspectFallback(['possession', 'possession_status'], $latestProspect?->possession),
            'project' => $latestVisit?->project ?: $this->displayProjectValue($pick(['project', 'interested_project', 'interested_projects', 'preferred_projects'])) ?: $prospectProjects,
            'fund' => $pick(['fund', 'payment_mode']),
            'customer_profiling' => $pick(['customer_profiling', 'profile']) ?: implode(' | ', $customerProfiling),
            'profession' => $pick(['customer_job', 'profession', 'occupation']),
            'visit_status' => $latestVisit ? strtoupper(str_replace('_', ' ', (string) $latestVisit->status)) : '',
            'visit_date' => $this->formatDate($latestVisit?->date_of_visit ?? $latestVisit?->scheduled_at),
            'revisit_date' => $latestVisit?->visit_sequence === 'revisit' ? $this->formatDate($latestVisit?->date_of_visit ?? $latestVisit?->scheduled_at) : '',
            'remark_1' => $this->firstDisplayableRemark([
                $latestProspect?->manager_remark ?? null,
                $lead->manager_remark ?? null,
                $lead->remark ?? null,
            ], ''),
            'follow_up_date_2' => $this->formatDate($latestFollowUp?->follow_up_date ?? $latestFollowUp?->scheduled_at ?? null),
            'remark_2' => $this->firstDisplayableRemark([
                $latestFollowUp?->remarks ?? null,
                $latestFollowUp?->notes ?? null,
            ], ''),
            'follow_up_date_3' => '',
            'remark_3' => $this->firstDisplayableRemark([
                $latestProspect?->employee_remark ?? null,
                $latestProspect?->remark ?? null,
            ], ''),
            'follow_up_date_4' => '',
            'last_remark' => $lastRemark,
            'final' => $this->finalLabel($lead),
        ];
    }

    private function firstDisplayableRemark(array $values, string $fallback = 'No remark'): string
    {
        foreach ($values as $value) {
            $remark = $this->cleanRemarkText((string) $value);
            if ($this->isDisplayableRemark($remark)) {
                return $remark;
            }
        }

        return $fallback;
    }

    private function displayProjectValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !str_starts_with($value, '[')) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $value;
        }

        $names = collect($decoded)
            ->map(fn ($project) => is_array($project) ? ($project['name'] ?? '') : (string) $project)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $names;
    }

    private function categoryForType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return '';
        }

        if (str_contains($type, 'commercial') || str_contains($type, 'shop') || str_contains($type, 'office')) {
            return 'Commercial';
        }

        if (str_contains($type, 'apartment') || str_contains($type, 'villa') || str_contains($type, 'plot') || str_contains($type, 'residential')) {
            return 'Residential';
        }

        return '';
    }

    private function isDisplayableRemark(string $remark): bool
    {
        if ($remark === '') {
            return false;
        }

        $lower = strtolower($remark);
        if (in_array($remark, ['-', '--', '—', 'â€”', 'Ã¢â‚¬â€'], true)
            || in_array($lower, ['n/a', 'na', 'null', 'cnp', 'follow_up', 'follow up', 'interested', 'completed', 'pending'], true)) {
            return false;
        }

        return ! $this->isSystemGeneratedRemark($lower)
            && ! preg_match('/^[a-z0-9_]+:\s*/i', $remark);
    }

    private function isSystemGeneratedRemark(string $remark): bool
    {
        foreach ([
            'inbox_url',
            'business.facebook.com',
            'apartment_budget',
            'apartment_type',
            'asm fresh lead cnp automation',
            'asm outcome:',
            'mcube non-answered call captured',
            'auto-created from inbound whatsapp',
            'base crm android app',
            'synced automatically from base crm',
            'recording uploaded from android app',
            'recording unavailable from android app',
            'call not picked (auto cnp) handled',
            'follow-up call task scheduled for',
            'follow up scheduled for',
            'follow-up scheduled for',
            'auto-created cnp retry task',
        ] as $pattern) {
            if (str_contains(strtolower($remark), $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function cleanRemarkText(string $value): string
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($value)) ?: [])
            ->map(function ($line) {
                $line = trim((string) $line);
                $line = preg_replace('/^\[\d{4}-\d{2}-\d{2}[^\]]*\]\s*/', '', $line) ?? $line;
                $line = preg_replace('/^ASM\s+outcome:\s*/i', '', $line) ?? $line;
                $line = preg_replace('/\s*\|\s*Follow-?up scheduled for.*$/i', '', $line) ?? $line;

                return trim($line);
            })
            ->filter(fn (string $line) => $line !== '' && ! $this->isSystemGeneratedRemark($line))
            ->values();

        return (string) ($lines->last() ?? '');
    }

    private function filterOptions(): array
    {
        $advisors = DB::table('users')
            ->when(app(LeadAuditorAccessService::class)->restricted(), fn ($q) => app(LeadAuditorAccessService::class)->scopeUsers($q))
            ->join('lead_assignments', 'lead_assignments.assigned_to', '=', 'users.id')
            ->where('lead_assignments.is_active', true)
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get();

        return [
            'sources' => Lead::sourceOptions(),
            'statuses' => self::STATUS_LABELS,
            'column_values' => [
                'crm_status' => array_values(self::STATUS_LABELS),
                'advisor' => $advisors->pluck('name')->filter()->unique()->values()->all(),
            ],
            'column_value_keys' => [
                'crm_status' => array_flip(self::STATUS_LABELS),
            ],
            'advisors' => $advisors
                ->map(fn ($user) => ['id' => (string) $user->id, 'name' => (string) $user->name])
                ->all(),
        ];
    }

    private function normalizedServerColumnFilters(Request $request): array
    {
        $columnFilters = $request->input('column_filters', []);

        return [
            'crm_status' => collect($columnFilters['crm_status'] ?? [])
                ->map(fn ($status) => $this->statusKeyForLabel((string) $status))
                ->filter()
                ->unique()
                ->values(),
            'advisor' => collect($columnFilters['advisor'] ?? [])
                ->map(fn ($advisor) => trim((string) $advisor))
                ->filter()
                ->unique()
                ->values(),
        ];
    }

    private function needsDisplayStatusFiltering(array $columnFilters): bool
    {
        return collect($columnFilters)->contains(fn (Collection $values) => $values->isNotEmpty());
    }

    private function canFilterStatusesInDatabase(Collection $statuses): bool
    {
        return $statuses->isNotEmpty()
            && $statuses->intersect(['new', 'cnp', 'follow_up'])->isEmpty();
    }

    private function matchingLeadIds(Builder $query, array $columnFilters): array
    {
        $matchingIds = [];

        (clone $query)->select('leads.*')->chunkById(100, function (Collection $leads) use (&$matchingIds, $columnFilters) {
            $this->applyDisplayStatuses($leads);
            $matchingIds = array_merge(
                $matchingIds,
                $this->applyServerColumnFilters($leads, $columnFilters)->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }, 'leads.id', 'id');

        return $matchingIds ?: [0];
    }

    private function applyServerColumnFilters(Collection $leads, array $columnFilters): Collection
    {
        $crmStatusFilters = $columnFilters['crm_status'] ?? collect();

        if ($crmStatusFilters->isNotEmpty()) {
            $leads = $leads->filter(function (Lead $lead) use ($crmStatusFilters) {
                $displayStatus = $this->displayStatusForLead($lead);

                return $crmStatusFilters->contains($displayStatus);
            });
        }

        return $leads;
    }

    private function applyDisplayStatuses(Collection $leads): void
    {
        if (Schema::hasTable('tasks')) {
            $this->displayStatusResolver->apply($leads);
            return;
        }

        $leads->each(function (Lead $lead) {
            $lead->setAttribute('display_status', $this->fallbackDisplayStatus($lead));
        });
    }

    private function displayStatusForLead(Lead $lead): string
    {
        return (string) ($lead->getAttribute('display_status') ?? $this->fallbackDisplayStatus($lead));
    }

    private function fallbackDisplayStatus(Lead $lead): string
    {
        $status = (string) ($lead->status ?? 'new');
        if ($status !== 'new') {
            return $status;
        }
        if ((int) ($lead->cnp_count ?? 0) > 0) {
            return 'cnp';
        }
        if (!empty($lead->next_followup_at)) {
            return 'follow_up';
        }

        return $status;
    }

    private function statusLabel(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return '';
        }

        return self::STATUS_LABELS[$normalized] ?? ucwords(str_replace('_', ' ', $normalized));
    }

    private function statusKeyForLabel(string $label): string
    {
        $normalizedLabel = strtoupper(trim($label));
        foreach (self::STATUS_LABELS as $key => $statusLabel) {
            if (strtoupper($statusLabel) === $normalizedLabel) {
                return $key;
            }
        }

        return strtolower(str_replace(' ', '_', $normalizedLabel));
    }

    private function resolveDateRange(Request $request): array
    {
        if (!$request->filled('start_date') || !$request->filled('end_date')) {
            return [null, null];
        }

        try {
            return [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function finalLabel(Lead $lead): string
    {
        if ((bool) ($lead->is_dead ?? false) || $lead->status === 'dead') {
            return 'Dead';
        }

        return match ((string) $lead->status) {
            'closed' => 'Closed',
            'junk' => 'Junk',
            'not_interested' => 'Not Interested',
            default => '',
        };
    }

    private function rowKey(Lead $lead): string
    {
        return 'lead:' . $lead->id;
    }

    private function leadIdFromRowKey(string $rowKey): ?int
    {
        app(LeadAuditorAccessService::class)->authorizeRow($rowKey);
        if (!str_starts_with($rowKey, 'lead:')) {
            return null;
        }

        $id = (int) substr($rowKey, 5);

        return $id > 0 ? $id : null;
    }
}
