<?php

namespace App\Console\Commands;

use App\Models\MailDeliveryLog;
use App\Models\PostSaleDemand;
use App\Services\MailDeliveryLogger;
use Illuminate\Console\Command;

class SendPostSalesDemandReminders extends Command
{
    protected $signature = 'post-sales:send-demand-reminders';
    protected $description = 'Send enabled Post Sales customer demand reminders';

    public function handle(MailDeliveryLogger $logger): int
    {
        if (!config('post_sales.customer_mail_enabled', false)) {
            $this->info('Customer delivery is disabled. No email sent.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach (config('post_sales.reminder_days', [7, 3, 0]) as $days) {
            $demands = PostSaleDemand::with('postSaleCase')->whereDate('due_date', now()->addDays($days)->toDateString())
                ->whereIn('status', ['upcoming', 'due', 'part_paid', 'overdue'])
                ->whereHas('postSaleCase', fn ($query) => $query->where('status', 'active')->where('reminders_enabled', true)->whereNotNull('customer_email'))
                ->get();
            foreach ($demands as $demand) {
                $logKey = 'day_'.$days;
                $reminderLog = (array) $demand->reminder_log;
                if (!empty($reminderLog[$logKey])) continue;
                $log = $logger->sendView(
                    MailDeliveryLog::TYPE_POST_SALE_DEMAND_REMINDER,
                    'Payment demand reminder - '.$demand->postSaleCase->project_name,
                    $demand->postSaleCase->customer_email,
                    'emails.post-sale-demand',
                    ['demand' => $demand, 'isTest' => false],
                    ['demand_id' => $demand->id, 'days_before_due' => $days],
                    $demand
                );
                if ($log->status === MailDeliveryLog::STATUS_SENT) {
                    $reminderLog[$logKey] = now()->toIso8601String();
                    $demand->update(['reminder_log' => $reminderLog]);
                    $sent++;
                }
            }
        }
        $this->info("Sent {$sent} customer reminders.");
        return self::SUCCESS;
    }
}
