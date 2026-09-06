<?php

namespace App\Services;

use App\Models\PricingConfig;
use App\Models\Project;
use App\Models\UnitType;
use Illuminate\Support\Facades\DB;

class PricingService
{
    /**
     * Set BSP for project.
     */
    public function setBSP(Project $project, float $bspPerSqft, string $roundingRule = 'none'): PricingConfig
    {
        DB::beginTransaction();
        try {
            $pricingConfig = $project->pricingConfig()->updateOrCreate(
                ['project_id' => $project->id],
                [
                    'bsp_per_sqft' => $bspPerSqft,
                    'price_rounding_rule' => $roundingRule,
                ]
            );

            // Recalculate all unit prices
            $this->recalculateAllPrices($project);

            DB::commit();
            return $pricingConfig;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate unit price.
     */
    public function calculateUnitPrice(UnitType $unitType, ?float $bsp = null): float
    {
        if (!$bsp) {
            $bsp = $unitType->project->pricingConfig?->bsp_per_sqft;
        }

        if (!$bsp) {
            return 0;
        }

        $price = $unitType->area_sqft * $bsp;

        // Apply rounding
        $roundingRule = $unitType->project->pricingConfig?->price_rounding_rule;
        $price = $this->applyRoundingRule($price, $roundingRule);

        return $price;
    }

    /**
     * Recalculate all prices for a project.
     */
    public function recalculateAllPrices(Project $project): void
    {
        $bsp = $project->pricingConfig?->bsp_per_sqft;

        if (!$bsp) {
            return;
        }

        $unitTypes = $project->unitTypes()->get();

        foreach ($unitTypes as $unitType) {
            $price = $this->calculateUnitPrice($unitType, $bsp);
            $unitType->update(['calculated_price' => $price]);
        }

        // Mark starting from (cheapest)
        $this->markStartingFrom($project);
    }

    /**
     * Apply rounding rule.
     */
    public function applyRoundingRule(float $price, ?string $rule = null): float
    {
        if ($rule === 'nearest_1000') {
            return round($price / 1000) * 1000;
        } elseif ($rule === 'nearest_10000') {
            return round($price / 10000) * 10000;
        }

        return $price;
    }

    /**
     * Format price in Indian currency (Lakhs/Crores).
     */
    public function formatPrice(float $price): string
    {
        if ($price >= 10000000) {
            return '₹' . number_format($price / 10000000, 2) . ' Cr';
        } elseif ($price >= 100000) {
            return '₹' . number_format($price / 100000, 2) . ' L';
        }

        return '₹' . number_format($price, 0);
    }

    /**
     * Mark cheapest unit as starting from.
     */
    public function markStartingFrom(Project $project): void
    {
        // Reset all
        $project->unitTypes()->update(['is_starting_from' => false]);

        // Find cheapest
        $cheapest = $project->unitTypes()
            ->whereNotNull('calculated_price')
            ->orderBy('calculated_price', 'asc')
            ->first();

        if ($cheapest) {
            $cheapest->update(['is_starting_from' => true]);
        }
    }
}
