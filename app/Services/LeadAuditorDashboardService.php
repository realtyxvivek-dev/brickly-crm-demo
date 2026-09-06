<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LeadAuditorDashboardService
{
    public const CATEGORIES = ['initial', 'follow_up', 'meeting', 'site_visit', 'other'];
    public const OUTCOMES = ['interested', 'not_interested', 'cnp', 'call_not_picked', 'connected', 'follow_up', 'follow_up_needed', 'schedule_follow_up', 'schedule_visit', 'schedule_meeting', 'meeting_request', 'site_visit_request', 'meeting_scheduled', 'site_visit_scheduled', 'meeting', 'site_visit', 'verified_prospect', 'junk', 'wrong_number', 'busy', 'callback', 'call_later', 'call_again', 'broker', 'block', 'visited', 'meeting_completed', 'visit_done', 'customer_not_available', 'send_to_closer'];

    public function users(?int $id = null): Builder
    {
        app(LeadAuditorAccessService::class)->authorizeUser($id);
        return DB::table('users as u')->join('roles as r', 'r.id', '=', 'u.role_id')
            ->when(app(LeadAuditorAccessService::class)->restricted(), fn ($q) => app(LeadAuditorAccessService::class)->scopeUsers($q, 'u.id'))
            ->whereNull('u.deleted_at')->where('u.is_active', true)
            ->whereIn('r.slug', ['sales_manager', 'senior_manager', 'assistant_sales_manager', 'sales_executive', 'telecaller', 'sales_head'])
            ->when($id, fn ($q) => $q->where('u.id', $id));
    }

    private function cycleStart(): string
    {
        return 'CASE WHEN l.transferred_to_user_id = a.assigned_to AND l.transferred_at IS NOT NULL AND (a.assigned_at IS NULL OR l.transferred_at >= a.assigned_at) THEN l.transferred_at ELSE a.assigned_at END';
    }

    public function untouched(?int $userId = null, string $range = 'all'): Builder
    {
        $start = $this->cycleStart();
        $rangeStart = match ($range) {
            'today' => Carbon::today(),
            'week' => Carbon::today()->startOfWeek(),
            'month' => Carbon::today()->startOfMonth(),
            'year' => Carbon::today()->startOfYear(),
            default => null,
        };
        $query = DB::table('lead_assignments as a')->join('leads as l', 'l.id', '=', 'a.lead_id')
            ->joinSub($this->users($userId)->select('u.id', 'u.name'), 'owner', 'owner.id', '=', 'a.assigned_to')
            ->where('a.is_active', true)->where('a.assignment_type', 'primary')->whereNull('l.deleted_at')
            ->when(app(LeadAuditorAccessService::class)->restricted(), fn ($q) => app(LeadAuditorAccessService::class)->scopeLeads($q, 'l.id'))
            ->whereIn('l.status', ['new', 'fresh_transfer'])
            // A transfer marker must not override an existing CNP/follow-up stage.
            ->whereRaw('COALESCE(l.cnp_count, 0) = 0')->whereNull('l.next_followup_at')
            // All Leads derives a stage from retained history even when the stored stage is new.
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('tasks as stage_task')->whereColumn('stage_task.lead_id', 'l.id')
                    ->whereRaw("(a.assignment_method <> 'admin_reopen' OR a.assignment_method IS NULL OR stage_task.id > COALESCE(JSON_EXTRACT(CASE WHEN JSON_VALID(a.notes) THEN a.notes ELSE '{}' END, '$.previous_record_max_ids.tasks'), 0))")
                    ->whereNull('stage_task.deleted_at')->where(function ($stage) {
                        $stage->whereIn(DB::raw('LOWER(stage_task.outcome)'), ['junk', 'not_interested', 'cnp', 'follow_up', 'followup', 'follow_up_needed', 'schedule_follow_up', 'interested', 'visited', 'visit_done', 'meeting_done']);
                        foreach (['title', 'description', 'notes'] as $column) {
                            foreach (['cnp retry task created', 'cnp rescheduled', 'previous call not picked'] as $marker) {
                                $stage->orWhereRaw("LOWER(stage_task.$column) LIKE ?", ['%'.$marker.'%']);
                            }
                        }
                    });
            })
            ->where(function ($q) {
                $q->where('a.assignment_method', 'admin_reopen')->orWhereNotIn('l.id', \App\Models\Lead::query()->select('leads.id')->whereHas('latestProspect', fn ($q) => $q->whereIn('verification_status', ['verified', 'approved'])));
            })
            ->when($rangeStart, fn ($q) => $q->whereRaw("($start) >= ?", [$rangeStart->toDateTimeString()])->whereRaw("($start) < ?", [Carbon::tomorrow()->toDateTimeString()]))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('lead_assignments as newer')->whereColumn('newer.lead_id', 'a.lead_id')
                    ->where('newer.is_active', true)->where('newer.assignment_type', 'primary')
                    ->where(function ($q) {
                        $q->whereColumn('newer.assigned_at', '>', 'a.assigned_at')
                            ->orWhere(fn ($q) => $q->whereNull('a.assigned_at')->whereNotNull('newer.assigned_at'))
                            ->orWhere(fn ($q) => $q->whereRaw('(newer.assigned_at = a.assigned_at OR (newer.assigned_at IS NULL AND a.assigned_at IS NULL))')->whereColumn('newer.id', '>', 'a.id'));
                    });
            });
        foreach (['tasks' => 'type', 'telecaller_tasks' => 'task_type'] as $table => $typeColumn) {
            $completed = $table === 'tasks' ? 'COALESCE(t.outcome_recorded_at, t.completed_at)' : 't.completed_at';
            // CNP automation cancels its task, but its recorded outcome still advances the lead.
            $query->whereNotExists(function ($q) use ($table, $start, $completed) {
                $q->selectRaw('1')->from($table.' as t')->whereColumn('t.lead_id', 'a.lead_id')
                    ->whereRaw("(a.assignment_method <> 'admin_reopen' OR a.assignment_method IS NULL OR t.id > COALESCE(JSON_EXTRACT(CASE WHEN JSON_VALID(a.notes) THEN a.notes ELSE '{}' END, '$.previous_record_max_ids.$table'), 0))")
                    ->whereColumn('t.assigned_to', 'a.assigned_to')->whereNull('t.deleted_at')
                    ->whereIn('t.outcome', ['cnp', 'call_not_picked', 'follow_up', 'follow_up_needed'])
                    ->whereRaw("$completed >= ($start)")->whereRaw("t.created_at >= ($start)");
            });
            $query->whereNotExists(function ($q) use ($table, $typeColumn, $start, $completed) {
                $q->selectRaw('1')->from($table.' as t')->whereColumn('t.lead_id', 'a.lead_id')
                    ->whereRaw("(a.assignment_method <> 'admin_reopen' OR a.assignment_method IS NULL OR t.id > COALESCE(JSON_EXTRACT(CASE WHEN JSON_VALID(a.notes) THEN a.notes ELSE '{}' END, '$.previous_record_max_ids.$table'), 0))")
                    ->whereColumn('t.assigned_to', 'a.assigned_to')->whereNull('t.deleted_at')
                    ->where('t.status', 'completed')->whereIn('t.'.$typeColumn, ['phone_call', 'calling', 'follow_up'])
                    ->whereIn('t.outcome', self::OUTCOMES)
                    ->whereRaw("$completed >= ($start)")
                    ->where(function ($cycle) use ($table, $start) {
                        $cycle->whereRaw("t.created_at >= ($start)");
                        if ($table === 'tasks') {
                            // Reused tasks need a recorded owner completion in the new assignment cycle.
                            $cycle->orWhereExists(function ($response) use ($start) {
                                $response->selectRaw('1')->from('task_activities as completed_response')
                                    ->whereColumn('completed_response.task_id', 't.id')->whereColumn('completed_response.user_id', 'a.assigned_to')
                                    ->where('completed_response.activity_type', 'status_changed')->where('completed_response.new_value', 'completed')
                                    ->whereRaw("completed_response.created_at >= ($start)")
                                    ->whereColumn('completed_response.created_at', '<=', 't.completed_at');
                            });
                        }
                    });
                if ($table === 'tasks') {
                    // A known different actor cannot satisfy the current owner's first response.
                    $q->whereNotExists(function ($activity) {
                        $activity->selectRaw('1')->from('task_activities as response')
                            ->whereColumn('response.task_id', 't.id')->where('response.activity_type', 'status_changed')
                            ->where('response.new_value', 'completed')->whereNotNull('response.user_id')
                            ->whereColumn('response.user_id', '!=', 't.assigned_to');
                    });
                }
            });
        }
        // Imported completed activities may not have an initial task/outcome row.
        foreach (['meetings', 'site_visits'] as $table) {
            $query->whereNotExists(function ($q) use ($table, $start) {
                $q->selectRaw('1')->from($table.' as activity')
                    ->whereRaw("(a.assignment_method <> 'admin_reopen' OR a.assignment_method IS NULL OR activity.id > COALESCE(JSON_EXTRACT(CASE WHEN JSON_VALID(a.notes) THEN a.notes ELSE '{}' END, '$.previous_record_max_ids.$table'), 0))")
                    ->whereColumn('activity.lead_id', 'a.lead_id')
                    ->whereRaw('COALESCE(activity.assigned_to, activity.created_by) = a.assigned_to')
                    ->whereNull('activity.deleted_at')
                    ->where(function ($evidence) use ($start) {
                        $evidence->where(fn ($completed) => $completed->where('activity.status', 'completed')->whereRaw("activity.completed_at >= ($start)"))
                            ->orWhere(function ($scheduled) use ($start) {
                                // Legacy scheduling completed the initial task without writing its outcome.
                                $scheduled->whereIn('activity.status', ['scheduled', 'pending', 'confirmed'])
                                    ->whereColumn('activity.created_by', 'a.assigned_to')
                                    ->whereExists(function ($task) use ($start) {
                                        $task->selectRaw('1')->from('tasks as initial')
                                            ->whereColumn('initial.lead_id', 'a.lead_id')->whereColumn('initial.assigned_to', 'a.assigned_to')
                                            ->whereNull('initial.deleted_at')->where('initial.status', 'completed')
                                            ->whereIn('initial.type', ['phone_call', 'calling', 'follow_up'])
                                            ->where(fn ($outcome) => $outcome->whereNull('initial.outcome')->orWhere('initial.outcome', ''))
                                            ->whereRaw("initial.created_at >= ($start)")
                                            ->whereColumn('initial.completed_at', '>=', 'activity.created_at')
                                            ->whereNotExists(function ($actor) {
                                                $actor->selectRaw('1')->from('task_activities as response')->whereColumn('response.task_id', 'initial.id')
                                                    ->where('response.activity_type', 'status_changed')->where('response.new_value', 'completed')
                                                    ->whereNotNull('response.user_id')->whereColumn('response.user_id', '!=', 'a.assigned_to');
                                            });
                                    });
                            });
                    })
                    ->whereRaw("activity.scheduled_at >= ($start)")
                    ->whereRaw("COALESCE(activity.created_at, activity.scheduled_at) >= ($start)");
            });
        }
        return $query->select('l.id as lead_id', 'l.name as customer', 'l.phone', 'l.source', 'owner.name as salesperson', 'a.assigned_to as user_id')
            ->selectRaw("($start) as assigned_at")
            ->selectRaw("CASE WHEN l.transferred_to_user_id = a.assigned_to AND l.transferred_at IS NOT NULL AND (a.assigned_at IS NULL OR l.transferred_at >= a.assigned_at) THEN 'fresh_transfer' ELSE 'new' END as assignment_type");
    }

    // Activities own their linked reminder rows, including reminders in the other task table.
    private function unlinkedTasks(string $table): Builder
    {
        $query = DB::table($table.' as t')->whereNull('t.deleted_at')->whereNotNull('t.lead_id');
        foreach (['meetings' => 'meeting_id', 'site_visits' => 'site_visit_id', 'follow_ups' => 'follow_up_id'] as $activity => $column) {
            $query->where(function ($q) use ($activity, $column, $table) {
                $q->whereNotExists(function ($linked) use ($activity, $column, $table) {
                        $linked->selectRaw('1')->from($activity.' as linked')->whereColumn('linked.lead_id', 't.lead_id')->whereNull('linked.deleted_at')
                            ->where(function ($match) use ($activity, $column, $table) {
                                $match->whereColumn('linked.id', 't.'.$column);
                                if ($activity === 'site_visits' && $table === 'tasks') {
                                    $match->orWhereColumn('linked.reminder_task_id', 't.id');
                                }
                                if ($activity === 'meetings' && $table === 'telecaller_tasks') {
                                    $match->orWhereColumn('linked.pre_meeting_call_task_id', 't.id');
                                }
                            });
                    });
            });
        }
        return $query;
    }

    public function workItems(?int $userId = null): Builder
    {
        $union = null;
        foreach (['follow_ups' => 'follow_up', 'meetings' => 'meeting', 'site_visits' => 'site_visit'] as $table => $category) {
            $owner = $table === 'follow_ups' ? 't.created_by' : 'COALESCE(t.assigned_to,t.created_by)';
            $query = DB::table($table.' as t')->whereNull('t.deleted_at')
                ->selectRaw("t.id as record_id, '$category' as record_type, '$category' as category, t.lead_id, $owner as user_id, t.scheduled_at, t.completed_at, t.status, NULL as outcome, CASE WHEN t.status = 'completed' AND t.completed_at IS NOT NULL THEN 1 ELSE 0 END as done");
            if ($table === 'follow_ups') {
                foreach (['tasks' => ['type', true], 'telecaller_tasks' => ['task_type', false]] as $taskTable => [$typeColumn, $hasTitle]) {
                    $query->whereNotExists(function ($legacy) use ($taskTable, $typeColumn, $hasTitle) {
                        $legacy->selectRaw('1')->from($taskTable.' as legacy')
                            ->whereNull('legacy.deleted_at')->whereNull('legacy.follow_up_id')
                            ->whereNotIn('legacy.status', ['cancelled', 'rejected'])
                            ->whereColumn('legacy.lead_id', 't.lead_id')
                            ->whereColumn('legacy.assigned_to', 't.created_by')
                            ->whereColumn('legacy.scheduled_at', 't.scheduled_at')
                            ->where(function ($followUp) use ($typeColumn, $hasTitle) {
                                $followUp->where('legacy.'.$typeColumn, 'follow_up');
                                if ($hasTitle) {
                                    $followUp->orWhereRaw("LOWER(COALESCE(legacy.title,'')) LIKE 'follow-up call:%'");
                                }
                            });
                    });
                }
            }
            $union = $union ? $union->unionAll($query) : $query;
        }
        foreach (['tasks' => 'type', 'telecaller_tasks' => 'task_type'] as $table => $column) {
            $query = $this->unlinkedTasks($table);
            $completed = $table === 'tasks' ? 'COALESCE(t.completed_at,t.outcome_recorded_at)' : 't.completed_at';
            $category = "CASE WHEN t.$column IN ('phone_call','calling') THEN 'initial' WHEN t.$column = 'follow_up' THEN 'follow_up' WHEN t.$column = 'meeting' THEN 'meeting' WHEN t.$column = 'site_visit' THEN 'site_visit' ELSE 'other' END";
            if ($table === 'tasks') {
                $category = "CASE WHEN t.$column = 'follow_up' OR (t.$column IN ('phone_call','calling') AND (LOWER(COALESCE(t.title,'')) LIKE 'follow-up call:%' OR LOWER(COALESCE(t.title,'')) LIKE 'cnp retry follow up call:%' OR LOWER(COALESCE(t.notes,'')) LIKE '%asm follow up cnp automation retry task%')) THEN 'follow_up' WHEN t.$column IN ('phone_call','calling') THEN 'initial' WHEN t.$column = 'meeting' THEN 'meeting' WHEN t.$column = 'site_visit' THEN 'site_visit' ELSE 'other' END";
            }
            $values = implode(',', array_fill(0, count(self::OUTCOMES), '?'));
            $valid = "t.outcome IN ($values)";
            if ($table === 'tasks') {
                // Generic sales tasks have no outcome form; require a recorded owner completion.
                $valid .= " OR (t.outcome IS NULL AND t.type NOT IN ('phone_call','calling','follow_up','meeting','site_visit') AND EXISTS (SELECT 1 FROM task_activities completion WHERE completion.task_id = t.id AND completion.activity_type = 'status_changed' AND completion.new_value = 'completed' AND completion.user_id = t.assigned_to AND completion.created_at >= t.created_at))";
            }
            $query->selectRaw("t.id as record_id, '$table' as record_type, $category as category, t.lead_id, t.assigned_to as user_id, t.scheduled_at, $completed as completed_at, t.status, t.outcome, CASE WHEN t.status = 'completed' AND $completed IS NOT NULL AND ($valid) THEN 1 ELSE 0 END as done", self::OUTCOMES);
            $union->unionAll($query);
        }
        return DB::query()->fromSub($union, 'w')->join('leads as l', 'l.id', '=', 'w.lead_id')
            ->joinSub($this->users($userId)->select('u.id', 'u.name'), 'owner', 'owner.id', '=', 'w.user_id')
            ->whereNull('l.deleted_at')->whereNotIn('w.status', ['cancelled', 'rejected'])
            ->when(app(LeadAuditorAccessService::class)->restricted(), fn ($q) => app(LeadAuditorAccessService::class)->scopeLeads($q, 'l.id'))
            ->where(fn ($q) => $q->where('w.status', '!=', 'completed')->orWhere('w.done', 1));
    }

    public function summary(?int $userId = null, string $range = 'all'): array
    {
        $start = Carbon::today()->toDateTimeString();
        $end = Carbon::tomorrow()->toDateTimeString();
        $groups = $this->workItems($userId)->select('w.user_id', 'w.category')
            ->selectRaw('SUM(CASE WHEN w.scheduled_at >= ? AND w.scheduled_at < ? THEN 1 ELSE 0 END) as due', [$start, $end])
            ->selectRaw('SUM(CASE WHEN w.scheduled_at >= ? AND w.scheduled_at < ? AND w.done = 1 THEN 1 ELSE 0 END) as done', [$start, $end])
            ->selectRaw('SUM(CASE WHEN w.completed_at >= ? AND w.completed_at < ? AND w.done = 1 THEN 1 ELSE 0 END) as completed_today', [$start, $end])
            ->where(fn ($q) => $q->whereBetween('w.scheduled_at', [$start, $end])->orWhereBetween('w.completed_at', [$start, $end]))
            ->groupBy('w.user_id', 'w.category')->get()->groupBy('user_id');
        $rows = $this->users($userId)->select('u.id', 'u.name')->orderBy('u.name')->get()->map(function ($user) use ($groups) {
            $categories = array_fill_keys(self::CATEGORIES, ['due' => 0, 'done' => 0]);
            $due = $done = $completed = 0;
            foreach ($groups->get($user->id, collect()) as $group) {
                $categories[$group->category] = ['due' => (int) $group->due, 'done' => (int) $group->done];
                $due += (int) $group->due;
                $done += (int) $group->done;
                $completed += (int) $group->completed_today;
            }
            return ['id' => $user->id, 'name' => $user->name, 'categories' => $categories, 'due' => $due, 'completed_today' => $completed, 'remaining' => $due - $done];
        });
        $counts = DB::query()->fromSub($this->untouched($userId, $range), 'pending')->selectRaw('assignment_type, COUNT(*) as total')->groupBy('assignment_type')->pluck('total', 'assignment_type');
        return ['users' => $rows, 'untouched' => ['total' => (int) $counts->sum(), 'new' => (int) ($counts['new'] ?? 0), 'fresh_transfer' => (int) ($counts['fresh_transfer'] ?? 0)], 'date' => Carbon::today()->toDateString(), 'server_now' => now()->toIso8601String()];
    }

    public function drilldown(int $userId, string $bucket, ?string $category): Builder
    {
        $start = Carbon::today()->toDateTimeString();
        $end = Carbon::tomorrow()->toDateTimeString();
        $query = $this->workItems($userId)->when($category, fn ($q) => $q->where('w.category', $category));
        if ($bucket === 'completed_today') {
            $query->where('w.done', 1)->where('w.completed_at', '>=', $start)->where('w.completed_at', '<', $end);
        } else {
            $query->where('w.scheduled_at', '>=', $start)->where('w.scheduled_at', '<', $end);
            if ($bucket === 'done') $query->where('w.done', 1);
            if ($bucket === 'remaining') $query->where('w.done', 0);
        }
        return $query->select('w.*', 'l.name as customer', 'l.phone', 'owner.name as salesperson')->orderBy('w.scheduled_at')->orderBy('w.record_type')->orderBy('w.record_id');
    }
}
