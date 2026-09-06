<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (file_exists(storage_path('framework/crm-relief-mode'))) {
            return;
        }

        // Sync Google Sheets every minute (command checks intervals internally)
        $schedule->command('google-sheets:sync')->everyMinute();
        
        // Move overdue calls to pending
        $schedule->command('telecaller:move-overdue-to-pending')->everyMinute();
        
        // Send 10-minute reminder notifications
        $schedule->command('telecaller:send-reminder-notifications')->everyMinute();

        // Send manager retry-call reminder notifications 15 minutes before due time
        $schedule->command('notifications:manager-task-reminders')->everyMinute();
        
        // Send follow-up reminder notifications 15 minutes and 5 minutes before due time
        $schedule->command('notifications:followup-reminders')->everyMinute();

        // Send meeting reminder notifications 15 minutes and 5 minutes before due time
        $schedule->command('notifications:meeting-reminders')->everyMinute();

        // Send site visit reminder notifications 15 minutes and 5 minutes before due time
        $schedule->command('notifications:site-visit-reminders')->everyMinute();

        // Same-day and outcome reminders for meetings and site visits
        $schedule->command('notifications:activity-lifecycle-reminders')->everyMinute();

        // Notify responsible users and managers about overdue tasks
        $schedule->command('notifications:overdue-tasks')->everyMinute();

        // Notify responsible users and managers about overdue follow-ups
        $schedule->command('notifications:overdue-followups')->everyMinute()->withoutOverlapping();

        // Execution Desk due and overdue reminders
        $schedule->command('notifications:execution-task-reminders')->everyMinute();

        // Private self todo reminders pilot
        $schedule->command('notifications:self-todo-reminders')->everyMinute();

        // Daily ERP email report for admins
        $schedule->command('crm:send-daily-admin-report')->dailyAt('21:00')->withoutOverlapping();
        $schedule->command('crm:send-daily-telegram-report')->dailyAt('20:00')->withoutOverlapping();
        $schedule->command('crm:telegram-admin-bot')->everyMinute()->withoutOverlapping();

        // Backup reconciliation for ASM fresh lead CNP automation
        $schedule->command('asm-cnp:process')->everyFifteenMinutes()->withoutOverlapping();

        // Lead Bank temporary allocation lifecycle
        $schedule->command('lead-bank:sync-protected --limit=500')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('lead-bank:recall-expired --cooldown-days=7')->hourly()->withoutOverlapping();

        // Calling Center one-by-one MCube queue
        $schedule->command('calling-center:process')->everyMinute()->withoutOverlapping();

        // Process new lead response SLA automation every 5 minutes
        $schedule->command('new-lead-sla:process')->everyFiveMinutes();

        // Process delayed and quiet-hour WhatsApp automation sends
        $schedule->command('whatsapp-automation:process --limit=100')->everyMinute();
        $schedule->command('meta-queue:process --max-jobs=20 --tries=3 --timeout=120')->everyMinute()->withoutOverlapping();
        $schedule->command('waba-campaigns:process --limit=50')->everyMinute()->withoutOverlapping();
        $schedule->command('meta-ads:sync-insights')->hourly()->withoutOverlapping();
        $schedule->command('facebook-lead-ads:sync-forms')->everyThirtyMinutes()->withoutOverlapping();

        // Refresh Instagram long-lived access tokens before expiry
        $schedule->command('instagram:refresh-tokens')->dailyAt('02:20');
        
        // Reset daily limits at midnight
        $schedule->job(new \App\Jobs\ResetDailyLimitsJob)->dailyAt('00:00');
        
        // Auto-assign unassigned leads after reset (at 00:05)
        $schedule->job(new \App\Jobs\AutoAssignUnassignedLeadsJob)->dailyAt('00:05');
        
        // Generate recurring tasks daily at 1 AM
        $schedule->command('tasks:generate-recurring')->dailyAt('01:00');

        $schedule->command('attendance:send-reminders second')->dailyAt('09:59')->withoutOverlapping();
        $schedule->command('attendance:finalize-day')->dailyAt('16:05');
        $schedule->command('attendance:finalize-day')->dailyAt('23:50');
        $schedule->command('attendance:detect-suspicion')->dailyAt('23:55');
        $schedule->command('employee:run-automations')->dailyAt('09:15');

        // Knowledge base due soon / due today / overdue reminders
        $schedule->command('knowledge-base:send-reminders')->cron('0 */6 * * *');

        // Safe by default: command exits without sending until POST_SALES_CUSTOMER_MAIL_ENABLED=true.
        $schedule->command('post-sales:send-demand-reminders')->dailyAt('10:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
