<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;

class WhatsAppComplianceService
{
    public function checkLead(?Lead $lead, array $options = []): array
    {
        if (!$lead) {
            return ['allowed' => false, 'reason' => 'Lead not found.'];
        }

        if ($lead->whatsapp_opted_out_at) {
            return ['allowed' => false, 'reason' => 'Customer opted out from WhatsApp.'];
        }

        $dailyCap = (int) ($options['daily_send_cap'] ?? 0);
        if ($dailyCap > 0) {
            $sentToday = WhatsAppAutomationLog::query()
                ->where('lead_id', $lead->id)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', now()->startOfDay())
                ->count();
            if ($sentToday >= $dailyCap) {
                return ['allowed' => false, 'reason' => "Daily WhatsApp cap reached for this lead ({$dailyCap})."];
            }
        }

        $cooldown = (int) ($options['cooldown_minutes'] ?? 0);
        if ($cooldown > 0) {
            $lastSent = WhatsAppAutomationLog::query()
                ->where('lead_id', $lead->id)
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->value('sent_at');
            if ($lastSent && Carbon::parse($lastSent)->gt(now()->subMinutes($cooldown))) {
                return ['allowed' => false, 'reason' => "Cooldown active for {$cooldown} minutes."];
            }
        }

        if (($options['requires_session_window'] ?? false) && !$this->hasOpenSessionWindow($lead)) {
            return ['allowed' => false, 'reason' => 'Outside WhatsApp 24-hour session window. Use approved template instead.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function hasOpenSessionWindow(Lead $lead): bool
    {
        $last10 = substr(preg_replace('/[^0-9]/', '', (string) $lead->phone), -10);
        if (!$last10) {
            return false;
        }

        return WhatsAppMessage::query()
            ->where('direction', 'received')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereHas('conversation', function ($query) use ($lead, $last10) {
                $query->where('lead_id', $lead->id)
                    ->orWhere('phone_number', 'like', '%' . $last10);
            })
            ->exists();
    }

    public function health(): array
    {
        return [
            'queue_pending' => WhatsAppAutomationLog::query()->where('status', WhatsAppAutomationLog::STATUS_PENDING)->count(),
            'automation_failed' => WhatsAppAutomationLog::query()->where('status', WhatsAppAutomationLog::STATUS_FAILED)->count(),
            'open_sessions' => WhatsAppConversation::query()->where('last_inbound_at', '>=', now()->subHours(24))->count(),
            'opted_out_leads' => Lead::query()->whereNotNull('whatsapp_opted_out_at')->count(),
        ];
    }
}
