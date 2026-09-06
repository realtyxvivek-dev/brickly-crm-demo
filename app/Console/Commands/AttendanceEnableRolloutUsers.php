<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserAttendanceProfile;
use Illuminate\Console\Command;

class AttendanceEnableRolloutUsers extends Command
{
    protected $signature = 'attendance:enable-rollout-users
                            {emails* : User emails to enable}
                            {--stage=pilot : Rollout stage label}
                            {--disable-others : Disable attendance for all other mapped users}';

    protected $description = 'Enable attendance rollout for selected mapped users';

    public function handle(): int
    {
        $emails = collect((array) $this->argument('emails'))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            $this->error('Provide at least one email.');
            return self::FAILURE;
        }

        $profiles = UserAttendanceProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->whereIn('email', $emails))
            ->get();

        if ($this->option('disable-others')) {
            UserAttendanceProfile::query()->update([
                'attendance_enabled' => false,
                'attendance_rollout_stage' => 'pilot',
            ]);
        }

        $foundEmails = $profiles->pluck('user.email')->map(fn ($email) => strtolower((string) $email))->all();
        $missingEmails = $emails->reject(fn ($email) => in_array($email, $foundEmails, true));

        foreach ($profiles as $profile) {
            $profile->update([
                'attendance_enabled' => true,
                'attendance_rollout_stage' => (string) $this->option('stage'),
            ]);

            $this->line("Enabled attendance rollout for {$profile->user->email}");
        }

        foreach ($missingEmails as $email) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $message = $user
                ? "Skipped {$email}: attendance mapping/profile missing."
                : "Skipped {$email}: user not found.";
            $this->warn($message);
        }

        return self::SUCCESS;
    }
}
