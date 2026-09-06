<?php

namespace App\Http\Middleware;

use App\Models\{Lead, Task, TelecallerTask};
use App\Services\LeadReopenService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuardReopenedLeadActivities
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) return $next($request);
        $records = collect($request->route()?->parameters() ?? [])->filter(fn ($value) => $value instanceof Model && in_array($value->getTable(), LeadReopenService::TABLES, true));
        if ($records->isEmpty()) {
            $id = $request->route('task') ?? $request->route('taskId') ?? $request->input('task_id');
            $managerTask = is_string($id) && str_starts_with($id, 'mt_');
            if ($managerTask) $id = substr($id, 3);
            if (is_scalar($id) && ctype_digit((string) $id)) {
                $class = !$managerTask && str_contains($request->route()?->getActionName() ?? '', 'TelecallerController') ? TelecallerTask::class : Task::class;
                $record = $class::withoutGlobalScopes()->find($id);
                if (!$record && $class === TelecallerTask::class) $record = Task::withoutGlobalScopes()->find($id);
                if ($record) $records->push($record);
            }
        }
        if ($records->isEmpty() && str_contains($request->route()?->getActionName() ?? '', 'ActivityCalendarController')) {
            $class = ['follow_up' => \App\Models\FollowUp::class, 'meeting' => \App\Models\Meeting::class, 'site_visit' => \App\Models\SiteVisit::class][$request->route('type')] ?? null;
            if ($class && ($record = $class::withoutGlobalScopes()->find($request->route('id')))) $records->push($record);
        }
        if ($request->filled('assignment_id') && str_contains($request->route()?->getActionName() ?? '', 'TelecallerController')) {
            $record = \App\Models\CrmAssignment::find($request->input('assignment_id'));
            if ($record) $records->push($record);
        }
        if ($records->isEmpty()) return $next($request);
        return DB::transaction(function () use ($records, $request, $next) {
            // Use the same lead lock as reopening, before any outcome-side effects.
            foreach ($records->pluck('lead_id')->filter()->unique()->sort() as $leadId) Lead::whereKey($leadId)->lockForUpdate()->first();
            foreach ($records as $record) app(LeadReopenService::class)->assertCurrentRecord($record);
            return $next($request);
        });
    }
}
