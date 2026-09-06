<?php

namespace App\Console\Commands;

use App\Mail\DailyAdminReportMail;
use App\Models\MailDeliveryLog;
use App\Models\Role;
use App\Models\User;
use App\Services\DailyAdminReportService;
use App\Services\MailDeliveryLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyAdminReport extends Command
{
    protected $signature = 'crm:send-daily-admin-report {--date=} {--range=today} {--to=} {--force}';

    protected $description = 'Send the daily ERP-style admin report email and track delivery logs.';

    public function handle(DailyAdminReportService $reportService, MailDeliveryLogger $logger): int
    {
        $date = Carbon::parse($this->option('date') ?: today())->startOfDay();
        $range = in_array((string) $this->option('range'), DailyAdminReportService::VALID_RANGES, true)
            ? (string) $this->option('range')
            : 'today';
        $report = $reportService->buildRange($range, $date);
        $subject = 'Base CRM ERP Report - ' . ($report['range_label'] ?? $date->format('d M Y'));

        $recipients = $this->option('to')
            ? collect([(string) $this->option('to')])
            : User::query()
                ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
                ->where('is_active', true)
                ->whereNotNull('email')
                ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No recipients found.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $email = $recipient instanceof User ? (string) $recipient->email : (string) $recipient;

            if (!$this->option('force') && $this->alreadySent($email, $date, $range)) {
                $logger->markSkipped($logger->createQueued([
                    'mail_type' => MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT,
                    'subject' => $subject,
                    'recipient_email' => $email,
                    'recipient_user_id' => $recipient instanceof User ? $recipient->id : null,
                    'payload_summary' => [
                        'report_date' => $date->toDateString(),
                        'report_range' => $range,
                        'reason' => 'duplicate_prevented',
                    ],
                ]), 'Duplicate report already sent for this date and range.');

                $skipped++;
                $this->line("Skipped duplicate: {$email}");
                continue;
            }

            $log = $logger->sendMailable(
                MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT,
                $subject,
                $recipient,
                new DailyAdminReportMail($report),
                [
                    'report_date' => $date->toDateString(),
                    'report_range' => $range,
                    'range_label' => $report['range_label'] ?? null,
                    'total_leads' => $report['summary']['total_leads'] ?? 0,
                    'high_budget_clients' => $report['summary']['high_budget_clients'] ?? 0,
                    'meetings_scheduled' => $report['summary']['meetings_scheduled'] ?? 0,
                    'visits_scheduled' => $report['summary']['visits_scheduled'] ?? 0,
                ],
                null,
                null
            );

            if ($log->status === MailDeliveryLog::STATUS_SENT) {
                $sent++;
                $this->info("Sent: {$email}");
            } elseif ($log->status === MailDeliveryLog::STATUS_SKIPPED) {
                $skipped++;
                $this->line("Skipped: {$email}");
            } else {
                $failed++;
                $this->error("Failed: {$email} - {$log->error_message}");
            }
        }

        $this->info("Report done for {$range}. Sent: {$sent}, skipped: {$skipped}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function alreadySent(string $email, Carbon $date, string $range): bool
    {
        return MailDeliveryLog::query()
            ->where('mail_type', MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT)
            ->where('recipient_email', $email)
            ->where('status', MailDeliveryLog::STATUS_SENT)
            ->where('payload_summary->report_date', $date->toDateString())
            ->where('payload_summary->report_range', $range)
            ->exists();
    }
}
