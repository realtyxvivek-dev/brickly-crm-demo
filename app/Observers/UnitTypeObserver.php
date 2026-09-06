<?php

namespace App\Observers;

use App\Models\UnitType;
use App\Services\PricingService;

class UnitTypeObserver
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Handle the UnitType "created" event.
     */
    public function created(UnitType $unitType): void
    {
        $this->updateUnitType($unitType);
    }

    /**
     * Handle the UnitType "updated" event.
     */
    public function updated(UnitType $unitType): void
    {
        $this->updateUnitType($unitType);
    }

    /**
     * Update unit type (calculate price, generate label, mark starting from).
     */
    protected function updateUnitType(UnitType $unitType): void
    {
        // Generate display label if not set
        if (!$unitType->display_label) {
            $unitType->generateDisplayLabel();
            $unitType->saveQuietly();
        }

        // Calculate price if BSP exists
        if ($unitType->project->pricingConfig && $unitType->isDirty(['area_sqft', 'unit_type'])) {
            $price = $this->pricingService->calculateUnitPrice($unitType);
            $unitType->calculated_price = $price;
            $unitType->saveQuietly(); // Save without triggering events
        }

        // Mark starting from (cheapest) - only if price changed
        if ($unitType->isDirty('calculated_price')) {
            $this->pricingService->markStartingFrom($unitType->project);
        }
    }
}
