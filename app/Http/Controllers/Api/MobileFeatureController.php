<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSettings;
use Illuminate\Http\Request;

class MobileFeatureController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $recordingEnabled = $user ? $this->callRecordingEnabledForEmail((string) $user->email) : false;
        $incomingCallCardEnabled = $user ? $this->incomingCallCardEnabledForEmail((string) $user->email) : false;
        $diagnostics = $user ? $this->diagnosticsFlagsForUser((int) $user->id) : $this->defaultDiagnosticsFlags();
        $maxSizeMb = (int) SystemSettings::get('mobile_call_recording_max_size_mb', '25');

        return response()->json([
            'callRecordingEnabled' => $recordingEnabled,
            'incomingCallCardEnabled' => $incomingCallCardEnabled,
            'diagnosticsEnabled' => $diagnostics['diagnosticsEnabled'],
            'callerIdDebugEnabled' => $diagnostics['callerIdDebugEnabled'],
            'callSyncDebugEnabled' => $diagnostics['callSyncDebugEnabled'],
            'recordingDebugEnabled' => $diagnostics['recordingDebugEnabled'],
            'attendanceDebugEnabled' => $diagnostics['attendanceDebugEnabled'],
            'notificationDebugEnabled' => $diagnostics['notificationDebugEnabled'],
            'diagnosticsExpiresAt' => $diagnostics['diagnosticsExpiresAt'],
            'incomingCallLookupUrl' => url('/api/mobile/incoming-call-lookup'),
            'recordingMaxSizeMb' => max(1, $maxSizeMb),
            'recordingUploadUrl' => url('/api/call-logs/{id}/recording'),
            'diagnosticsUploadUrl' => url('/api/mobile/diagnostics'),
        ]);
    }

    private function callRecordingEnabledForEmail(string $email): bool
    {
        $configured = (string) SystemSettings::get('mobile_call_recording_user_emails', 'test@gmail.com');
        $allowedEmails = collect(explode(',', $configured))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();

        if ($allowedEmails->isEmpty()) {
            $allowedEmails = collect(['test@gmail.com']);
        }

        return $allowedEmails->contains(strtolower(trim($email)));
    }

    private function incomingCallCardEnabledForEmail(string $email): bool
    {
        $configured = (string) SystemSettings::get('mobile_incoming_call_card_user_emails', 'test@gmail.com');
        $allowedEmails = collect(explode(',', $configured))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();

        if ($allowedEmails->isEmpty()) {
            $allowedEmails = collect(['test@gmail.com']);
        }

        return $allowedEmails->contains(strtolower(trim($email)));
    }

    private function diagnosticsFlagsForUser(int $userId): array
    {
        $flags = $this->configuredDiagnosticsFlags();
        $userFlags = $flags[(string) $userId] ?? null;

        if (!is_array($userFlags) || !($userFlags['enabled'] ?? false)) {
            return $this->defaultDiagnosticsFlags();
        }

        $expiresAt = $userFlags['expires_at'] ?? null;
        if ($expiresAt && now()->greaterThan(\Illuminate\Support\Carbon::parse($expiresAt))) {
            return $this->defaultDiagnosticsFlags();
        }

        return [
            'diagnosticsEnabled' => true,
            'callerIdDebugEnabled' => (bool) ($userFlags['caller_id'] ?? true),
            'callSyncDebugEnabled' => (bool) ($userFlags['call_sync'] ?? true),
            'recordingDebugEnabled' => (bool) ($userFlags['recording'] ?? true),
            'attendanceDebugEnabled' => (bool) ($userFlags['attendance'] ?? true),
            'notificationDebugEnabled' => (bool) ($userFlags['notification'] ?? true),
            'diagnosticsExpiresAt' => $expiresAt,
        ];
    }

    private function defaultDiagnosticsFlags(): array
    {
        return [
            'diagnosticsEnabled' => false,
            'callerIdDebugEnabled' => false,
            'callSyncDebugEnabled' => false,
            'recordingDebugEnabled' => false,
            'attendanceDebugEnabled' => false,
            'notificationDebugEnabled' => false,
            'diagnosticsExpiresAt' => null,
        ];
    }

    private function configuredDiagnosticsFlags(): array
    {
        $decoded = json_decode((string) SystemSettings::get('mobile_app_diagnostics_user_flags', '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }
}
