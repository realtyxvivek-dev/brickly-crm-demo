<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendDailyTelegramReport extends Command
{
    protected $signature = 'crm:send-daily-telegram-report {--date=}';

    protected $description = 'Send daily CRM lead, visit, and overdue summary to Telegram.';

    public function handle(): int
    {
        $day = Carbon::parse($this->option('date') ?: today());

        foreach ($this->messages($day) as $message) {
            if (!$this->sendTelegram($message)) {
                return self::FAILURE;
            }
        }

        $this->info('Daily Telegram report sent.');

        return self::SUCCESS;
    }

    private function messages(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $tomorrowStart = $day->copy()->addDay()->startOfDay();
        $tomorrowEnd = $day->copy()->addDay()->endOfDay();

        $leadBase = Lead::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(fn ($query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'));

        $leadSources = (clone $leadBase)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => Lead::displaySourceLabel($row->source) . ': ' . (int) $row->total)
            ->implode("\n");

        $visitBase = SiteVisit::withQueueHidden();
        $todayScheduled = (clone $visitBase)->whereBetween('scheduled_at', [$start, $end])->count();
        $todayCompleted = (clone $visitBase)->whereBetween('completed_at', [$start, $end])->count();
        $todayCancelled = (clone $visitBase)->whereBetween('scheduled_at', [$start, $end])->where('status', 'cancelled')->count();
        $todayPending = (clone $visitBase)
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereNull('completed_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $tomorrowVisits = (clone $visitBase)
            ->whereBetween('scheduled_at', [$tomorrowStart, $tomorrowEnd])
            ->whereNull('completed_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $visitRows = $this->visitRows($start, $end, $tomorrowStart, $tomorrowEnd);
        $overdueRows = $this->overdueRows($day);

        $summary = implode("\n", array_filter([
            'CRM DAILY SUMMARY',
            'Date: ' . $day->format('d M Y') . ' | 8:00 PM',
            '',
            'LEADS TODAY',
            'Total: ' . (clone $leadBase)->count(),
            $leadSources !== '' ? $leadSources : 'No leads today',
            '',
            'VISITS TODAY',
            'Scheduled: ' . $todayScheduled,
            'Completed: ' . $todayCompleted,
            'Cancelled: ' . $todayCancelled,
            'Pending/Not Done: ' . $todayPending,
            'Tomorrow Scheduled: ' . $tomorrowVisits,
        ]));

        $visits = implode("\n", array_filter([
            'CRM VISIT REPORT',
            'Date: ' . $day->format('d M Y'),
            '',
            'SALES EXECUTIVE VISITS',
            $visitRows !== '' ? $visitRows : 'No visit activity',
        ]));

        $overdue = implode("\n", array_filter([
            'CRM OVERDUE REPORT',
            'Date: ' . $day->format('d M Y'),
            'Scope: Previous-day pending phone-call tasks',
            '',
            'OVERDUE PENDING',
            $overdueRows !== '' ? $overdueRows : 'No overdue pending',
        ]));

        return [$summary, $visits, $overdue];
    }

    private function visitRows(Carbon $start, Carbon $end, Carbon $tomorrowStart, Carbon $tomorrowEnd): string
    {
        $scheduled = $this->siteVisitCounts('scheduled_at', $start, $end);
        $completed = $this->siteVisitCounts('completed_at', $start, $end);
        $cancelled = $this->siteVisitCounts('scheduled_at', $start, $end, fn ($query) => $query->where('status', 'cancelled'));
        $tomorrow = $this->siteVisitCounts('scheduled_at', $tomorrowStart, $tomorrowEnd, fn ($query) => $query->whereNull('completed_at')->whereNotIn('status', ['completed', 'cancelled']));

        $userIds = collect([$scheduled, $completed, $cancelled, $tomorrow])
            ->flatMap(fn ($counts) => $counts->keys())
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return '';
        }

        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds
            ->map(fn ($id) => [
                'name' => $names[$id] ?? 'Unassigned',
                'scheduled' => (int) ($scheduled[$id] ?? 0),
                'completed' => (int) ($completed[$id] ?? 0),
                'cancelled' => (int) ($cancelled[$id] ?? 0),
                'tomorrow' => (int) ($tomorrow[$id] ?? 0),
            ])
            ->sortByDesc(fn ($row) => $row['scheduled'] + $row['completed'] + $row['cancelled'] + $row['tomorrow'])
            ->take(15)
            ->map(fn ($row) => "{$row['name']}: Today {$row['scheduled']} | Done {$row['completed']} | Cancel {$row['cancelled']} | Tomorrow {$row['tomorrow']}")
            ->implode("\n");
    }

    private function siteVisitCounts(string $column, Carbon $start, Carbon $end, ?callable $filter = null)
    {
        $query = SiteVisit::withQueueHidden()->whereBetween($column, [$start, $end]);

        if ($filter) {
            $filter($query);
        }

        return $query
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count);
    }

    private function overdueRows(Carbon $day): string
    {
        $start = $day->copy()->subDay()->startOfDay();
        $end = $day->copy()->subDay()->endOfDay();

        $taskCounts = Task::query()
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $userIds = $taskCounts
            ->keys()
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return '';
        }

        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds
            ->map(fn ($id) => [
                'name' => $names[$id] ?? 'Unassigned',
                'count' => (int) ($taskCounts[$id] ?? 0),
            ])
            ->sortByDesc('count')
            ->take(15)
            ->map(fn ($row) => "{$row['name']}: {$row['count']}")
            ->implode("\n");
    }

    private function sendTelegram(string $message): bool
    {
        if (!config('error_alerts.telegram.enabled')) {
            $this->warn('Telegram alerts are disabled.');
            return false;
        }

        $token = trim((string) config('error_alerts.telegram.bot_token', ''));
        $chatId = trim((string) config('error_alerts.telegram.chat_id', ''));

        if ($token === '' || $chatId === '') {
            $this->error('Telegram token/chat id missing.');
            return false;
        }

        $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => mb_strimwidth($message, 0, 3900, "\n..."),
            'disable_web_page_preview' => true,
        ]);

        if (!$response->successful()) {
            $this->error('Telegram send failed: ' . $response->body());
            return false;
        }

        return true;
    }
}
