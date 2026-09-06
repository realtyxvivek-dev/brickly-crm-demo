<?php

namespace App\Services\Instagram;

use App\Models\IgApiLog;
use App\Models\IgAutomationRule;
use App\Models\IgConversation;
use App\Models\IgDmFlow;
use App\Models\IgFailedJob;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;

class InstagramReadinessService
{
    public function __construct(private readonly InstagramConfig $config)
    {
    }

    public function report(): array
    {
        $configStatus = $this->config->status();

        $checks = [
            $this->check(
                'Instagram app credentials',
                $configStatus['client_id_configured'] && $configStatus['client_secret_configured'],
                'App ID and app secret are configured.',
                'Set INSTAGRAM_CLIENT_ID and INSTAGRAM_CLIENT_SECRET.'
            ),
            $this->check(
                'Webhook security',
                $configStatus['webhook_verify_token_configured'] && $configStatus['app_secret_configured'] && $configStatus['webhook_signature_enabled'],
                'Verify token, app secret, and signature validation are ready.',
                'Set INSTAGRAM_WEBHOOK_VERIFY_TOKEN, INSTAGRAM_APP_SECRET, and keep signature validation enabled.'
            ),
            $this->check(
                'Production queue',
                in_array(config('queue.default'), ['redis', 'database'], true),
                'Queue connection is production-ready.',
                'Set QUEUE_CONNECTION=database on shared hosting or redis on a VPS.'
            ),
            $this->check(
                'Connected account',
                InstagramAccount::query()->where('status', 'connected')->whereNotNull('access_token')->exists(),
                'At least one Instagram account is connected.',
                'Connect an Instagram Business or Creator account from Accounts tab.'
            ),
            $this->check(
                'Active DM flow',
                IgDmFlow::query()->where('status', 'active')->where('is_active', true)->exists(),
                'At least one active DM flow exists.',
                'Create and activate a name/phone DM flow.'
            ),
            $this->check(
                'Active automation rule',
                IgAutomationRule::query()->where('status', 'active')->where('is_active', true)->exists(),
                'At least one active keyword rule exists.',
                'Create and activate a keyword automation rule.'
            ),
            $this->check(
                'Token expiry',
                !InstagramAccount::query()
                    ->where('status', 'connected')
                    ->whereNotNull('token_expiry')
                    ->where('token_expiry', '<=', now()->addDays(10))
                    ->exists(),
                'No connected account token expires within 10 days.',
                'Run php artisan instagram:refresh-tokens and review failed refresh logs.',
                'warning'
            ),
            $this->check(
                'Recent webhook failures',
                IgWebhookEvent::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count() === 0,
                'No webhook failures in the last 24 hours.',
                'Review Instagram Automation > Logs for failed webhook events.',
                'warning'
            ),
            $this->check(
                'Recent API failures',
                IgApiLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count() === 0,
                'No Instagram API failures in the last 24 hours.',
                'Review Recent API Logs for failed Graph API calls.',
                'warning'
            ),
            $this->check(
                'Failed Instagram jobs',
                IgFailedJob::query()->where('job_type', 'like', 'instagram%')->where('failed_at', '>=', now()->subDay())->count() === 0,
                'No Instagram failed job records in the last 24 hours.',
                'Review ig_failed_jobs and queue failed jobs.',
                'warning'
            ),
            $this->check(
                'Human attention queue',
                IgConversation::query()->where('status', 'needs_human')->count() === 0,
                'No conversations currently need human attention.',
                'Review Conversations tab and handle needs_human conversations.',
                'warning'
            ),
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check) => $check['status'] !== 'error'),
            'has_warnings' => collect($checks)->contains(fn (array $check) => $check['status'] === 'warning'),
            'checks' => $checks,
            'summary' => [
                'connected_accounts' => InstagramAccount::query()->where('status', 'connected')->count(),
                'active_rules' => IgAutomationRule::query()->where('status', 'active')->where('is_active', true)->count(),
                'active_flows' => IgDmFlow::query()->where('status', 'active')->where('is_active', true)->count(),
                'needs_human' => IgConversation::query()->where('status', 'needs_human')->count(),
                'failed_webhooks_24h' => IgWebhookEvent::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
                'failed_api_logs_24h' => IgApiLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
        ];
    }

    private function check(string $name, bool $passes, string $ok, string $fix, string $failureStatus = 'error'): array
    {
        return [
            'name' => $name,
            'status' => $passes ? 'ok' : $failureStatus,
            'message' => $passes ? $ok : $fix,
        ];
    }
}
