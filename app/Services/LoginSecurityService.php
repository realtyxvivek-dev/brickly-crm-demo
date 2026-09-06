<?php

namespace App\Services;

use App\Mail\LoginSecurityAlertMail;
use App\Models\AppNotification;
use App\Models\LoginSecurityEvent;
use App\Models\OtpLoginRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoginSecurityService
{
    public const PREF_KEY = 'login_security';
    public const FIRST_LOCK_AT = 3;
    public const SECOND_LOCK_AT = 6;
    public const ADMIN_LOCK_AT = 9;

    public function rememberAttempt(Request $request, string $email): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $normalized = $this->normalizeEmail($email);
        $request->session()->put('login_security.email_normalized', $normalized);
        $request->session()->put('login_security.masked_email', app(EmailOtpLoginService::class)->maskEmailForDisplay($normalized));
    }

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function lockState(?User $user): array
    {
        if (!$user) {
            return [
                'locked' => false,
                'admin_locked' => false,
                'lock_level' => LoginSecurityEvent::LOCK_NONE,
                'lock_until' => null,
                'failed_count' => 0,
            ];
        }

        $security = $this->securityPreferences($user);
        $adminLocked = !empty($security['admin_locked_at']);
        $lockUntil = !empty($security['lock_until']) ? \Illuminate\Support\Carbon::parse($security['lock_until']) : null;
        $locked = $adminLocked || ($lockUntil && $lockUntil->isFuture());

        if ($lockUntil && $lockUntil->isPast() && !$adminLocked) {
            $security['lock_until'] = null;
            $security['lock_level'] = LoginSecurityEvent::LOCK_NONE;
            $this->saveSecurityPreferences($user, $security);
        }

        return [
            'locked' => $locked,
            'admin_locked' => $adminLocked,
            'lock_level' => $security['lock_level'] ?? LoginSecurityEvent::LOCK_NONE,
            'lock_until' => $adminLocked ? null : $lockUntil,
            'failed_count' => (int) ($security['failed_count'] ?? 0),
        ];
    }

    public function isLocked(?User $user): bool
    {
        return $this->lockState($user)['locked'];
    }

    public function recordFailure(Request $request, ?User $user, string $email, string $method): array
    {
        $normalized = $this->normalizeEmail($email);
        $this->rememberAttempt($request, $normalized);

        if (!$user) {
            LoginSecurityEvent::create($this->baseEventPayload($request, null, $normalized, LoginSecurityEvent::TYPE_FAILED, $method));

            return $this->lockState(null);
        }

        $security = $this->securityPreferences($user);
        $failedCount = ((int) ($security['failed_count'] ?? 0)) + 1;
        $security['failed_count'] = $failedCount;
        $security['last_failed_at'] = now()->toDateTimeString();

        $lockLevel = LoginSecurityEvent::LOCK_NONE;
        $lockUntil = null;
        $lockedNow = false;

        if ($failedCount >= self::ADMIN_LOCK_AT) {
            $lockLevel = LoginSecurityEvent::LOCK_ADMIN;
            $security['admin_locked_at'] = now()->toDateTimeString();
            $security['lock_until'] = null;
            $security['lock_level'] = $lockLevel;
            $lockedNow = true;
        } elseif ($failedCount === self::SECOND_LOCK_AT) {
            $lockLevel = LoginSecurityEvent::LOCK_FIFTEEN_MIN;
            $lockUntil = now()->addMinutes(15);
            $security['lock_until'] = $lockUntil->toDateTimeString();
            $security['lock_level'] = $lockLevel;
            $lockedNow = true;
        } elseif ($failedCount === self::FIRST_LOCK_AT) {
            $lockLevel = LoginSecurityEvent::LOCK_TWO_MIN;
            $lockUntil = now()->addMinutes(2);
            $security['lock_until'] = $lockUntil->toDateTimeString();
            $security['lock_level'] = $lockLevel;
            $lockedNow = true;
        }

        $this->saveSecurityPreferences($user, $security);

        LoginSecurityEvent::create($this->baseEventPayload($request, $user, $normalized, LoginSecurityEvent::TYPE_FAILED, $method, [
            'lock_level' => $lockLevel,
            'failed_count' => $failedCount,
            'lock_until' => $lockUntil,
        ]));

        if ($lockedNow) {
            $event = LoginSecurityEvent::create($this->baseEventPayload($request, $user, $normalized, LoginSecurityEvent::TYPE_LOCKED, $method, [
                'lock_level' => $lockLevel,
                'failed_count' => $failedCount,
                'lock_until' => $lockUntil,
                'status' => $lockLevel === LoginSecurityEvent::LOCK_ADMIN ? 'admin_locked' : 'locked',
            ]));
            $this->sendLockAlerts($event);
        }

        return $this->lockState($user->fresh());
    }

    public function clearFailures(User $user): void
    {
        $security = $this->securityPreferences($user);
        $security['failed_count'] = 0;
        $security['lock_until'] = null;
        $security['lock_level'] = LoginSecurityEvent::LOCK_NONE;
        $security['last_success_at'] = now()->toDateTimeString();
        unset($security['admin_locked_at']);
        $this->saveSecurityPreferences($user, $security);

        LoginSecurityEvent::create([
            'user_id' => $user->id,
            'email_normalized' => $this->normalizeEmail((string) $user->email),
            'event_type' => LoginSecurityEvent::TYPE_SUCCESS,
            'lock_level' => LoginSecurityEvent::LOCK_NONE,
            'status' => 'recorded',
        ]);
    }

    public function createAccessRequest(Request $request): LoginSecurityEvent
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'selfie_data' => ['nullable', 'string'],
        ]);

        $normalized = $this->normalizeEmail($validated['email']);
        $user = app(EmailOtpLoginService::class)->resolveActiveUserByEmail($normalized);
        $state = $this->lockState($user);
        $selfiePath = $this->storeSelfie($validated['selfie_data'] ?? null, $normalized);

        $event = LoginSecurityEvent::create($this->baseEventPayload($request, $user, $normalized, LoginSecurityEvent::TYPE_ACCESS_REQUEST, null, [
            'lock_level' => $state['lock_level'],
            'failed_count' => $state['failed_count'],
            'lock_until' => $state['lock_until'],
            'status' => 'pending',
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'selfie_path' => $selfiePath,
            'reason' => $validated['reason'],
        ]));

        $this->notifyAdmins($event, 'Urgent login access requested', 'A user requested urgent access after failed login attempts.');

        return $event;
    }

    public function unlock(User $user, User $admin, ?LoginSecurityEvent $event = null): void
    {
        $security = $this->securityPreferences($user);
        $security['failed_count'] = 0;
        $security['lock_until'] = null;
        $security['lock_level'] = LoginSecurityEvent::LOCK_NONE;
        unset($security['admin_locked_at']);
        $security['unlocked_at'] = now()->toDateTimeString();
        $security['unlocked_by'] = $admin->id;
        $this->saveSecurityPreferences($user, $security);

        OtpLoginRequest::query()
            ->where('user_id', $user->id)
            ->where('status', OtpLoginRequest::STATUS_PENDING)
            ->update([
                'status' => OtpLoginRequest::STATUS_INVALIDATED,
                'invalidated_at' => now(),
            ]);

        if ($event) {
            $event->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        }

        LoginSecurityEvent::create([
            'user_id' => $user->id,
            'email_normalized' => $this->normalizeEmail((string) $user->email),
            'event_type' => LoginSecurityEvent::TYPE_UNLOCKED,
            'lock_level' => LoginSecurityEvent::LOCK_NONE,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(LoginSecurityEvent $event, User $admin): void
    {
        $event->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function redirectToLock(Request $request, ?User $user, string $email)
    {
        $this->rememberAttempt($request, $email);

        return redirect()->route('login.security-lock');
    }

    public function jsonLockedResponse(?User $user)
    {
        $state = $this->lockState($user);

        return response()->json([
            'message' => $state['admin_locked']
                ? 'Account is locked. Admin approval is required.'
                : 'Too many failed login attempts. Please wait before trying again.',
            'lock_level' => $state['lock_level'],
            'lock_until' => optional($state['lock_until'])->toIso8601String(),
            'admin_locked' => $state['admin_locked'],
        ], 423);
    }

    public function securityPreferences(User $user): array
    {
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];

        return is_array($preferences[self::PREF_KEY] ?? null) ? $preferences[self::PREF_KEY] : [];
    }

    protected function saveSecurityPreferences(User $user, array $security): void
    {
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $preferences[self::PREF_KEY] = $security;
        $user->forceFill(['ui_preferences' => $preferences])->save();
    }

    protected function baseEventPayload(Request $request, ?User $user, string $email, string $eventType, ?string $method, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user?->id,
            'email_normalized' => $this->normalizeEmail($email),
            'event_type' => $eventType,
            'login_method' => $method,
            'lock_level' => LoginSecurityEvent::LOCK_NONE,
            'status' => 'recorded',
            'failed_count' => 0,
            'lock_until' => null,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'device_summary' => $this->deviceSummary((string) $request->userAgent()),
            'meta' => [
                'url' => $request->fullUrl(),
                'accept_language' => $request->header('Accept-Language'),
            ],
        ], $overrides);
    }

    protected function sendLockAlerts(LoginSecurityEvent $event): void
    {
        if ($event->user?->email) {
            $this->sendMail($event->user->email, new LoginSecurityAlertMail($event, 'user'));
        }

        $this->notifyAdmins($event, 'Login security lock triggered', 'A CRM account was locked after multiple failed login attempts.');
    }

    protected function notifyAdmins(LoginSecurityEvent $event, string $title, string $message): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
            ->get()
            ->each(function (User $admin) use ($event, $title, $message) {
                AppNotification::create([
                    'user_id' => $admin->id,
                    'type' => AppNotification::TYPE_LOGIN_SECURITY,
                    'title' => $title,
                    'message' => $message . ' Email: ' . $event->email_normalized,
                    'data' => ['login_security_event_id' => $event->id],
                    'action_type' => AppNotification::ACTION_LOGIN_SECURITY,
                    'action_url' => route('admin.login-security.show', $event),
                ]);

                if ($admin->email) {
                    $this->sendMail($admin->email, new LoginSecurityAlertMail($event, 'admin', $admin));
                }
            });
    }

    protected function sendMail(string $email, \Illuminate\Mail\Mailable $mail): void
    {
        try {
            Mail::to($email)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Login security mail failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function storeSelfie(?string $selfieData, string $email): ?string
    {
        if (!$selfieData || !preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $selfieData, $matches)) {
            return null;
        }

        $payload = substr($selfieData, strpos($selfieData, ',') + 1);
        $binary = base64_decode($payload, true);
        if ($binary === false || strlen($binary) > 3 * 1024 * 1024) {
            return null;
        }

        $extension = $matches[1] === 'png' ? 'png' : 'jpg';
        $path = 'login-security/selfies/' . now()->format('Y/m') . '/' . Str::slug(Str::before($email, '@')) . '-' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    protected function deviceSummary(string $userAgent): string
    {
        $browser = str_contains($userAgent, 'Edg/') ? 'Edge' : (str_contains($userAgent, 'Chrome/') ? 'Chrome' : (str_contains($userAgent, 'Firefox/') ? 'Firefox' : (str_contains($userAgent, 'Safari/') ? 'Safari' : 'Unknown browser')));
        $os = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Android') ? 'Android' : (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') ? 'iOS' : (str_contains($userAgent, 'Mac OS') ? 'macOS' : 'Unknown OS')));

        return trim($browser . ' / ' . $os);
    }
}
