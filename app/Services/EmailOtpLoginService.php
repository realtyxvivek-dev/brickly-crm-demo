<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\ActivityLog;
use App\Models\OtpLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailOtpLoginService
{
    public const OTP_LENGTH = 6;
    public const OTP_EXPIRY_SECONDS = 300;
    public const RESEND_COOLDOWN_SECONDS = 90;
    public const MAX_VERIFY_ATTEMPTS = 5;
    public const MAX_RESEND_ATTEMPTS = 3;

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function resolveActiveUserByEmail(string $email): ?User
    {
        $normalized = $this->normalizeEmail($email);

        return User::with('role')
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->where('is_active', true)
            ->first();
    }

    public function shouldForcePasswordLogin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return app()->isLocal() && ($user->isAdmin() || $user->isCrm() || $user->isFinanceManager());
    }

    public function sendOtp(Request $request, string $email): array
    {
        $normalized = $this->normalizeEmail($email);
        $user = $this->resolveActiveUserByEmail($normalized);
        $requestId = (string) Str::uuid();
        $maskedEmail = $this->maskEmail($normalized);
        $otpDispatched = false;

        if ($user && $user->usesEmailOtpLogin() && !$this->shouldForcePasswordLogin($user)) {
            $this->cleanupRequests($user, $normalized);
            $this->invalidateActiveRequests($user, $normalized);

            $otp = $this->generateOtp();
            $record = OtpLoginRequest::create([
                'user_id' => $user->id,
                'email_normalized' => $normalized,
                'request_id' => $requestId,
                'otp_hash' => $this->hashOtp($otp),
                'expires_at' => now()->addSeconds(self::OTP_EXPIRY_SECONDS),
                'last_sent_at' => now(),
                'status' => OtpLoginRequest::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Mail::to($user->email)->send(new LoginOtpMail($otp, $user->name));
            $otpDispatched = true;

            $this->logOtpEvent('login_otp_sent', $user, $record, $request, 'Email login OTP sent.');
        } else {
            $this->logOtpEvent('login_otp_send_requested', $user, null, $request, 'OTP send requested for login.', [
                'email_normalized' => $normalized,
                'eligible_for_otp' => $user?->usesEmailOtpLogin() ?? false,
            ]);
        }

        return [
            'request_id' => $requestId,
            'email_normalized' => $normalized,
            'masked_email' => $maskedEmail,
            'method' => User::TWO_FACTOR_EMAIL,
            'otp_dispatched' => $otpDispatched,
        ];
    }

    public function resolveLoginFlow(Request $request, string $email): array
    {
        $normalized = $this->normalizeEmail($email);
        $user = $this->resolveActiveUserByEmail($normalized);
        $maskedEmail = $this->maskEmail($normalized);

        if ($user && $user->usesEmailOtpLogin() && !$this->shouldForcePasswordLogin($user)) {
            $payload = $this->sendOtp($request, $normalized);
            $this->logOtpEvent('login_email_resolved', $user, null, $request, 'Login email resolved to OTP flow.', [
                'email_normalized' => $normalized,
                'resolved_method' => User::TWO_FACTOR_EMAIL,
            ]);

            return $payload;
        }

        $this->logOtpEvent('login_email_resolved', $user, null, $request, 'Login email resolved to password flow.', [
            'email_normalized' => $normalized,
            'resolved_method' => User::TWO_FACTOR_OFF,
            'resolved_user' => (bool) $user,
        ]);

        return [
            'request_id' => null,
            'email_normalized' => $normalized,
            'masked_email' => $maskedEmail,
            'method' => User::TWO_FACTOR_OFF,
            'otp_dispatched' => false,
            'cooldown_remaining' => 0,
        ];
    }

    public function resendOtp(Request $request, string $requestId, string $email): array
    {
        $normalized = $this->normalizeEmail($email);
        $user = $this->resolveActiveUserByEmail($normalized);
        $current = $this->findLatestActiveRequest($user, $normalized, $requestId);
        $newRequestId = (string) Str::uuid();

        if ($current && $current->resend_count >= self::MAX_RESEND_ATTEMPTS) {
            $current->update([
                'status' => OtpLoginRequest::STATUS_LOCKED,
                'invalidated_at' => now(),
            ]);

            $this->logOtpEvent('login_otp_resend_locked', $user, $current, $request, 'OTP resend limit reached.');

            return [
                'request_id' => $newRequestId,
                'email_normalized' => $normalized,
                'masked_email' => $this->maskEmail($normalized),
                'method' => User::TWO_FACTOR_EMAIL,
                'otp_dispatched' => false,
                'cooldown_remaining' => 0,
            ];
        }

        if ($current && $current->last_sent_at && now()->diffInSeconds($current->last_sent_at) < self::RESEND_COOLDOWN_SECONDS) {
            $remaining = self::RESEND_COOLDOWN_SECONDS - now()->diffInSeconds($current->last_sent_at);

            return [
                'request_id' => $requestId,
                'email_normalized' => $normalized,
                'masked_email' => $this->maskEmail($normalized),
                'method' => User::TWO_FACTOR_EMAIL,
                'otp_dispatched' => true,
                'cooldown_remaining' => max($remaining, 1),
            ];
        }

        if ($user && $user->usesEmailOtpLogin() && !$this->shouldForcePasswordLogin($user)) {
            $this->cleanupRequests($user, $normalized);
            $this->invalidateActiveRequests($user, $normalized);

            $otp = $this->generateOtp();
            $record = OtpLoginRequest::create([
                'user_id' => $user->id,
                'email_normalized' => $normalized,
                'request_id' => $newRequestId,
                'otp_hash' => $this->hashOtp($otp),
                'expires_at' => now()->addSeconds(self::OTP_EXPIRY_SECONDS),
                'last_sent_at' => now(),
                'resend_count' => ($current?->resend_count ?? 0) + 1,
                'status' => OtpLoginRequest::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            Mail::to($user->email)->send(new LoginOtpMail($otp, $user->name));

            $this->logOtpEvent('login_otp_resent', $user, $record, $request, 'Email login OTP resent.');

            return [
                'request_id' => $newRequestId,
                'email_normalized' => $normalized,
                'masked_email' => $this->maskEmail($normalized),
                'method' => User::TWO_FACTOR_EMAIL,
                'otp_dispatched' => true,
                'cooldown_remaining' => self::RESEND_COOLDOWN_SECONDS,
            ];
        }

        $this->logOtpEvent('login_otp_resend_requested', $user, null, $request, 'OTP resend requested for login.', [
            'email_normalized' => $normalized,
        ]);

        return [
            'request_id' => $newRequestId,
            'email_normalized' => $normalized,
            'masked_email' => $this->maskEmail($normalized),
            'method' => User::TWO_FACTOR_EMAIL,
            'otp_dispatched' => false,
            'cooldown_remaining' => 0,
        ];
    }

    public function verifyOtp(Request $request, string $requestId, string $email, string $otp): ?User
    {
        $normalized = $this->normalizeEmail($email);
        $user = $this->resolveActiveUserByEmail($normalized);
        $record = $this->findLatestActiveRequest($user, $normalized, $requestId);

        if (!$user || !$record) {
            $this->logOtpEvent('login_otp_verify_failed', $user, $record, $request, 'OTP verification failed.', [
                'reason' => 'request_not_found',
                'email_normalized' => $normalized,
            ]);

            return null;
        }

        if ($record->isExpired()) {
            $record->update([
                'status' => OtpLoginRequest::STATUS_EXPIRED,
                'invalidated_at' => now(),
            ]);

            $this->logOtpEvent('login_otp_verify_failed', $user, $record, $request, 'OTP verification failed.', [
                'reason' => 'expired',
            ]);

            return null;
        }

        if ($record->attempt_count >= self::MAX_VERIFY_ATTEMPTS) {
            $record->update([
                'status' => OtpLoginRequest::STATUS_LOCKED,
                'invalidated_at' => now(),
            ]);

            $this->logOtpEvent('login_otp_verify_locked', $user, $record, $request, 'OTP request locked after too many attempts.');

            return null;
        }

        if (!$this->otpMatches($otp, (string) $record->otp_hash)) {
            $attemptCount = $record->attempt_count + 1;
            $status = $attemptCount >= self::MAX_VERIFY_ATTEMPTS
                ? OtpLoginRequest::STATUS_LOCKED
                : OtpLoginRequest::STATUS_PENDING;

            $record->update([
                'attempt_count' => $attemptCount,
                'status' => $status,
                'invalidated_at' => $status === OtpLoginRequest::STATUS_LOCKED ? now() : null,
            ]);

            $this->logOtpEvent('login_otp_verify_failed', $user, $record, $request, 'OTP verification failed.', [
                'reason' => 'invalid_otp',
                'attempt_count' => $attemptCount,
            ]);

            return null;
        }

        $record->update([
            'status' => OtpLoginRequest::STATUS_USED,
            'used_at' => now(),
        ]);

        $this->logOtpEvent('login_otp_verify_success', $user, $record, $request, 'OTP verification successful.');

        return $user;
    }

    public function blockPasswordLoginAudit(Request $request, ?User $user): void
    {
        $this->logOtpEvent('login_password_blocked_for_otp_only', $user, null, $request, 'Password login blocked because OTP-only login is enabled.', [
            'email_normalized' => $this->normalizeEmail((string) $request->input('email')),
        ]);
    }

    public function clearPendingSession(Request $request): void
    {
        $request->session()->forget([
            'auth_flow.pending',
            'auth_flow.request_id',
            'auth_flow.email_normalized',
            'auth_flow.masked_email',
            'auth_flow.cooldown_remaining',
            'auth_flow.method',
        ]);
    }

    public function setPendingSession(Request $request, array $payload): void
    {
        $request->session()->put('auth_flow.pending', true);
        $request->session()->put('auth_flow.request_id', $payload['request_id']);
        $request->session()->put('auth_flow.email_normalized', $payload['email_normalized']);
        $request->session()->put('auth_flow.masked_email', $payload['masked_email']);
        $request->session()->put('auth_flow.method', $payload['method'] ?? User::TWO_FACTOR_OFF);
        $request->session()->put('auth_flow.cooldown_remaining', $payload['cooldown_remaining'] ?? self::RESEND_COOLDOWN_SECONDS);
    }

    protected function findLatestActiveRequest(?User $user, string $normalizedEmail, string $requestId): ?OtpLoginRequest
    {
        $query = OtpLoginRequest::query()
            ->where('email_normalized', $normalizedEmail)
            ->where('status', OtpLoginRequest::STATUS_PENDING)
            ->orderByDesc('id');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $latest = $query->first();

        if (!$latest || $latest->request_id !== $requestId) {
            return null;
        }

        return $latest;
    }

    protected function cleanupRequests(?User $user, string $normalizedEmail): void
    {
        $query = OtpLoginRequest::query()->where('email_normalized', $normalizedEmail);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $query->where('status', OtpLoginRequest::STATUS_PENDING)
            ->get()
            ->each(function (OtpLoginRequest $record) {
                if ($record->isExpired()) {
                    $record->update([
                        'status' => OtpLoginRequest::STATUS_EXPIRED,
                        'invalidated_at' => now(),
                    ]);
                }
            });
    }

    protected function invalidateActiveRequests(User $user, string $normalizedEmail): void
    {
        OtpLoginRequest::query()
            ->where('user_id', $user->id)
            ->where('email_normalized', $normalizedEmail)
            ->where('status', OtpLoginRequest::STATUS_PENDING)
            ->update([
                'status' => OtpLoginRequest::STATUS_INVALIDATED,
                'invalidated_at' => now(),
            ]);
    }

    protected function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    protected function hashOtp(string $otp): string
    {
        $pepper = (string) env('OTP_LOGIN_PEPPER', config('app.key'));

        return hash_hmac('sha256', $otp, $pepper);
    }

    protected function otpMatches(string $otp, string $hash): bool
    {
        return hash_equals($hash, $this->hashOtp($otp));
    }

    protected function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, min(2, strlen($name)));
        $maskedPart = str_repeat('*', max(strlen($name) - strlen($visible), 2));

        return $visible . $maskedPart . '@' . $domain;
    }

    public function maskEmailForDisplay(string $email): string
    {
        return $this->maskEmail($this->normalizeEmail($email));
    }

    protected function logOtpEvent(string $action, ?User $user, ?OtpLoginRequest $record, Request $request, string $description, array $newValues = []): void
    {
        try {
            ActivityLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'model_type' => $record ? OtpLoginRequest::class : User::class,
                'model_id' => $record?->id ?? $user?->id,
                'description' => $description,
                'old_values' => null,
                'new_values' => array_merge($newValues, [
                    'request_id' => $record?->request_id,
                    'status' => $record?->status,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to persist OTP activity log.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
