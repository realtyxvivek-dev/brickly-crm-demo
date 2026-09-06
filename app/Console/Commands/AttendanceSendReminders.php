<?php

namespace App\Console\Commands;

use App\Jobs\SendFcmNotificationJob;
use App\Models\AppNotification;
use App\Models\AttendanceEvent;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceRecord;
use App\Models\AttendanceWeekoff;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceAccessService;
use Illuminate\Console\Command;

class AttendanceSendReminders extends Command
{
    protected $signature = 'attendance:send-reminders {slot : morning|primary|second|warning|final|punchout}';

    protected $description = 'Send attendance reminder notifications for a given slot';

    public function handle(AttendanceAccessService $attendanceAccessService): int
    {
        $slot = $this->argument('slot');
        $today = now()->toDateString();

        if ($slot === 'punchout') {
            $this->info('Punch-out reminder notifications are disabled.');

            return self::SUCCESS;
        }

        $timeLabel = match ($slot) {
            'morning' => 'Good morning. Please punch in by 9:45 AM.',
            'primary' => 'Punch in is pending. Please punch in now.',
            'second' => 'Reminder: your punch in is still pending.',
            'warning' => 'Urgent: punch in is still pending. Please punch in now.',
            'final' => 'Final punch-in reminder for today. Please punch in now.',
            'punchout' => 'Please complete your punch-out for today.',
            default => 'Attendance reminder.',
        };
        $title = $slot === 'punchout' ? 'Punch Out Reminder' : 'Punch In Reminder';
        $attendanceUrl = url('/dashboard#attendanceWidget');

        $users = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereIn('id', $attendanceAccessService->enabledProfilesQuery(now())->select('user_id'))
            ->whereHas('role', fn ($query) => $query->where('slug', '!=', Role::ADMIN))
            ->get();

        foreach ($users as $user) {
            if ($this->shouldSkip($user, $today, $slot)) {
                continue;
            }

            $alreadySent = AttendanceEvent::query()
                ->where('user_id', $user->id)
                ->where('event_date', $today)
                ->where('event_type', AttendanceEvent::TYPE_REMINDER_SENT)
                ->where('meta_json->slot', $slot)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            AttendanceEvent::create([
                'user_id' => $user->id,
                'event_date' => $today,
                'event_type' => AttendanceEvent::TYPE_REMINDER_SENT,
                'event_time' => now(),
                'source' => 'scheduler',
                'meta_json' => ['slot' => $slot],
            ]);

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'attendance_reminder',
                'title' => $title,
                'message' => $timeLabel,
                'data' => [
                    'kind' => 'attendance_reminder',
                    'slot' => $slot,
                    'attendance_date' => $today,
                    'popup_type' => $slot === 'punchout' ? 'punch_out' : 'punch_in',
                    'primary_action_label' => $slot === 'punchout' ? 'Punch Out Now' : 'Punch In Now',
                    'primary_action_url' => $attendanceUrl,
                    'primary_action_method' => 'GET',
                    'secondary_action_label' => 'Open Attendance',
                    'secondary_action_url' => $attendanceUrl,
                    'secondary_action_method' => 'GET',
                    'dismiss_label' => 'Dismiss',
                ],
                'action_type' => AppNotification::ACTION_ATTENDANCE,
                'action_url' => $attendanceUrl,
            ]);

            SendFcmNotificationJob::dispatch(
                $user->id,
                $title,
                $timeLabel,
                $attendanceUrl,
                'attendance-reminder-' . $today . '-' . $slot . '-' . $user->id,
                [
                    'notification_id' => $notification->id,
                    'notification_type' => 'attendance_reminder',
                    'kind' => 'attendance_reminder',
                    'slot' => $slot,
                    'attendance_date' => $today,
                    'popup_type' => $slot === 'punchout' ? 'punch_out' : 'punch_in',
                    'primary_action_label' => $slot === 'punchout' ? 'Punch Out Now' : 'Punch In Now',
                    'primary_action_url' => $attendanceUrl,
                    'primary_action_method' => 'GET',
                    'secondary_action_label' => 'Open Attendance',
                    'secondary_action_url' => $attendanceUrl,
                    'secondary_action_method' => 'GET',
                    'dismiss_label' => 'Dismiss',
                ]
            );
        }

        return self::SUCCESS;
    }

    private function shouldSkip(User $user, string $date, string $slot): bool
    {
        $record = AttendanceRecord::query()->where('user_id', $user->id)->where('attendance_date', $date)->first();

        if ($slot === 'punchout') {
            return !$record?->first_punch_in_at || $record->last_punch_out_at !== null;
        }

        if ($record?->first_punch_in_at || $record?->manual_first_punch_in_at) {
            return true;
        }

        if ($record && in_array($record->status, [
            AttendanceRecord::STATUS_PRESENT,
            AttendanceRecord::STATUS_LATE,
            AttendanceRecord::STATUS_HALF_DAY,
            AttendanceRecord::STATUS_WEEK_OFF,
            AttendanceRecord::STATUS_HOLIDAY,
            AttendanceRecord::STATUS_LEAVE,
        ], true)) {
            return true;
        }

        if (AttendanceHoliday::whereDate('holiday_date', $date)->exists()) {
            return true;
        }

        if (AttendanceWeekoff::where('user_id', $user->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->exists()) {
            return true;
        }

        return false;
    }
}
