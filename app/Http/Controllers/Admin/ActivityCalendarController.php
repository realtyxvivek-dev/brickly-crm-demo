<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\InsightSheetService;
use App\Services\LeadActiveWorkflowService;
use App\Services\MeetingService;
use App\Services\PhonePrivacyService;
use App\Services\SiteVisitTaskSyncService;
use App\Services\VerificationRoutingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ActivityCalendarController extends Controller
{
    private const SALES_ROLES = [
        Role::SALES_MANAGER,
        Role::SENIOR_MANAGER,
        Role::ASSISTANT_SALES_MANAGER,
        Role::SALES_EXECUTIVE,
        Role::TELECALLER,
        'sales_head',
    ];

    public function __construct(
        private readonly PhonePrivacyService $phonePrivacy,
        private readonly InsightSheetService $insightSheet,
        private readonly MeetingService $meetingService,
        private readonly LeadActiveWorkflowService $workflowService,
        private readonly SiteVisitTaskSyncService $siteVisitTaskSync,
    ) {
        $this->middleware(['auth', 'role:lead_quality_auditor']);
    }

    public function index()
    {
        return view('admin.lead-quality-auditor.activity-calendar', [
            'salesUsers' => $this->salesUsers(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'user_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(['follow_up', 'meeting', 'site_visit'])],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'overdue', 'cancelled'])],
            'project' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $start = Carbon::parse($validated['start'])->startOfDay();
        // FullCalendar sends an exclusive range end.
        $end = Carbon::parse($validated['end'])->subSecond();
        abort_if($start->diffInDays($end) > 100, 422, 'Date range is too large.');

        $events = collect();
        $types = empty($validated['type'])
            ? ['follow_up', 'meeting', 'site_visit']
            : [$validated['type']];

        if (in_array('follow_up', $types, true)) {
            $query = FollowUp::withQueueHidden()->with(['lead:id,name,phone', 'creator:id,name'])
                ->where(function (Builder $query) use ($start, $end) {
                    $query->whereBetween('scheduled_at', [$start, $end])
                        ->orWhereBetween('completed_at', [$start, $end]);
                });
            $this->applyFilters($query, $validated, 'created_by', false);
            $query->get()->each(fn (FollowUp $item) => $this->appendOccurrences($events, 'follow_up', $item, $request));
        }

        if (in_array('meeting', $types, true)) {
            $query = Meeting::withQueueHidden()->with(['lead:id,name,phone', 'assignedTo:id,name', 'creator:id,name'])
                ->where(function (Builder $query) use ($start, $end) {
                    $query->whereBetween('scheduled_at', [$start, $end])
                        ->orWhereBetween('completed_at', [$start, $end]);
                });
            $this->applyFilters($query, $validated, 'assigned_to');
            $query->get()->each(fn (Meeting $item) => $this->appendOccurrences($events, 'meeting', $item, $request));
        }

        if (in_array('site_visit', $types, true)) {
            $query = SiteVisit::withQueueHidden()->with(['lead:id,name,phone', 'assignedTo:id,name', 'creator:id,name'])
                ->where(function (Builder $query) use ($start, $end) {
                    $query->whereBetween('scheduled_at', [$start, $end])
                        ->orWhereBetween('completed_at', [$start, $end]);
                });
            $this->applyFilters($query, $validated, 'assigned_to');
            $query->get()->each(fn (SiteVisit $item) => $this->appendOccurrences($events, 'site_visit', $item, $request));
        }

        $events = $events->filter(function (array $event) use ($validated) {
            if (($validated['status'] ?? null) === 'overdue') {
                return $event['extendedProps']['is_overdue'];
            }
            if (!empty($validated['status']) && $validated['status'] !== $event['extendedProps']['status']) {
                return false;
            }
            if (!empty($validated['project']) && stripos((string) $event['extendedProps']['project'], $validated['project']) === false) {
                return false;
            }
            return true;
        })->values();

        $unique = $events->unique(fn (array $event) => $event['extendedProps']['type'].':'.$event['extendedProps']['record_id']);
        $summary = [
            'planned' => $events->where('extendedProps.occurrence', 'planned')->count(),
            'completed' => $events->where('extendedProps.occurrence', 'completed')->count(),
            'overdue' => $unique->where('extendedProps.is_overdue', true)->count(),
            'follow_up' => $unique->where('extendedProps.type', 'follow_up')->count(),
            'meeting' => $unique->where('extendedProps.type', 'meeting')->count(),
            'site_visit' => $unique->where('extendedProps.type', 'site_visit')->count(),
        ];

        return response()->json(['events' => $events, 'summary' => $summary]);
    }

    public function leads(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($validated['q']);
        $leads = Lead::query()
            ->whereIn('leads.id', app(\App\Services\LeadAuditorAccessService::class)->visibleLeadIds())
            ->with(['currentAssignment.assignedTo:id,name'])
            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
            ->latest('id')->limit(20)->get(['id', 'name', 'phone', 'status']);

        return response()->json($leads->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $this->phonePrivacy->display($lead->phone, $request->user()),
            'status' => $lead->status,
            'owner_id' => $lead->currentAssignment?->assigned_to,
            'owner_name' => $lead->currentAssignment?->assignedTo?->name,
        ]));
    }

    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        $record->loadMissing(['lead', 'creator']);
        if (method_exists($record, 'assignedTo')) {
            $record->loadMissing('assignedTo');
        }
        $lead = $record->lead;

        return response()->json([
            'activity' => $this->activityPayload($type, $record, $request),
            'lead_details' => $lead ? $this->insightSheet->leadDetails('lead:'.$lead->id) : null,
            'completion_details' => $lead ? $this->insightSheet->completionDetails('lead:'.$lead->id) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $base = $request->validate([
            'type' => ['required', Rule::in(['follow_up', 'meeting', 'site_visit'])],
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'sales_person_id' => ['required', 'integer'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'remark' => ['required', 'string', 'max:2000'],
        ]);
        $owner = $this->salesUser((int) $base['sales_person_id']);
        $lead = Lead::findOrFail($base['lead_id']);
        app(\App\Services\LeadAuditorAccessService::class)->authorizeLead($lead->id);
        $scheduledAt = Carbon::parse($base['scheduled_at']);

        $record = DB::transaction(function () use ($request, $base, $owner, $lead, $scheduledAt) {
            if ($base['type'] === 'follow_up') {
                $record = FollowUp::create([
                    'lead_id' => $lead->id,
                    'created_by' => $owner->id,
                    'type' => $request->input('follow_up_type', 'call'),
                    'notes' => $base['remark'],
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                ]);
                $lead->update(['next_followup_at' => $scheduledAt]);
            } elseif ($base['type'] === 'meeting') {
                $extra = $request->validate($this->meetingRules());
                $record = $this->meetingService->createMeetingWithReminder(array_merge($extra, [
                    'lead_id' => $lead->id,
                    'created_by' => $owner->id,
                    'assigned_to' => $owner->id,
                    'customer_name' => $lead->name,
                    'phone' => $lead->getRawOriginal('phone'),
                    'scheduled_at' => $scheduledAt,
                    'date_of_visit' => $scheduledAt->toDateString(),
                    'meeting_notes' => $base['remark'],
                    'reminder_enabled' => true,
                    'reminder_minutes' => 30,
                    'status' => 'scheduled',
                    'verification_status' => 'pending',
                ]), $owner);
            } else {
                $extra = $request->validate($this->siteVisitRules());
                $record = SiteVisit::create(array_merge($extra, [
                    'lead_id' => $lead->id,
                    'created_by' => $owner->id,
                    'assigned_to' => $owner->id,
                    'customer_name' => $lead->name,
                    'phone' => $lead->getRawOriginal('phone'),
                    'scheduled_at' => $scheduledAt,
                    'date_of_visit' => $scheduledAt->toDateString(),
                    'visit_notes' => $base['remark'],
                    'status' => 'scheduled',
                    'verification_status' => 'pending',
                ]));
                $this->siteVisitTaskSync->syncReminderTask($record, $owner, [
                    'assigned_to' => $owner->id,
                    'created_by' => $owner->id,
                    'priority' => 'medium',
                    'notes_prefix' => 'Created by Lead Quality Auditor',
                ]);
                $lead->updateStatusIfAllowed(($record->lead_type ?? null) === 'Revisited' ? 'revisited_scheduled' : 'visit_scheduled');
            }

            if ($base['type'] !== 'meeting') {
                $this->workflowService->moveLeadToWorkflow($lead->id, $base['type'], $record->id);
            }
            $this->audit($request, 'calendar_activity_created', $record, null, $record->toArray(), $base['remark']);
            return $record;
        });

        return response()->json(['success' => true, 'activity' => $this->activityPayload($base['type'], $record->fresh(), $request)], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        abort_if($record->status === 'completed', 422, 'Completed activity cannot be changed.');
        $validated = $request->validate([
            'action' => ['required', Rule::in(['reschedule', 'reassign', 'cancel', 'remark'])],
            'scheduled_at' => ['required_if:action,reschedule', 'nullable', 'date', 'after:now'],
            'sales_person_id' => ['required_if:action,reassign', 'nullable', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $old = $record->toArray();

        DB::transaction(function () use ($request, $record, $type, $validated, $old) {
            if ($validated['action'] === 'reschedule') {
                $newTime = Carbon::parse($validated['scheduled_at']);
                if ($type === 'follow_up') {
                    $notes = trim((string) $record->notes."\n".now()->format('d M Y h:i A').' - Rescheduled by auditor: '.$validated['reason']);
                    $record->forceFill(['scheduled_at' => $newTime, 'status' => 'scheduled', 'notes' => $notes])->save();
                    $record->lead?->forceFill(['next_followup_at' => $newTime])->save();
                } else {
                    $record->forceFill([
                        'scheduled_at' => $newTime,
                        'status' => 'scheduled',
                        'verification_status' => 'pending',
                        'rescheduled_at' => now(),
                        'rescheduled_by' => $request->user()->id,
                        'reschedule_reason' => $validated['reason'],
                        'is_rescheduled' => true,
                        'reschedule_count' => (int) ($record->reschedule_count ?? 0) + 1,
                    ])->save();
                }
                $this->syncRelatedTasks($record, $type, $request->user(), 'reschedule');
            } elseif ($validated['action'] === 'reassign') {
                $owner = $this->salesUser((int) $validated['sales_person_id']);
                $field = $type === 'follow_up' ? 'created_by' : 'assigned_to';
                $record->forceFill([$field => $owner->id])->save();
                $this->syncRelatedTasks($record, $type, $request->user(), 'reassign');
            } elseif ($validated['action'] === 'cancel') {
                $record->forceFill(['status' => 'cancelled'])->save();
                $this->syncRelatedTasks($record, $type, $request->user(), 'cancel');
            } else {
                $field = $type === 'follow_up' ? 'notes' : ($type === 'meeting' ? 'meeting_notes' : 'visit_notes');
                $existing = trim((string) $record->{$field});
                $record->forceFill([$field => trim($existing."\n".now()->format('d M Y h:i A').' - Auditor: '.$validated['reason'])])->save();
            }
            $this->audit($request, 'calendar_'.$validated['action'], $record, $old, $record->fresh()->toArray(), $validated['reason']);
        });

        return response()->json(['success' => true, 'activity' => $this->activityPayload($type, $record->fresh(), $request)]);
    }

    public function complete(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->findRecord($type, $id);
        abort_if($record->status === 'completed', 422, 'Activity is already completed.');
        $old = $record->toArray();

        if ($type === 'follow_up') {
            $validated = $request->validate(['outcome' => ['required', 'string', 'max:1000']]);
            $record->forceFill(['status' => 'completed', 'completed_at' => now(), 'outcome' => $validated['outcome']])->save();
        } elseif ($type === 'meeting') {
            $validated = $request->validate([
                'feedback' => ['required', 'string', 'max:2000'],
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
                'proof_photos' => ['required', 'array', 'min:1'],
                'proof_photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            ]);
            $record->forceFill([
                'status' => 'completed', 'completed_at' => now(), 'verification_status' => 'pending',
                'feedback' => $validated['feedback'], 'rating' => $validated['rating'],
                'completion_proof_photos' => $this->storePhotos($validated['proof_photos'], 'meetings/proof'),
            ])->save();
            $record->lead?->updateStatusIfAllowed('meeting_completed');
            $this->notifyPendingVerification($record, VerificationRoutingService::WORKFLOW_MEETING);
        } else {
            $validated = $request->validate([
                'feedback' => ['required', 'string', 'max:2000'],
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
                'visited_projects' => ['required', 'string', 'max:2000'],
                'visited_property_types' => ['required', 'array', 'min:1'],
                'visited_property_types.*' => ['string', Rule::in(['plot', 'villa', 'apartment', 'commercial', 'other'])],
                'tentative_closing_time' => ['required', Rule::in(['within_3_days', 'tomorrow', 'this_week', 'this_month', 'it_will_take_time'])],
                'proof_photos' => ['required', 'array', 'min:1'],
                'proof_photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            ]);
            unset($validated['proof_photos']);
            $record->forceFill(array_merge($validated, [
                'status' => 'completed', 'completed_at' => now(), 'verification_status' => 'pending',
                'completion_proof_photos' => $this->storePhotos($request->file('proof_photos', []), 'site-visits/proof'),
            ]))->save();
            $record->lead?->updateStatusIfAllowed(($record->lead_type ?? null) === 'Revisited' ? 'revisited_completed' : 'visit_done');
            $this->notifyPendingVerification($record, VerificationRoutingService::WORKFLOW_SITE_VISIT);
        }

        $this->syncRelatedTasks($record, $type, $request->user(), 'complete');
        $this->audit($request, 'calendar_activity_completed', $record, $old, $record->fresh()->toArray(), $validated['feedback'] ?? $validated['outcome']);
        return response()->json(['success' => true, 'activity' => $this->activityPayload($type, $record->fresh(), $request)]);
    }

    private function appendOccurrences($events, string $type, $record, Request $request): void
    {
        $payload = $this->activityPayload($type, $record, $request);
        if ($record->scheduled_at) {
            $events->push($this->calendarEvent($payload, $record->scheduled_at, 'planned'));
        }
        if ($record->completed_at) {
            $events->push($this->calendarEvent($payload, $record->completed_at, 'completed'));
        }
    }

    private function calendarEvent(array $payload, $date, string $occurrence): array
    {
        $label = ['follow_up' => 'F', 'meeting' => 'M', 'site_visit' => 'V'][$payload['type']];
        return [
            'id' => $payload['type'].'-'.$payload['record_id'].'-'.$occurrence,
            'title' => $label.' · '.$payload['customer_name'],
            'start' => Carbon::parse($date)->toIso8601String(),
            'classNames' => ['activity-'.$payload['type'], 'occurrence-'.$occurrence, $payload['is_overdue'] ? 'is-overdue' : ''],
            'extendedProps' => array_merge($payload, ['occurrence' => $occurrence]),
        ];
    }

    private function activityPayload(string $type, $record, Request $request): array
    {
        $lead = $record->lead;
        $owner = $type === 'follow_up' ? $record->creator : ($record->assignedTo ?? $record->creator);
        $remarkField = $type === 'follow_up' ? 'notes' : ($type === 'meeting' ? 'meeting_notes' : 'visit_notes');
        $project = $type === 'site_visit' ? ($record->project ?: $record->property_name) : ($record->project ?? null);
        return [
            'record_id' => $record->id,
            'lead_id' => $record->lead_id,
            'type' => $type,
            'status' => $record->status,
            'is_overdue' => $record->status === 'scheduled' && $record->scheduled_at?->isPast(),
            'customer_name' => $record->customer_name ?? $lead?->name ?? 'Unknown customer',
            'phone' => $this->phonePrivacy->display($record->phone ?? $lead?->phone, $request->user()),
            'sales_person_id' => $owner?->id,
            'sales_person' => $owner?->name ?? 'Unassigned',
            'project' => $project ?: '-',
            'remark' => trim((string) ($record->{$remarkField} ?? '')),
            'scheduled_at' => $record->scheduled_at?->toIso8601String(),
            'completed_at' => $record->completed_at?->toIso8601String(),
        ];
    }

    private function applyFilters(Builder $query, array $filters, string $ownerField, bool $hasCustomerColumns = true): void
    {
        $access = app(\App\Services\LeadAuditorAccessService::class);
        $access->authorizeUser(isset($filters['user_id']) ? (int) $filters['user_id'] : null);
        $access->scopeUsers($query, $ownerField === 'assigned_to' ? DB::raw('COALESCE(assigned_to, created_by)') : $ownerField);
        $access->scopeLeads($query, 'lead_id');
        if (!empty($filters['user_id'])) {
            $query->where($ownerField, $filters['user_id']);
        }
        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function (Builder $query) use ($term, $hasCustomerColumns) {
                $query->whereHas('lead', fn (Builder $lead) => $lead->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
                if ($hasCustomerColumns) {
                    $query->orWhere('customer_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%");
                }
            });
        }
    }

    private function findRecord(string $type, int $id)
    {
        $class = match ($type) {
            'follow_up' => FollowUp::class,
            'meeting' => Meeting::class,
            'site_visit' => SiteVisit::class,
            default => abort(404),
        };
        $record = $class::withQueueHidden()->findOrFail($id);
        $access = app(\App\Services\LeadAuditorAccessService::class);
        $access->authorizeLead((int) $record->lead_id);
        $owner = $type === 'follow_up' ? $record->created_by : ($record->assigned_to ?? $record->created_by);
        abort_unless($owner, 404);
        $access->authorizeUser((int) $owner);
        return $record;
    }

    private function salesUsers()
    {
        return User::query()->with('role:id,name,slug')->where('is_active', true)
            ->whereIn('users.id', app(\App\Services\LeadAuditorAccessService::class)->allowedIds())
            ->whereHas('role', fn (Builder $query) => $query->whereIn('slug', self::SALES_ROLES))
            ->orderBy('name')->get(['id', 'name', 'role_id']);
    }

    private function salesUser(int $id): User
    {
        app(\App\Services\LeadAuditorAccessService::class)->authorizeUser($id);
        return User::query()->whereKey($id)->where('is_active', true)
            ->whereHas('role', fn (Builder $query) => $query->whereIn('slug', self::SALES_ROLES))->firstOrFail();
    }

    private function meetingRules(): array
    {
        return [
            'project' => ['nullable', 'string', 'max:255'],
            'team_leader' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:1000'],
            'budget_range' => ['required', Rule::in(['Under 50 Lac', '50 Lac – 1 Cr', '1 Cr – 2 Cr', '2 Cr – 3 Cr', 'Above 3 Cr'])],
            'property_type' => ['required', Rule::in(['Plot/Villa', 'Flat', 'Commercial', 'Just Exploring'])],
            'payment_mode' => ['required', Rule::in(['Self Fund', 'Loan'])],
            'tentative_period' => ['required', Rule::in(['Within 1 Month', 'Within 3 Months', 'Within 6 Months', 'More than 6 Months'])],
            'lead_type' => ['required', Rule::in(['New Visit', 'Revisited', 'Meeting', 'Prospect'])],
        ];
    }

    private function siteVisitRules(): array
    {
        $rules = $this->meetingRules();
        unset($rules['location']);
        return array_merge($rules, [
            'property_name' => ['required', 'string', 'max:255'],
            'property_address' => ['required', 'string', 'max:1000'],
            'team_leader' => ['required', 'string', 'max:255'],
        ]);
    }

    private function syncRelatedTasks($record, string $type, User $actor, string $action): void
    {
        if ($type === 'meeting' && in_array($action, ['reschedule', 'reassign'], true)) {
            $this->meetingService->syncPreMeetingReminderAfterReschedule($record, $actor, null);
            return;
        }
        if ($type === 'site_visit' && in_array($action, ['reschedule', 'reassign'], true)) {
            $this->siteVisitTaskSync->syncReminderTask($record, $actor, ['assigned_to' => $record->assigned_to, 'created_by' => $actor->id]);
            return;
        }
        if (!Schema::hasTable('tasks')) {
            return;
        }

        $column = ['follow_up' => 'follow_up_id', 'meeting' => 'meeting_id', 'site_visit' => 'site_visit_id'][$type];
        $ownerId = $type === 'follow_up' ? $record->created_by : $record->assigned_to;
        foreach ([Task::class, TelecallerTask::class] as $taskClass) {
            if (!Schema::hasTable((new $taskClass)->getTable()) || !$taskClass::supportsColumn($column)) {
                continue;
            }
            $taskClass::withoutGlobalScopes()->where($column, $record->id)
                ->whereIn('status', $taskClass::OPEN_STATUSES)->whereNull('completed_at')->get()
                ->each(function ($task) use ($action, $ownerId, $record) {
                    if ($action === 'reschedule') {
                        $task->forceFill(['scheduled_at' => $record->scheduled_at, 'status' => 'pending'])->save();
                    } elseif ($action === 'reassign') {
                        $task->forceFill(['assigned_to' => $ownerId])->save();
                    } else {
                        $task->forceFill(['status' => $action === 'complete' ? 'completed' : 'cancelled', 'completed_at' => now()])->save();
                    }
                });
        }
    }

    private function notifyPendingVerification($record, string $workflow): void
    {
        try {
            $record->loadMissing('creator');
            $users = app(VerificationRoutingService::class)->eligibleVerifiers($record, $workflow);
            foreach ($users->unique('id') as $user) {
                app(\App\Services\NotificationService::class)->notifyNewVerification(
                    $user,
                    $workflow === VerificationRoutingService::WORKFLOW_MEETING ? 'meeting' : 'site_visit',
                    'New Activity Verification',
                    "{$record->customer_name} requires verification",
                    url('/hr-manager/verifications'),
                    ['record_id' => $record->id, 'customer_name' => $record->customer_name]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function storePhotos(array $photos, string $directory): array
    {
        return collect($photos)->map(function ($photo) use ($directory) {
            $path = $directory.'/'.now()->format('YmdHis').'_'.uniqid().'.'.$photo->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('', $photo, $path);
            return $path;
        })->all();
    }

    private function audit(Request $request, string $action, $record, ?array $old, array $new, string $reason): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'model_type' => $record::class,
            'model_id' => $record->id,
            'description' => $reason,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
