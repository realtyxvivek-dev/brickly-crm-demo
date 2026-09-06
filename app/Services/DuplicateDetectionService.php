<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\SiteVisit;
use App\Models\BlacklistedNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DuplicateDetectionService
{
    public function __construct(private readonly InternationalPhoneService $phoneService)
    {
    }
    /**
     * Check if a phone number is blacklisted
     * 
     * @param string $phone
     * @return bool
     */
    public function isBlacklisted(string $phone): bool
    {
        $parsed = $this->parsedLeadPhone($phone);
        $phone = $this->sanitizePhone($phone);
        
        if (empty($phone)) {
            return false;
        }

        // Check blacklisted_numbers table
        $candidates = array_values(array_unique(array_filter([
            $phone,
            $parsed['normalized'] ?? null,
            $parsed['e164'] ?? null,
            ($parsed['country_iso'] ?? null) === InternationalPhoneService::DEFAULT_COUNTRY
                ? substr((string) ($parsed['normalized'] ?? ''), -10)
                : null,
        ])));

        return BlacklistedNumber::whereIn('phone', $candidates)->exists();
    }

    /**
     * Check if a phone number already exists in the system
     * 
     * @param string $phone
     * @return bool
     */
    public function isDuplicate(string $phone): bool
    {
        // Sanitize phone number
        $phone = $this->sanitizePhone($phone);
        
        if (empty($phone)) {
            return false;
        }

        // Check if blacklisted (blacklisted numbers should be treated as duplicates)
        if ($this->isBlacklisted($phone)) {
            return true;
        }

        // Check leads table through the same indexed lookup used by Meta/manual guards.
        if ($this->findExistingLeadByPhone($phone)) {
            return true;
        }
        
        // Check lead_assignments via leads
        if (LeadAssignment::whereHas('lead', function($q) use ($phone) {
            $q->where('phone', $phone);
        })->exists()) {
            return true;
        }
        
        // Check site_visits via leads
        if (SiteVisit::whereHas('lead', function($q) use ($phone) {
            $q->where('phone', $phone);
        })->exists()) {
            return true;
        }
        
        return false;
    }

    public function normalizeLeadPhone(?string $phone, ?string $countryIso = null): string
    {
        return $this->phoneService->parse($phone, $countryIso)['normalized'] ?? '';
    }

    public function findExistingLeadByPhone(?string $phone, ?string $countryIso = null): ?Lead
    {
        $rawDigits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $normalizedPhone = preg_match('/^[1-9][0-9]{10,14}$/', $rawDigits)
            ? $rawDigits
            : $this->normalizeLeadPhone($phone, $countryIso);
        if ($normalizedPhone === '') {
            return null;
        }

        if ($this->leadsHaveNormalizedPhoneColumn()) {
            $normalizedMatch = Lead::query()
                ->where('normalized_phone', $normalizedPhone)
                ->latest('id')
                ->first();

            if ($normalizedMatch) {
                return $normalizedMatch;
            }
        }

        $isIndian = str_starts_with($normalizedPhone, '91') && strlen($normalizedPhone) === 12;
        $lastTenDigits = $isIndian ? substr($normalizedPhone, -10) : null;

        $exact = Lead::query()
            ->whereIn('phone', array_values(array_unique(array_filter([
                $normalizedPhone,
                $lastTenDigits,
                '+' . $normalizedPhone,
                $isIndian ? '+91' . $lastTenDigits : null,
            ]))))
            ->latest('id')
            ->first();
        if ($exact) {
            return $exact;
        }

        $normalizedPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), ')', ''), '(', '')";
        $lastTenSql = DB::connection()->getDriverName() === 'mysql'
            ? "RIGHT({$normalizedPhoneSql}, 10)"
            : "substr({$normalizedPhoneSql}, -10)";

        $fallbackQuery = Lead::query()
            ->whereNotNull('phone')
            ->where(function ($query) use ($normalizedPhone, $lastTenDigits, $normalizedPhoneSql, $lastTenSql) {
                $query->whereRaw("{$normalizedPhoneSql} = ?", [$normalizedPhone]);
                if ($lastTenDigits !== null) {
                    $query->orWhereRaw("{$lastTenSql} = ?", [$lastTenDigits]);
                }
            });

        if ($this->leadsHaveNormalizedPhoneColumn()) {
            $fallbackQuery->where(function ($query) {
                $query->whereNull('normalized_phone')
                    ->orWhere('normalized_phone', '');
            });
        }

        return $fallbackQuery
            ->orderByDesc('id')
            ->first();
    }

    private function leadsHaveNormalizedPhoneColumn(): bool
    {
        try {
            return Schema::hasColumn('leads', 'normalized_phone');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sanitize phone number - remove non-numeric characters except +
     * 
     * @param string $phone
     * @return string
     */
    public function sanitizePhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        return trim($phone);
    }

    /**
     * Validate phone number format
     * 
     * @param string $phone
     * @return bool
     */
    public function isValidPhone(string $phone, ?string $countryIso = null): bool
    {
        return $this->normalizeLeadPhone($phone, $countryIso) !== '';
    }

    public function parsedLeadPhone(?string $phone, ?string $countryIso = null): ?array
    {
        return $this->phoneService->parse($phone, $countryIso);
    }
}
