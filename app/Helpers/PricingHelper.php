<?php

namespace App\Helpers;

class PricingHelper
{
    /**
     * Format price in Indian currency (Lakhs/Crores).
     */
    public static function formatIndianCurrency(float $price): string
    {
        if ($price >= 10000000) {
            return '₹' . number_format($price / 10000000, 2) . ' Cr';
        } elseif ($price >= 100000) {
            return '₹' . number_format($price / 100000, 2) . ' L';
        }

        return '₹' . number_format($price, 0);
    }

    /**
     * Apply rounding rule to price.
     */
    public static function applyRoundingRule(float $price, ?string $rule = null): float
    {
        if ($rule === 'nearest_1000') {
            return round($price / 1000) * 1000;
        } elseif ($rule === 'nearest_10000') {
            return round($price / 10000) * 10000;
        }

        return $price;
    }

    /**
     * Convert price to Lakhs.
     */
    public static function convertToLakhs(float $price): float
    {
        return $price / 100000;
    }

    /**
     * Convert price to Crores.
     */
    public static function convertToCrores(float $price): float
    {
        return $price / 10000000;
    }
}
