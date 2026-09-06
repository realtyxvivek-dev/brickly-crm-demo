<?php

namespace App\Console\Commands;

use App\Models\SystemSettings;
use App\Support\SecurityLockControl;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SecurityLockOn extends Command
{
    protected $signature = 'security-lock:on
        {--owner=vivek.baseinfra@gmail.com : Owner email allowed after secret URL verification}
        {--secret= : Optional fixed secret token for owner bypass URL}';

    protected $description = 'Enable security lock for all users, with owner bypass through a secret URL.';

    public function handle(): int
    {
        $ownerEmail = trim((string) $this->option('owner'));
        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid owner email is required.');
            return self::FAILURE;
        }

        $secret = trim((string) $this->option('secret'));
        if ($secret === '') {
            $secret = Str::random(48);
        }

        SecurityLockControl::write([
            'enabled' => true,
            'owner_email' => $ownerEmail,
            'secret' => $secret,
            'started_at' => now()->toIso8601String(),
        ]);

        SystemSettings::set('security_lock_all_users', '1');
        SystemSettings::set('security_lock_owner_email', $ownerEmail);
        SystemSettings::set('security_lock_owner_secret', $secret);
        SystemSettings::set('security_lock_started_at', now()->toIso8601String());
        SystemSettings::set('security_lock_preview_email', '');

        $this->info('Security lock enabled for all users.');
        $this->line('Owner email: ' . $ownerEmail);
        $this->line('Owner bypass URL: ' . url('/security-owner-access/' . $secret));
        $this->line('Unlock command: php artisan security-lock:off');

        return self::SUCCESS;
    }
}
