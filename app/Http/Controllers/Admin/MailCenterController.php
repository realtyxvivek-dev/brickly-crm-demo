<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DailyAdminReportMail;
use App\Models\MailDeliveryLog;
use App\Models\Role;
use App\Models\SystemSettings;
use App\Models\User;
use App\Services\DailyAdminReportService;
use App\Services\HighBudgetLeadAlertService;
use App\Services\MailDeliveryLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MailCenterController extends Controller
{
    private const HIGH_BUDGET_RECIPIENT_SETTING = 'high_budget_alert_recipient_user_ids';
    private const HIGH_BUDGET_RECIPIENT_ROLES = [
        Role::ADMIN,
        Role::SALES_MANAGER,
        Role::SENIOR_MANAGER,
        Role::ASSISTANT_SALES_MANAGER,
        'sales_head',
    ];

    public function index(Request $request)
    {
        $query = MailDeliveryLog::query()->with(['recipient', 'creator', 'resendOf'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('mail_type')) {
            $query->where('mail_type', $request->string('mail_type')->value());
        }

        if ($request->filled('recipient')) {
            $query->where('recipient_email', 'like', '%' . $request->string('recipient')->value() . '%');
        }

        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $query->where(function ($inner) use ($search) {
                $inner->where('subject', 'like', $search)
                    ->orWhere('error_message', 'like', $search)
                    ->orWhere('recipient_email', 'like', $search);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();
        $today = today();

        $summary = [
            'sent_today' => MailDeliveryLog::where('status', MailDeliveryLog::STATUS_SENT)->whereDate('created_at', $today)->count(),
            'failed_today' => MailDeliveryLog::where('status', MailDeliveryLog::STATUS_FAILED)->whereDate('created_at', $today)->count(),
            'queued' => MailDeliveryLog::where('status', MailDeliveryLog::STATUS_QUEUED)->count(),
            'month_total' => MailDeliveryLog::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];

        return view('admin.mail-center.index', [
            'logs' => $logs,
            'summary' => $summary,
            'typeLabels' => MailDeliveryLog::typeLabels(),
            'statusLabels' => MailDeliveryLog::statusLabels(),
            'filters' => $request->only(['status', 'mail_type', 'recipient', 'search', 'date_from', 'date_to']),
            'adminRecipients' => $this->adminRecipients(),
            'highBudgetRecipients' => $this->highBudgetRecipients(),
            'selectedHighBudgetRecipientIds' => $this->selectedHighBudgetRecipientIds(),
        ]);
    }

    public function show(MailDeliveryLog $mailLog)
    {
        $mailLog->load(['recipient', 'creator', 'resendOf']);

        return view('admin.mail-center.show', compact('mailLog'));
    }

    public function previewDailyReport(Request $request, DailyAdminReportService $reportService)
    {
        $report = $this->buildReportFromRequest($request, $reportService);

        return view('emails.daily-admin-report', compact('report'));
    }

    public function sendDailyReport(Request $request, DailyAdminReportService $reportService, MailDeliveryLogger $logger)
    {
        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'report_range' => ['required', Rule::in(DailyAdminReportService::VALID_RANGES)],
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $recipient = $this->adminRecipients()->firstWhere('id', (int) $validated['recipient_user_id']);

        if (!$recipient) {
            return back()->with('error', 'Selected admin recipient valid nahi hai.');
        }

        $date = Carbon::parse($validated['report_date'])->startOfDay();
        $log = $this->sendDailyReportToRecipient($recipient, $date, $reportService, $logger, $validated['report_range']);

        return redirect()
            ->route('admin.mail-center.show', $log)
            ->with(
                $log->status === MailDeliveryLog::STATUS_SENT ? 'success' : 'error',
                $log->status === MailDeliveryLog::STATUS_SENT
                    ? 'Daily report selected admin ko send ho gaya.'
                    : 'Daily report send fail hua. Error detail log me check karo.'
            );
    }

    public function updateHighBudgetRecipients(Request $request)
    {
        $eligibleIds = $this->highBudgetRecipients()->pluck('id')->all();

        $validated = $request->validate([
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['integer', Rule::in($eligibleIds)],
        ]);

        $ids = collect($validated['recipient_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        SystemSettings::set(self::HIGH_BUDGET_RECIPIENT_SETTING, json_encode($ids));

        return back()->with('success', $ids
            ? 'High budget alert recipients update ho gaye.'
            : 'High budget recipients reset ho gaye. Empty setting par active admins fallback rahenge.'
        );
    }

    public function sendHighBudgetTest(HighBudgetLeadAlertService $alertService)
    {
        $logs = $alertService->sendLatestTestAlert();

        if ($logs->isEmpty()) {
            return back()->with('error', 'Latest 2 Cr+ lead/site visit nahi mila ya recipients configured nahi hain.');
        }

        $sent = $logs->where('status', MailDeliveryLog::STATUS_SENT)->count();
        $failed = $logs->where('status', MailDeliveryLog::STATUS_FAILED)->count();

        return redirect()
            ->route('admin.mail-center.index', ['mail_type' => MailDeliveryLog::TYPE_HIGH_BUDGET_LEAD_ALERT])
            ->with(
                $failed > 0 ? 'error' : 'success',
                "High budget test mail sent: {$sent}, failed: {$failed}."
            );
    }

    public function downloadDailyReportPdf(Request $request, DailyAdminReportService $reportService)
    {
        $report = $this->buildReportFromRequest($request, $reportService);
        $filename = 'base-crm-daily-report-' . str($report['range_label'] ?? now()->format('d-m-Y'))->slug('-') . '.pdf';
        $html = view('emails.daily-admin-report', compact('report'))->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download($filename);
        }

        if (class_exists('PDF')) {
            return \PDF::loadHTML($html)->download($filename);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"',
        ]);
    }

    public function resend(MailDeliveryLog $mailLog, DailyAdminReportService $reportService, MailDeliveryLogger $logger)
    {
        if ($mailLog->mail_type !== MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT) {
            return back()->with('error', 'Resend currently daily admin report ke liye enabled hai.');
        }

        $reportDate = $mailLog->payload_summary['report_date'] ?? $mailLog->created_at?->toDateString();
        $date = Carbon::parse($reportDate ?: today());
        $range = $mailLog->payload_summary['report_range'] ?? 'today';
        $newLog = $this->sendDailyReportToEmail((string) $mailLog->recipient_email, $date, $reportService, $logger, $mailLog->id, $range);

        return redirect()
            ->route('admin.mail-center.show', $newLog)
            ->with($newLog->status === MailDeliveryLog::STATUS_SENT ? 'success' : 'error', $newLog->status === MailDeliveryLog::STATUS_SENT ? 'Mail resend ho gaya.' : 'Mail resend fail hua.');
    }

    private function adminRecipients()
    {
        return User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'is_active']);
    }

    private function highBudgetRecipients()
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::HIGH_BUDGET_RECIPIENT_ROLES))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'is_active']);
    }

    private function selectedHighBudgetRecipientIds(): array
    {
        $value = SystemSettings::get(self::HIGH_BUDGET_RECIPIENT_SETTING);

        if (is_array($value)) {
            return collect($value)->map(fn ($id) => (int) $id)->all();
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return collect($decoded)->map(fn ($id) => (int) $id)->all();
        }

        return collect(preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function sendDailyReportToRecipient(User $recipient, Carbon $date, DailyAdminReportService $reportService, MailDeliveryLogger $logger, string $range = 'today'): MailDeliveryLog
    {
        return $this->sendDailyReportToEmail($recipient, $date, $reportService, $logger, null, $range);
    }

    private function sendDailyReportToEmail(User|string $recipient, Carbon $date, DailyAdminReportService $reportService, MailDeliveryLogger $logger, ?int $resendOfLogId = null, string $range = 'today'): MailDeliveryLog
    {
        $report = $reportService->buildRange($range, $date);
        $subject = 'Base CRM ERP Report - ' . ($report['range_label'] ?? $date->format('d M Y'));

        return $logger->sendMailable(
            MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT,
            $subject,
            $recipient,
            new DailyAdminReportMail($report),
            [
                'report_date' => $date->toDateString(),
                'report_range' => $range,
                'range_label' => $report['range_label'] ?? null,
                'total_leads' => $report['summary']['total_leads'] ?? 0,
                'meetings_scheduled' => $report['summary']['meetings_scheduled'] ?? 0,
                'visits_scheduled' => $report['summary']['visits_scheduled'] ?? 0,
                'manual_send' => true,
            ],
            null,
            auth()->id(),
            $resendOfLogId
        );
    }

    private function buildReportFromRequest(Request $request, DailyAdminReportService $reportService): array
    {
        $date = Carbon::parse($request->input('date', $request->input('report_date', today())))->startOfDay();
        $range = $request->input('range', $request->input('report_range', 'today'));

        return $reportService->buildRange((string) $range, $date);
    }
}
