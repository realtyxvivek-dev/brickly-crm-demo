<?php

namespace App\Services;

use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbPage;
use App\Models\FbWebhookEvent;
use App\Models\Role;
use App\Models\SystemSettings;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FacebookLeadAdsHealthService
{
    public function snapshot(?array $metaChecks = null): array
    {
        $connectedPages = FbPage::whereNotNull('page_access_token')->count();
        $enabledForms = FbForm::where('is_enabled', true)->count();
        $mappedForms = FbForm::where('is_enabled', true)->whereHas('mapping')->count();
        $unmappedEnabledForms = max(0, $enabledForms - $mappedForms);
        $failedCount = FbWebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
        $retryableFailedCount = $this->retryableFailedCount();
        $suspectedMissingCount = $this->suspectedMissingCount();
        $pipelineCounts = $this->pipelineCounts();
        $unassignedMetaLeadCount = $this->unassignedMetaLeadCount();
        $failedMetaJobCount = $this->failedMetaJobCount();
        $fallbackLeadCount = FbLead::where('created_at', '>=', now()->subDays(7))
            ->where('raw_response_json->_crm_import_mode', 'fallback_mapping')
            ->count();
        $lastWebhook = FbWebhookEvent::latest('created_at')->first();
        $lastCrmLead = FbLead::whereNotNull('crm_lead_id')->latest('created_at')->first();
        $webhookEndpointOk = $this->webhookEndpointOk();
        $metaChecksFailed = is_array($metaChecks) && collect($metaChecks)->contains(fn ($check) => !($check['success'] ?? false));

        $status = 'healthy';
        $label = 'Healthy';
        $tone = 'green';
        $reasons = [];
        $actions = [];

        if ($connectedPages === 0) {
            [$status, $label, $tone] = ['broken', 'Broken', 'red'];
            $reasons[] = 'No connected Facebook page token.';
            $actions[] = 'Open Facebook Lead Ads settings and add a valid page token.';
        }

        if (!$webhookEndpointOk) {
            [$status, $label, $tone] = ['broken', 'Broken', 'red'];
            $reasons[] = 'Webhook endpoint check failed.';
            $actions[] = 'Check /api/webhooks/facebook/leads verification URL and server routing.';
        }

        if ($metaChecksFailed) {
            [$status, $label, $tone] = ['broken', 'Broken', 'red'];
            $reasons[] = 'Meta API forms fetch failed.';
            $actions[] = 'Refresh the page access token and confirm Lead Access Manager access.';
        }

        if ($status !== 'broken' && ($enabledForms === 0 || $failedCount > 0 || $retryableFailedCount > 0 || $suspectedMissingCount > 0 || $unmappedEnabledForms > 0 || $fallbackLeadCount > 0 || $unassignedMetaLeadCount > 0 || $failedMetaJobCount > 0)) {
            [$status, $label, $tone] = ['warning', 'Warning', 'amber'];
        }

        if ($enabledForms === 0) {
            $reasons[] = 'No enabled forms.';
            $actions[] = 'Open Forms and configure at least one lead form.';
        }

        if ($failedCount > 0) {
            $reasons[] = "{$failedCount} failed webhook event(s) in last 7 days.";
            $actions[] = 'Use Diagnostics > Retry failed webhooks.';
        }

        if ($retryableFailedCount > 0) {
            $reasons[] = "{$retryableFailedCount} retryable failed webhook(s).";
        }

        if ($suspectedMissingCount > 0) {
            $reasons[] = "{$suspectedMissingCount} suspected missing CRM handoff(s).";
            $actions[] = 'Trace the leadgen ID or use Missing Checker for CSV recovery.';
        }

        if ($unmappedEnabledForms > 0) {
            $reasons[] = "{$unmappedEnabledForms} enabled form(s) need mapping.";
            $actions[] = 'Configure mapping for highlighted forms.';
        }

        if ($fallbackLeadCount > 0) {
            $reasons[] = "{$fallbackLeadCount} fallback-mapped Meta lead(s) in last 7 days.";
            $actions[] = 'Review fallback forms and save proper mapping.';
        }

        if ($unassignedMetaLeadCount > 0) {
            $reasons[] = "{$unassignedMetaLeadCount} linked Meta lead(s) have no active owner in last 7 days.";
            $actions[] = 'Review enabled forms without automation and assign the affected leads.';
        }

        if ($failedMetaJobCount > 0) {
            $reasons[] = "{$failedMetaJobCount} failed Meta queue job(s) found.";
            $actions[] = 'Inspect failed jobs and retry after fixing the root error.';
        }

        if (!$lastWebhook) {
            $reasons[] = 'No webhook received yet.';
            $actions[] = 'Create a Meta test lead and track delivery status.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'reasons' => array_values(array_unique($reasons ?: ['No current issue found.'])),
            'actions' => array_values(array_unique($actions)),
            'connected_pages' => $connectedPages,
            'enabled_forms' => $enabledForms,
            'mapped_enabled_forms' => $mappedForms,
            'failed_count' => $failedCount,
            'retryable_failed_count' => $retryableFailedCount,
            'suspected_missing_count' => $suspectedMissingCount,
            'webhooks_received_7d' => $pipelineCounts['webhooks_received'],
            'fb_leads_stored_7d' => $pipelineCounts['fb_leads_stored'],
            'crm_linked_7d' => $pipelineCounts['crm_linked'],
            'unlinked_fb_leads_7d' => $pipelineCounts['unlinked_fb_leads'],
            'unassigned_meta_leads_7d' => $unassignedMetaLeadCount,
            'failed_meta_jobs' => $failedMetaJobCount,
            'fallback_lead_count' => $fallbackLeadCount,
            'last_webhook_at' => $lastWebhook?->created_at,
            'last_crm_lead_at' => $lastCrmLead?->created_at,
            'webhook_endpoint_ok' => $webhookEndpointOk,
        ];
    }

    public function maybeSendAlert(array $snapshot): bool
    {
        if (($snapshot['status'] ?? 'healthy') === 'healthy') {
            return false;
        }

        $signature = sha1(json_encode([
            $snapshot['status'] ?? null,
            $snapshot['reasons'] ?? [],
            $snapshot['failed_count'] ?? 0,
            $snapshot['retryable_failed_count'] ?? 0,
            $snapshot['suspected_missing_count'] ?? 0,
            $snapshot['unlinked_fb_leads_7d'] ?? 0,
            $snapshot['unassigned_meta_leads_7d'] ?? 0,
            $snapshot['failed_meta_jobs'] ?? 0,
            $snapshot['fallback_lead_count'] ?? 0,
        ]));

        if (SystemSettings::get('facebook_lead_ads_health_alert_signature') === $signature) {
            return false;
        }

        $emails = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return false;
        }

        $body = "Facebook Lead Ads health is {$snapshot['label']}.\n\n"
            . "Issues:\n- " . implode("\n- ", $snapshot['reasons'] ?? []) . "\n\n"
            . "Actions:\n- " . implode("\n- ", $snapshot['actions'] ?: ['Open Facebook Lead Ads Diagnostics.']) . "\n\n"
            . 'Diagnostics: ' . url('/integrations/facebook-lead-ads/diagnostics');

        Mail::raw($body, function ($message) use ($emails, $snapshot) {
            $message->to($emails->all())
                ->subject('Facebook Lead Ads Health: ' . ($snapshot['label'] ?? 'Warning'));
        });

        SystemSettings::set('facebook_lead_ads_health_alert_signature', $signature);
        SystemSettings::set('facebook_lead_ads_health_alert_sent_at', Carbon::now()->toDateTimeString());

        return true;
    }

    private function webhookEndpointOk(): bool
    {
        try {
            $token = \App\Models\FbLeadAdsSettings::getSettings()->webhook_verify_token;

            if (!$token) {
                return false;
            }

            $challenge = 'crm_health_' . str()->random(8);
            $response = Http::timeout(8)->get(url('/api/webhooks/facebook/leads'), [
                'hub.mode' => 'subscribe',
                'hub.verify_token' => $token,
                'hub.challenge' => $challenge,
            ]);

            return $response->successful() && trim($response->body()) === $challenge;
        } catch (Throwable) {
            return false;
        }
    }

    private function suspectedMissingCount(): int
    {
        $missingEvents = FbWebhookEvent::where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('leadgen_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fb_leads')
                    ->whereColumn('fb_leads.leadgen_id', 'fb_webhook_events.leadgen_id');
            })
            ->distinct('leadgen_id')
            ->count('leadgen_id');

        $fbLeadsWithoutCrm = FbLead::where('created_at', '>=', now()->subDays(7))
            ->whereNull('crm_lead_id')
            ->count();

        return $missingEvents + $fbLeadsWithoutCrm;
    }

    private function retryableFailedCount(): int
    {
        $enabledFormIds = FbForm::where('is_enabled', true)->pluck('form_id')->map(fn ($id) => (string) $id)->flip();

        if ($enabledFormIds->isEmpty()) {
            return 0;
        }

        return FbWebhookEvent::query()
            ->where('status', 'failed')
            ->whereNotNull('leadgen_id')
            ->whereRaw("leadgen_id REGEXP '^[0-9]+$'")
            ->where(function ($query) {
                $query->whereNull('error')
                    ->orWhere('error', 'like', 'Form not found:%')
                    ->orWhere('error', 'like', 'Configured form/page missing%')
                    ->orWhere('error', 'like', 'No token for page%');
            })
            ->whereNotExists(function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('fb_leads')
                    ->whereColumn('fb_leads.leadgen_id', 'fb_webhook_events.leadgen_id');
            })
            ->orderByDesc('created_at')
            ->get()
            ->unique('leadgen_id')
            ->filter(fn (FbWebhookEvent $event) => $enabledFormIds->has((string) data_get($event->raw_payload, 'entry.0.changes.0.value.form_id')))
            ->count();
    }

    private function pipelineCounts(): array
    {
        $since = now()->subDays(7);

        return [
            'webhooks_received' => FbWebhookEvent::where('created_at', '>=', $since)->count(),
            'fb_leads_stored' => FbLead::where('created_at', '>=', $since)->count(),
            'crm_linked' => FbLead::where('created_at', '>=', $since)->whereNotNull('crm_lead_id')->count(),
            'unlinked_fb_leads' => FbLead::where('created_at', '>=', $since)->whereNull('crm_lead_id')->count(),
        ];
    }

    private function unassignedMetaLeadCount(): int
    {
        return FbLead::query()
            ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
            ->where('fb_leads.created_at', '>=', now()->subDays(7))
            ->where('leads.created_at', '>=', now()->subDays(7))
            ->whereNotNull('fb_leads.crm_lead_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('lead_assignments')
                    ->whereColumn('lead_assignments.lead_id', 'leads.id')
                    ->where('lead_assignments.is_active', true);
            })
            ->count();
    }

    private function failedMetaJobCount(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->where('payload', 'like', '%FetchFacebookLeadDetailsJob%')
            ->count();
    }
}
