<?php

namespace App\Services;

use App\Models\User;
use App\Models\SystemSettings;
use App\Notifications\NewUserWelcomeNotification;
use Illuminate\Support\Facades\Notification;

class NewUserMailService
{
    /**
     * Send welcome email to new user with credentials and details (if setting is on).
     */
    public function sendWelcomeEmailIfEnabled(User $user, string $plainPassword): bool
    {
        if (filter_var(SystemSettings::get('send_welcome_email_to_new_user', '1'), FILTER_VALIDATE_BOOLEAN) === false) {
            return false;
        }

        $user->load(['role', 'manager']);
        $roleName = $user->role ? $user->role->name : '—';
        $managerName = $user->manager ? $user->manager->name : null;

        Notification::sendNow($user, new NewUserWelcomeNotification(
            $user,
            $plainPassword,
            $roleName,
            $managerName
        ));

        return true;
    }

    /**
     * Send credentials email to an existing user (admin-triggered, no setting check).
     */
    public function sendCredentialsEmail(User $user, string $plainPassword): void
    {
        $user->load(['role', 'manager']);
        $roleName = $user->role ? $user->role->name : '—';
        $managerName = $user->manager ? $user->manager->name : null;

        Notification::sendNow($user, new NewUserWelcomeNotification(
            $user,
            $plainPassword,
            $roleName,
            $managerName
        ));
    }
}
