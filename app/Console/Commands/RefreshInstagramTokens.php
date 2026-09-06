<?php

namespace App\Console\Commands;

use App\Models\IgFailedJob;
use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramOAuthService;
use Illuminate\Console\Command;

class RefreshInstagramTokens extends Command
{
    protected $signature = 'instagram:refresh-tokens {--days=10}';

    protected $description = 'Refresh connected Instagram long-lived tokens before they expire';

    public function handle(InstagramOAuthService $oauthService): int
    {
        $days = max(1, (int) $this->option('days'));
        $accounts = InstagramAccount::query()
            ->where('status', 'connected')
            ->whereNotNull('access_token')
            ->where(function ($query) use ($days) {
                $query->whereNull('token_expiry')
                    ->orWhere('token_expiry', '<=', now()->addDays($days));
            })
            ->get();

        $refreshed = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $result = $oauthService->refreshAccountToken($account);

            if ($result['success'] ?? false) {
                $refreshed++;
                continue;
            }

            $failed++;
            IgFailedJob::query()->create([
                'job_type' => 'instagram_token_refresh',
                'related_type' => InstagramAccount::class,
                'related_id' => $account->id,
                'payload' => [
                    'ig_user_id' => $account->ig_user_id,
                    'ig_username' => $account->ig_username,
                ],
                'attempts' => 1,
                'error_message' => $result['error'] ?? 'Instagram token refresh failed.',
                'failed_at' => now(),
            ]);
        }

        $this->info("Instagram token refresh complete. Refreshed: {$refreshed}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
