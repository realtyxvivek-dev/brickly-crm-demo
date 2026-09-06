<?php

namespace App\Observers;

use App\Models\Incentive;
use App\Services\PostSalesService;

class IncentiveObserver
{
    public function saved(Incentive $incentive): void
    {
        if ($incentive->type !== 'closer' || $incentive->status !== 'verified' || !$incentive->finance_manager_verified_by) {
            return;
        }

        try {
            app(PostSalesService::class)->syncIncentive($incentive);
        } catch (\Throwable $error) {
            report($error);
        }
    }
}
