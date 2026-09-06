<?php

namespace App\Observers;

use App\Models\PricingConfig;
use App\Services\PricingService;

class PricingConfigObserver
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Handle the PricingConfig "created" event.
     */
    public function created(PricingConfig $pricingConfig): void
    {
        $this->recalculatePrices($pricingConfig);
    }

    /**
     * Handle the PricingConfig "updated" event.
     */
    public function updated(PricingConfig $pricingConfig): void
    {
        // Only recalculate if BSP or rounding rule changed
        if ($pricingConfig->isDirty(['bsp_per_sqft', 'price_rounding_rule'])) {
            $this->recalculatePrices($pricingConfig);
        }
    }

    /**
     * Recalculate all unit prices for the project.
     */
    protected function recalculatePrices(PricingConfig $pricingConfig): void
    {
        $project = $pricingConfig->project;

        if ($project) {
            $this->pricingService->recalculateAllPrices($project);
        }
    }
}
