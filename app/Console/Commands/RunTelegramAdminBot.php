<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\SystemErrorLog;
use App\Models\Task;
use App\Models\User;
use App\Services\AdvisorPerformanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunTelegramAdminBot extends Command
{
    protected $signature = 'crm:telegram-admin-bot {--setup} {--command=} {--dry-run}';

    protected $description = 'Answer read-only CRM admin commands received by the configured Telegram bot.';

    private const OFFSET_KEY = 'crm.telegram_admin_bot.offset';

    public function handle(): int
    {
        if (!$this->configured()) {
            return self::FAILURE;
        }

        if ($this->option('setup')) {
            return $this->setup();
        }

        if ($input = trim((string) $this->option('command'))) {
            $reply = $this->reply($input);
            $this->line($reply);

            return $this->option('dry-run') || $this->send($reply)
                ? self::SUCCESS
                : self::FAILURE;
        }

        return $this->processUpdates();
    }

    private function processUpdates(): int
    {
        try {
            $response = Http::timeout(12)->get($this->api('getUpdates'), [
                'offset' => (int) Cache::get(self::OFFSET_KEY, 0),
                'limit' => 50,
                'timeout' => 0,
                'allowed_updates' => json_encode(['message']),
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram admin bot update failed', ['body' => $response->body()]);
                return self::FAILURE;
            }

            foreach ($response->json('result', []) as $update) {
                Cache::forever(self::OFFSET_KEY, ((int) $update['update_id']) + 1);
                $message = $update['message'] ?? null;

                if (!$message || (string) ($message['chat']['id'] ?? '') !== $this->chatId()) {
                    continue;
                }

                $text = trim((string) ($message['text'] ?? ''));
                if ($text !== '') {
                    $this->send($this->reply($text));
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::warning('Telegram admin bot exception: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function reply(string $input): string
    {
        $input = preg_replace('/@\w+/', '', trim($input));
        $buttonCommands = [
            'Today Summary' => '/today',
            'Attendance' => '/present',
            'Overdue Tasks' => '/overdue',
            'Today Visits' => '/visits today',
            'Tomorrow Visits' => '/visits tomorrow',
            'This Week Visits' => '/visits thisweek',
            'Next Week Visits' => '/visits nextweek',
            'Lead Allocation' => '/leads today',
            'New Leads' => '/newleads today',
            'Sales Performance' => '/performance thisweek',
            'Closers' => '/closers thismonth',
            'Server Errors' => '/errors',
            'Menu' => '/menu',
        ];
        $input = $buttonCommands[$input] ?? $input;
        [$command, $argument] = array_pad(preg_split('/\s+/', $input, 2), 2, '');

        return match (strtolower($command)) {
            '/start', '/menu', '/help' => $this->menuMessage(),
            '/today' => $this->todayMessage(),
            '/present', '/attendance' => $this->attendanceMessage(),
            '/overdue' => $this->overdueMessage($argument),
            '/visits' => $this->visitsMessage($argument ?: 'today'),
            '/leads' => $this->leadsMessage($argument ?: 'today'),
            '/newleads' => $this->newLeadsMessage($argument ?: 'today'),
            '/unassigned' => $this->unassignedMessage(),
            '/source' => $this->sourceMessage($argument ?: 'today'),
            '/lead' => $this->leadMessage($argument),
            '/followups' => $this->followUpsMessage($argument ?: 'today'),
            '/stale' => $this->staleMessage($argument),
            '/visitpending' => $this->visitQueueMessage('pending'),
            '/visitcancelled' => $this->visitQueueMessage('cancelled'),
            '/visitverify' => $this->visitQueueMessage('verification'),
            '/visitproof' => $this->visitQueueMessage('proof'),
            '/performance' => $this->performanceMessage($argument ?: 'thisweek', 'performance'),
            '/conversion' => $this->performanceMessage($argument ?: 'thismonth', 'conversion'),
            '/ranking' => $this->performanceMessage($argument ?: 'thisweek', 'ranking'),
            '/closers' => $this->closersMessage($argument ?: 'thismonth'),
            '/user' => $this->userMessage($argument),
            '/errors' => $this->errorsMessage(),
            default => "Command samajh nahi aaya. /menu bhejiye.",
        };
    }

    private function menuMessage(): string
    {
        return implode("\n", [
            'CRM ADMIN BOT',
            '',
            '/today - Aaj ka CRM summary',
            '/present - Aaj ki attendance',
            '/overdue [user] - Previous-day pending calls',
            '/visits today|tomorrow|thisweek|nextweek|user Name',
            '/visitpending | /visitcancelled | /visitverify | /visitproof',
            '/leads today|thisweek|thismonth - User-wise allocation',
            '/newleads today|thisweek|thismonth - Untouched allocations',
            '/unassigned - Unassigned live leads',
            '/source today|thisweek|thismonth - Source report',
            '/lead Phone/Name - Lead lookup',
            '/followups today|tomorrow|overdue',
            '/stale 3 - 3+ din se untouched allocations',
            '/performance today|thisweek|thismonth',
            '/conversion thismonth | /ranking thisweek',
            '/closers today|thisweek|thismonth',
            '/user Name - Ek user ka snapshot',
            '/errors - Aaj ke 500+ errors',
        ]);
    }

    private function todayMessage(): string
    {
        [$start, $end] = $this->range('today');
        $leadBase = Lead::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(fn (Builder $query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'));
        $sources = (clone $leadBase)
            ->select('source', DB::raw('COUNT(*) total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => Lead::displaySourceLabel($row->source).': '.(int) $row->total)
            ->implode("\n");
        $visits = SiteVisit::withQueueHidden()->whereBetween('scheduled_at', [$start, $end]);
        $attendance = AttendanceRecord::query()->whereDate('attendance_date', $start->toDateString());

        return implode("\n", [
            'CRM TODAY SUMMARY',
            now()->format('d M Y, h:i A'),
            '',
            'Leads received: '.(clone $leadBase)->count(),
            $sources ?: 'No leads today',
            '',
            'Lead allocations: '.$this->assignmentBase($start, $end)->count(),
            'Visits scheduled: '.(clone $visits)->count(),
            'Visits done: '.(clone $visits)->where(fn (Builder $query) => $query->where('status', 'completed')->orWhereNotNull('completed_at'))->count(),
            'Visits cancelled: '.(clone $visits)->where('status', 'cancelled')->count(),
            'Present/punched in: '.(clone $attendance)->whereNotNull('first_punch_in_at')->count(),
            'Late: '.(clone $attendance)->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'Absent: '.(clone $attendance)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'On leave: '.(clone $attendance)->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
        ]);
    }

    private function attendanceMessage(): string
    {
        $records = AttendanceRecord::query()
            ->with('user:id,name')
            ->whereDate('attendance_date', today()->toDateString())
            ->get();
        $present = $records->filter(fn ($record) => $record->first_punch_in_at !== null);

        return implode("\n", [
            'CRM ATTENDANCE TODAY',
            now()->format('d M Y, h:i A'),
            '',
            'Present/Punched in: '.$present->count(),
            $this->nameList($present->pluck('user.name')),
            '',
            'Late: '.$records->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'Half day: '.$records->where('status', AttendanceRecord::STATUS_HALF_DAY)->count(),
            'Absent: '.$records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'On leave: '.$records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
        ]);
    }

    private function overdueMessage(string $userSearch = ''): string
    {
        $start = today()->subDay()->startOfDay();
        $end = $start->copy()->endOfDay();
        $query = Task::query()
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$start, $end]);

        if (trim($userSearch) !== '') {
            $query->whereHas('assignedTo', fn (Builder $user) => $user->where('name', 'like', '%'.trim($userSearch).'%'));
        }

        $counts = $query
            ->select('assigned_to', DB::raw('COUNT(*) total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $role) => $role->whereIn('slug', [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ]))
            ->when(trim($userSearch) !== '', fn (Builder $user) => $user->where('name', 'like', '%'.trim($userSearch).'%'))
            ->get(['id', 'name']);
        $rows = $users
            ->map(fn (User $user) => ['name' => $user->name, 'total' => (int) ($counts[$user->id] ?? 0)])
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->map(fn (array $row) => $row['name'].': '.$row['total'])
            ->values();

        return implode("\n", [
            'CRM OVERDUE REPORT',
            'Scope: '.$start->format('d M Y').' ke pending phone-call tasks',
            trim($userSearch) !== '' ? 'User search: '.trim($userSearch) : 'All users',
            '',
            $rows->isEmpty() ? 'No overdue pending' : $rows->take(50)->implode("\n"),
        ]);
    }

    private function visitsMessage(string $period): string
    {
        if (str_starts_with(strtolower(trim($period)), 'user ')) {
            return $this->visitUserMessage(trim(substr(trim($period), 5)));
        }

        [$start, $end, $label] = $this->periodRange($period);
        $rows = SiteVisit::withQueueHidden()
            ->whereBetween('scheduled_at', [$start, $end])
            ->select('assigned_to',
                DB::raw('COUNT(*) total'),
                DB::raw("SUM(CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN 1 ELSE 0 END) done"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) cancelled")
            )
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => sprintf('%s: %d | Done %d | Cancel %d | Pending %d',
                $row->assignedTo?->name ?: ($row->assigned_to ? 'User #'.$row->assigned_to : 'Unassigned'),
                (int) $row->total,
                (int) $row->done,
                (int) $row->cancelled,
                max(0, (int) $row->total - (int) $row->done - (int) $row->cancelled)
            ));

        return implode("\n", [
            'CRM VISIT REPORT',
            $label.' | '.$start->format('d M').' - '.$end->format('d M Y'),
            '',
            $rows->isEmpty() ? 'No visits found' : $rows->take(50)->implode("\n"),
        ]);
    }

    private function leadsMessage(string $period): string
    {
        [$start, $end, $label] = $this->periodRange($period);
        $rows = $this->assignmentBase($start, $end)
            ->select('assigned_to', DB::raw('COUNT(*) total'))
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => ($row->assignedTo?->name ?: 'Unassigned').': '.(int) $row->total);

        return implode("\n", [
            'CRM LEAD ALLOCATION',
            $label.' | '.$start->format('d M').' - '.$end->format('d M Y'),
            'Scope: allocation events to live sales leads',
            '',
            $rows->isEmpty() ? 'No lead allocations found' : $rows->take(50)->implode("\n"),
        ]);
    }

    private function newLeadsMessage(string $period): string
    {
        [$start, $end, $label] = $this->periodRange($period);
        $rows = $this->untouchedAssignments($start, $end)
            ->select('assigned_to', DB::raw('COUNT(*) total'))
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => ($row->assignedTo?->name ?: 'Unassigned').': '.(int) $row->total);

        return implode("\n", [
            'CRM UNTOUCHED NEW LEADS',
            $label.' | '.$start->format('d M').' - '.$end->format('d M Y'),
            'Scope: allocation ke baad koi call/task outcome, follow-up ya progression nahi',
            '',
            $rows->isEmpty() ? 'No untouched leads found' : $rows->take(50)->implode("\n"),
        ]);
    }

    private function unassignedMessage(): string
    {
        $leads = Lead::query()
            ->visibleInAllLeadsInventory()
            ->whereDoesntHave('activeAssignments')
            ->where(fn (Builder $query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'))
            ->whereNotIn('status', ['closed', 'dead', 'junk', 'not_interested'])
            ->where(fn (Builder $query) => $query->whereNull('is_dead')->orWhere('is_dead', false))
            ->latest()
            ->limit(30)
            ->get(['id', 'name', 'phone', 'source', 'created_at']);

        return implode("\n", [
            'CRM UNASSIGNED LIVE LEADS',
            'Total: '.Lead::query()
                ->visibleInAllLeadsInventory()
                ->whereDoesntHave('activeAssignments')
                ->where(fn (Builder $query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'))
                ->whereNotIn('status', ['closed', 'dead', 'junk', 'not_interested'])
                ->where(fn (Builder $query) => $query->whereNull('is_dead')->orWhere('is_dead', false))
                ->count(),
            '',
            $leads->isEmpty() ? 'No unassigned leads' : $leads->map(fn (Lead $lead) => sprintf(
                '#%d %s | %s | %s', $lead->id, $lead->name ?: 'No name', $lead->phone ?: '-', Lead::displaySourceLabel($lead->source)
            ))->implode("\n"),
        ]);
    }

    private function sourceMessage(string $period): string
    {
        [$start, $end, $label] = $this->periodRange($period);
        $rows = Lead::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(fn (Builder $query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'))
            ->select('source', DB::raw('COUNT(*) total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        return implode("\n", [
            'CRM LEAD SOURCE REPORT',
            $label.' | '.$start->format('d M').' - '.$end->format('d M Y'),
            'Total: '.(int) $rows->sum('total'),
            '',
            $rows->isEmpty() ? 'No leads found' : $rows->map(fn ($row) => Lead::displaySourceLabel($row->source).': '.(int) $row->total)->implode("\n"),
        ]);
    }

    private function leadMessage(string $search): string
    {
        $search = trim($search);
        if ($search === '') {
            return 'Phone, lead ID ya name bhejiye. Example: /lead 918860881617';
        }

        $digits = preg_replace('/\D+/', '', $search);
        $leads = Lead::query()
            ->with(['activeAssignments.assignedTo:id,name', 'latestSiteVisit'])
            ->where(function (Builder $query) use ($search, $digits) {
                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
                if ($digits !== '') {
                    $query->orWhere('normalized_phone', $digits)->orWhere('phone', 'like', '%'.$digits.'%');
                }
                $query->orWhere('name', 'like', '%'.$search.'%');
            })
            ->latest()
            ->limit(5)
            ->get();

        return implode("\n", [
            'CRM LEAD SEARCH',
            "Search: {$search}",
            '',
            $leads->isEmpty() ? 'Lead nahi mili' : $leads->map(function (Lead $lead) {
                $owner = $lead->activeAssignments->first()?->assignedTo?->name ?: 'Unassigned';
                $visit = $lead->latestSiteVisit;
                return sprintf("#%d %s\n%s | %s | %s\nOwner: %s | Visit: %s",
                    $lead->id, $lead->name ?: 'No name', $lead->phone ?: '-', Lead::displaySourceLabel($lead->source), $lead->status, $owner, $visit?->status ?: 'None'
                );
            })->implode("\n\n"),
        ]);
    }

    private function followUpsMessage(string $period): string
    {
        $period = strtolower(trim($period));
        if ($period === 'overdue') {
            $query = FollowUp::query()->where('scheduled_at', '<', now())->whereNull('completed_at')->where('status', 'scheduled');
            $label = 'Overdue';
        } else {
            [$start, $end, $label] = $this->periodRange($period);
            $query = FollowUp::query()->whereBetween('scheduled_at', [$start, $end]);
        }

        $rows = $query
            ->select('created_by', DB::raw('COUNT(*) total'), DB::raw("SUM(CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN 1 ELSE 0 END) done"))
            ->groupBy('created_by')
            ->with('creator:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => sprintf('%s: %d | Done %d | Pending %d',
                $row->creator?->name ?: 'Unassigned', (int) $row->total, (int) $row->done, max(0, (int) $row->total - (int) $row->done)
            ));

        return "CRM FOLLOW-UP REPORT\n{$label}\n\n".($rows->isEmpty() ? 'No follow-ups found' : $rows->take(50)->implode("\n"));
    }

    private function staleMessage(string $days): string
    {
        $days = max(1, min(90, (int) ($days ?: 3)));
        $rows = $this->untouchedAssignments(null, today()->subDays($days)->endOfDay())
            ->select('assigned_to', DB::raw('COUNT(*) total'))
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => ($row->assignedTo?->name ?: ($row->assigned_to ? 'User #'.$row->assigned_to : 'Unassigned')).': '.(int) $row->total);

        return "CRM STALE UNTOUCHED LEADS\nAge: {$days}+ days\n\n".($rows->isEmpty() ? 'No stale untouched leads' : $rows->take(50)->implode("\n"));
    }

    private function visitQueueMessage(string $type): string
    {
        $query = SiteVisit::withQueueHidden();
        $label = match ($type) {
            'cancelled' => 'CANCELLED TODAY',
            'verification' => 'VERIFICATION PENDING',
            'proof' => 'COMPLETION PROOF PENDING',
            default => 'OVERDUE/PENDING',
        };
        match ($type) {
            'cancelled' => $query->whereDate('scheduled_at', today())->where('status', 'cancelled'),
            'verification' => $query->where(fn (Builder $q) => $q->where('status', 'completed')->orWhereNotNull('completed_at'))->where(fn (Builder $q) => $q->whereNull('verification_status')->orWhere('verification_status', 'pending')),
            'proof' => $query->where(fn (Builder $q) => $q->where('status', 'completed')->orWhereNotNull('completed_at'))->where(fn (Builder $q) => $q->whereNull('completion_proof_photos')->orWhereRaw("JSON_LENGTH(completion_proof_photos) = 0")),
            default => $query->where('scheduled_at', '<', now())->whereNull('completed_at')->whereNotIn('status', ['completed', 'cancelled']),
        };
        $rows = $query
            ->select('assigned_to', DB::raw('COUNT(*) total'))
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->sortByDesc('total')
            ->map(fn ($row) => ($row->assignedTo?->name ?: 'Unassigned').': '.(int) $row->total);

        return "CRM VISITS {$label}\n\n".($rows->isEmpty() ? 'No visits found' : $rows->take(50)->implode("\n"));
    }

    private function visitUserMessage(string $search): string
    {
        $users = User::query()->where('is_active', true)->where('name', 'like', '%'.$search.'%')->limit(5)->get(['id', 'name']);
        if ($users->count() !== 1) {
            return $users->isEmpty()
                ? "User '{$search}' nahi mila."
                : "Multiple users mile:\n".$users->pluck('name')->implode("\n")."\n\nFull name bhejiye.";
        }

        $user = $users->first();
        [$todayStart, $todayEnd] = $this->range('today');
        [$weekStart, $weekEnd] = $this->range('thisweek');
        [$nextStart, $nextEnd] = $this->range('nextweek');
        $today = SiteVisit::withQueueHidden()->where('assigned_to', $user->id)->whereBetween('scheduled_at', [$todayStart, $todayEnd]);

        return implode("\n", [
            'CRM USER VISITS',
            $user->name,
            '',
            'Today total: '.(clone $today)->count(),
            'Today done: '.(clone $today)->where(fn (Builder $query) => $query->where('status', 'completed')->orWhereNotNull('completed_at'))->count(),
            'Today cancelled: '.(clone $today)->where('status', 'cancelled')->count(),
            'This week total: '.$this->visitCount($user->id, $weekStart, $weekEnd),
            'Next week total: '.$this->visitCount($user->id, $nextStart, $nextEnd),
        ]);
    }

    private function performanceMessage(string $period, string $mode): string
    {
        [$start, $end, $label] = $this->periodRange($period);
        $service = app(AdvisorPerformanceReportService::class);
        $report = $service->build($service->resolvePeriod([
            'period_type' => 'custom',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));
        $rows = collect($report['rows']);

        if ($mode === 'conversion') {
            $rows = $rows->sortByDesc('pp_percent')->map(fn ($row) => sprintf('%s: Leads %d | Booking %d | Lead-to-book %.1f%% | Visit-to-book %.1f%%',
                $row['advisor'], $row['leads'], $row['units'], $row['pp_percent'] * 100, $row['visits'] > 0 ? ($row['units'] / $row['visits']) * 100 : 0
            ));
            $title = 'CRM SALES CONVERSION';
        } elseif ($mode === 'ranking') {
            $rows = $rows->sortByDesc(fn ($row) => [$row['units'], $row['visits'], $row['leads']])->values()->map(fn ($row, $index) => sprintf('%d. %s | Booking %d | Completed Visit %d | Leads %d',
                $index + 1, $row['advisor'], $row['units'], $row['visits'], $row['leads']
            ));
            $title = 'CRM SALES RANKING';
        } else {
            $rows = $rows->sortByDesc('units')->map(fn ($row) => sprintf('%s: Leads %d | Completed Visits %d | F2F %d | Booking %d | Revenue Rs %s',
                $row['advisor'], $row['leads'], $row['visits'], $row['f2f'], $row['units'], number_format($row['revenue_value'])
            ));
            $title = 'CRM SALES PERFORMANCE';
        }

        return "{$title}\n{$label} | {$start->format('d M')} - {$end->format('d M Y')}\n\n".($rows->isEmpty() ? 'No sales activity' : $rows->take(50)->implode("\n"));
    }

    private function closersMessage(string $period): string
    {
        [$start, $end, $label] = $this->periodRange($period);
        $service = app(AdvisorPerformanceReportService::class);
        $report = $service->build($service->resolvePeriod([
            'period_type' => 'custom',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));
        $rows = collect($report['rows'])
            ->where('units', '>', 0)
            ->sortByDesc('units')
            ->map(fn ($row) => sprintf('%s: %d booking | Revenue Rs %s', $row['advisor'], $row['units'], number_format($row['revenue_value'])));

        return implode("\n", [
            'CRM FINANCE-APPROVED SALES',
            $label.' | '.$start->format('d M').' - '.$end->format('d M Y'),
            'Total bookings: '.(int) $report['totals']['units'],
            'Revenue: Rs '.number_format((float) $report['totals']['revenue_value']),
            '',
            $rows->isEmpty() ? 'No approved closers found' : $rows->take(50)->implode("\n"),
        ]);
    }

    private function untouchedAssignments(?Carbon $start, Carbon $end): Builder
    {
        $query = LeadAssignment::query()
            ->activeWithLiveLead()
            ->whereHas('assignedTo', fn (Builder $user) => $user
                ->where('is_active', true)
                ->whereHas('role', fn (Builder $role) => $role->whereIn('slug', [
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                    Role::SALES_EXECUTIVE,
                ])))
            ->whereHas('lead', fn (Builder $lead) => $lead
                ->whereNotIn('status', ['verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed', 'closed', 'dead', 'junk', 'not_interested', 'on_hold'])
                ->whereNull('next_followup_at')
                ->where(fn (Builder $dead) => $dead->whereNull('is_dead')->orWhere('is_dead', false)))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('crm_assignments')
                ->whereColumn('crm_assignments.lead_id', 'lead_assignments.lead_id')
                ->whereColumn('crm_assignments.assigned_to', 'lead_assignments.assigned_to')
                ->where(fn ($response) => $response->where('cnp_count', '>', 0)->orWhere('call_status', '!=', 'pending')))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('follow_ups')
                ->whereColumn('follow_ups.lead_id', 'lead_assignments.lead_id')
                ->whereColumn('follow_ups.created_by', 'lead_assignments.assigned_to')
                ->whereColumn('follow_ups.created_at', '>=', 'lead_assignments.assigned_at')
                ->whereNull('follow_ups.deleted_at'))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('tasks')
                ->whereColumn('tasks.lead_id', 'lead_assignments.lead_id')
                ->where(fn ($user) => $user->whereColumn('tasks.assigned_to', 'lead_assignments.assigned_to')->orWhereColumn('tasks.created_by', 'lead_assignments.assigned_to'))
                ->where(fn ($response) => $response->whereIn('tasks.status', ['in_progress', 'completed'])->orWhereNotNull('tasks.completed_at')->orWhereNotNull('tasks.outcome')->orWhereNotNull('tasks.outcome_recorded_at'))
                ->whereColumn('tasks.created_at', '>=', 'lead_assignments.assigned_at')
                ->whereNull('tasks.deleted_at'))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('telecaller_tasks')
                ->whereColumn('telecaller_tasks.lead_id', 'lead_assignments.lead_id')
                ->where(fn ($user) => $user->whereColumn('telecaller_tasks.assigned_to', 'lead_assignments.assigned_to')->orWhereColumn('telecaller_tasks.created_by', 'lead_assignments.assigned_to'))
                ->where(fn ($response) => $response->whereIn('telecaller_tasks.status', ['in_progress', 'completed'])->orWhereNotNull('telecaller_tasks.completed_at')->orWhereNotNull('telecaller_tasks.outcome'))
                ->whereColumn('telecaller_tasks.created_at', '>=', 'lead_assignments.assigned_at')
                ->whereNull('telecaller_tasks.deleted_at'));

        return $start ? $query->whereBetween('assigned_at', [$start, $end]) : $query->where('assigned_at', '<=', $end);
    }

    private function userMessage(string $search): string
    {
        $search = trim($search);
        if ($search === '') {
            return 'User name bhi bhejiye. Example: /user Naveen Singh';
        }

        $users = User::query()->where('is_active', true)->where('name', 'like', "%{$search}%")->limit(5)->get();
        if ($users->count() !== 1) {
            return $users->isEmpty()
                ? "User '{$search}' nahi mila."
                : "Multiple users mile:\n".$users->pluck('name')->implode("\n")."\n\nFull name bhejiye.";
        }

        $user = $users->first();
        [$todayStart, $todayEnd] = $this->range('today');
        [$weekStart, $weekEnd] = $this->range('thisweek');
        [$nextStart, $nextEnd] = $this->range('nextweek');
        $attendance = AttendanceRecord::query()->where('user_id', $user->id)->whereDate('attendance_date', today())->first();
        $previousDayOverdue = Task::query()
            ->where('assigned_to', $user->id)
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()])
            ->count();

        return implode("\n", [
            'CRM USER SNAPSHOT',
            $user->name,
            '',
            'Attendance: '.($attendance?->status ?: 'No record'),
            'Today lead allocations: '.$this->assignmentBase($todayStart, $todayEnd)->where('assigned_to', $user->id)->count(),
            'Previous-day pending calls: '.$previousDayOverdue,
            'Today visits: '.$this->visitCount($user->id, $todayStart, $todayEnd),
            'This week visits: '.$this->visitCount($user->id, $weekStart, $weekEnd),
            'Next week visits: '.$this->visitCount($user->id, $nextStart, $nextEnd),
        ]);
    }

    private function errorsMessage(): string
    {
        $query = SystemErrorLog::query()->where('status_code', '>=', 500)->whereDate('created_at', today());
        $latest = (clone $query)->latest()->limit(10)->get();

        return implode("\n", [
            'CRM SERVER ERRORS TODAY',
            'Total: '.(clone $query)->count(),
            '',
            $latest->isEmpty() ? 'No 500+ errors today' : $latest->map(fn ($error) => sprintf(
                '%s | %s | %s | %s',
                $error->created_at->format('h:i A'),
                $error->status_code,
                $error->user_name ?: 'Guest',
                $error->path
            ))->implode("\n"),
        ]);
    }

    private function assignmentBase(Carbon $start, Carbon $end): Builder
    {
        return LeadAssignment::query()
            ->whereBetween('assigned_at', [$start, $end])
            ->whereHas('lead', fn (Builder $lead) => $lead
                ->where(fn (Builder $query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate')));
    }

    private function visitCount(int $userId, Carbon $start, Carbon $end): int
    {
        return SiteVisit::withQueueHidden()->where('assigned_to', $userId)->whereBetween('scheduled_at', [$start, $end])->count();
    }

    private function periodRange(string $period): array
    {
        $period = strtolower(trim($period));
        [$start, $end] = $this->range($period);
        $label = match ($period) {
            'tomorrow' => 'Tomorrow',
            'thisweek', 'week' => 'This Week',
            'nextweek' => 'Next Week',
            'thismonth', 'month' => 'This Month',
            default => 'Today',
        };

        return [$start, $end, $label];
    }

    private function range(string $period): array
    {
        return match (strtolower(trim($period))) {
            'tomorrow' => [today()->addDay()->startOfDay(), today()->addDay()->endOfDay()],
            'thisweek', 'week' => [today()->startOfWeek()->startOfDay(), today()->endOfWeek()->endOfDay()],
            'nextweek' => [today()->addWeek()->startOfWeek()->startOfDay(), today()->addWeek()->endOfWeek()->endOfDay()],
            'thismonth', 'month' => [today()->startOfMonth()->startOfDay(), today()->endOfMonth()->endOfDay()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    private function nameList($names): string
    {
        $names = collect($names)->filter()->values();

        return $names->isEmpty()
            ? 'No names'
            : $names->take(30)->implode(', ').($names->count() > 30 ? ' ...' : '');
    }

    private function setup(): int
    {
        $commands = [
            ['command' => 'menu', 'description' => 'CRM admin menu'],
            ['command' => 'today', 'description' => 'Today CRM summary'],
            ['command' => 'present', 'description' => 'Today attendance'],
            ['command' => 'overdue', 'description' => 'Previous-day pending calls'],
            ['command' => 'visits', 'description' => 'Visit report'],
            ['command' => 'leads', 'description' => 'Lead allocation report'],
            ['command' => 'newleads', 'description' => 'Untouched new leads'],
            ['command' => 'unassigned', 'description' => 'Unassigned live leads'],
            ['command' => 'source', 'description' => 'Lead source report'],
            ['command' => 'lead', 'description' => 'Search a lead'],
            ['command' => 'followups', 'description' => 'Follow-up report'],
            ['command' => 'stale', 'description' => 'Stale untouched leads'],
            ['command' => 'visitpending', 'description' => 'Pending visits'],
            ['command' => 'visitcancelled', 'description' => 'Today cancelled visits'],
            ['command' => 'visitverify', 'description' => 'Visit verification pending'],
            ['command' => 'visitproof', 'description' => 'Visit proof pending'],
            ['command' => 'performance', 'description' => 'Sales performance'],
            ['command' => 'conversion', 'description' => 'Sales conversion'],
            ['command' => 'ranking', 'description' => 'Sales ranking'],
            ['command' => 'closers', 'description' => 'Approved bookings'],
            ['command' => 'user', 'description' => 'User snapshot'],
            ['command' => 'errors', 'description' => 'Today server errors'],
        ];
        $commandResponse = Http::timeout(10)->post($this->api('setMyCommands'), ['commands' => $commands]);
        $updates = Http::timeout(10)->get($this->api('getUpdates'), ['limit' => 100])->json('result', []);

        if ($updates !== []) {
            Cache::forever(self::OFFSET_KEY, ((int) collect($updates)->max('update_id')) + 1);
        }

        if (!$commandResponse->successful()) {
            $this->error('Telegram command setup failed: '.$commandResponse->body());
            return self::FAILURE;
        }

        $this->info('Telegram admin commands configured.');
        return $this->send($this->menuMessage()) ? self::SUCCESS : self::FAILURE;
    }

    private function send(string $message): bool
    {
        foreach ($this->messageChunks($message) as $chunk) {
            $response = Http::timeout(10)->post($this->api('sendMessage'), [
                'chat_id' => $this->chatId(),
                'text' => $chunk,
                'disable_web_page_preview' => true,
                'reply_markup' => [
                    'keyboard' => [
                        [['text' => 'Today Summary'], ['text' => 'Attendance']],
                        [['text' => 'Overdue Tasks'], ['text' => 'Today Visits']],
                        [['text' => 'Tomorrow Visits'], ['text' => 'This Week Visits']],
                        [['text' => 'Next Week Visits'], ['text' => 'Lead Allocation']],
                        [['text' => 'New Leads'], ['text' => 'Sales Performance']],
                        [['text' => 'Closers'], ['text' => 'Server Errors']],
                        [['text' => 'Menu']],
                    ],
                    'resize_keyboard' => true,
                ],
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram admin bot send failed', ['body' => $response->body()]);
                return false;
            }
        }

        return true;
    }

    private function messageChunks(string $message): array
    {
        if (mb_strlen($message) <= 3900) {
            return [$message];
        }

        $chunks = [];
        $chunk = '';
        foreach (explode("\n", $message) as $line) {
            if (mb_strlen($chunk."\n".$line) > 3900) {
                $chunks[] = $chunk;
                $chunk = $line;
            } else {
                $chunk .= ($chunk === '' ? '' : "\n").$line;
            }
        }
        if ($chunk !== '') {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function configured(): bool
    {
        if (!config('error_alerts.telegram.enabled') || $this->token() === '' || $this->chatId() === '') {
            $this->error('Telegram bot is disabled or token/chat id is missing.');
            return false;
        }

        return true;
    }

    private function api(string $method): string
    {
        return 'https://api.telegram.org/bot'.$this->token().'/'.$method;
    }

    private function token(): string
    {
        return trim((string) config('error_alerts.telegram.bot_token'));
    }

    private function chatId(): string
    {
        return trim((string) config('error_alerts.telegram.chat_id'));
    }
}
