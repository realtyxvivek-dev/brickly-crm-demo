<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\EmployeeAsset;
use App\Models\User;
use App\Notifications\EmployeeWelcomeOnboardingNotification;
use App\Services\EmployeeMasterService;
use Illuminate\Console\Command;

class RunEmployeeAutomations extends Command
{
    protected $signature = 'employee:run-automations';

    protected $description = 'Run employee lifecycle, document, salary, and probation automations';

    public function handle(EmployeeMasterService $employeeMasterService): int
    {
        $today = now()->startOfDay();
        $hrUsers = User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'hr_manager']))
            ->get();

        EmployeeProfile::query()
            ->with(['user.role', 'department', 'designation', 'documents', 'assets', 'exitWorkflow'])
            ->chunkById(100, function ($profiles) use ($today, $hrUsers, $employeeMasterService) {
                foreach ($profiles as $profile) {
                    $user = $profile->user;
                    if (!$user) {
                        continue;
                    }

                    if ($profile->joining_date?->isSameDay($today) && !$profile->welcome_email_sent_at) {
                        $user->notify(new EmployeeWelcomeOnboardingNotification(
                            $user->name,
                            $profile->designation?->name,
                            $profile->department?->name
                        ));

                        $employeeMasterService->recordTimeline($profile, $user, 'welcome_sent', 'Welcome email sent', 'Joining automation sent.');
                        $profile->forceFill(['welcome_email_sent_at' => now()])->save();
                    }

                    if ($profile->salary_day_of_month && (int) $profile->salary_day_of_month === (int) now()->day && !$profile->salary_reminder_sent_on?->isSameDay($today)) {
                        $employeeMasterService->createAutomationNotification(
                            $user,
                            'Salary Date Reminder',
                            'Your salary date reminder is due today.',
                            route('attendance.payslips'),
                            ['kind' => 'salary_reminder']
                        );

                        $profile->forceFill(['salary_reminder_sent_on' => $today])->save();
                    }

                    $missingDocumentTypes = $profile->missingDocumentTypes();
                    if (!empty($missingDocumentTypes) && !$profile->document_alert_sent_at?->isSameDay($today)) {
                        foreach ($hrUsers as $hrUser) {
                            $employeeMasterService->createAutomationNotification(
                                $hrUser,
                                'Missing employee documents',
                                $user->name . ' is missing required documents: ' . implode(', ', $missingDocumentTypes),
                                $this->employeeUrlFor($hrUser, $user),
                                ['kind' => 'missing_documents', 'employee_user_id' => $user->id]
                            );
                        }

                        $profile->forceFill(['document_alert_sent_at' => now()])->save();
                    }

                    if ($profile->probation_end_date && $profile->probation_end_date->between($today, $today->copy()->addDays(7)) && !$profile->probation_alert_sent_at) {
                        foreach ($hrUsers as $hrUser) {
                            $employeeMasterService->createAutomationNotification(
                                $hrUser,
                                'Probation ending soon',
                                $user->name . ' probation ends on ' . $profile->probation_end_date->format('d M Y'),
                                $this->employeeUrlFor($hrUser, $user),
                                ['kind' => 'probation_end', 'employee_user_id' => $user->id]
                            );
                        }

                        $profile->forceFill(['probation_alert_sent_at' => now()])->save();
                    }

                    $workflow = $profile->exitWorkflow;
                    if ($workflow && !$workflow->closed_at && $workflow->status === 'on_notice' && !$workflow->last_notice_reminder_sent_at?->isSameDay($today)) {
                        foreach ($hrUsers as $hrUser) {
                            $employeeMasterService->createAutomationNotification(
                                $hrUser,
                                'On notice employee follow-up',
                                $user->name . ' is on notice and needs exit tracking follow-up.',
                                $this->employeeUrlFor($hrUser, $user),
                                ['kind' => 'on_notice_followup', 'employee_user_id' => $user->id]
                            );
                        }

                        $workflow->forceFill(['last_notice_reminder_sent_at' => now()])->save();
                    }

                    $issuedAssets = $profile->assets->where('status', EmployeeAsset::STATUS_ISSUED)->count();
                    if ($workflow && !$workflow->closed_at && $issuedAssets > 0 && !$workflow->last_asset_alert_sent_at?->isSameDay($today)) {
                        foreach ($hrUsers as $hrUser) {
                            $employeeMasterService->createAutomationNotification(
                                $hrUser,
                                'Pending asset return',
                                $user->name . ' still has ' . $issuedAssets . ' issued asset(s) to return.',
                                $this->employeeUrlFor($hrUser, $user),
                                ['kind' => 'pending_asset_return', 'employee_user_id' => $user->id]
                            );
                        }

                        $workflow->forceFill(['last_asset_alert_sent_at' => now()])->save();
                    }
                }
            });

        $this->info('Employee automations completed.');

        return self::SUCCESS;
    }

    private function employeeUrlFor(User $viewer, User $employee): string
    {
        return route(
            $viewer->isHrManager() ? 'hr-manager.settings.hr.employees.show' : 'admin.hr.employees.show',
            $employee
        );
    }
}
