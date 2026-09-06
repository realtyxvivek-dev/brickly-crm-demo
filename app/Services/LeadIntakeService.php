<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Validation\ValidationException;

class LeadIntakeService
{
    public function __construct(
        private readonly LeadDuplicateGuardService $duplicateGuard,
        private readonly LeadReenquiryService $reenquiryService,
    ) {
    }

    public function createOrReenquire(array $attributes, ?string $source = null, ?int $actorId = null): array
    {
        $countryIso = $attributes['phone_country_iso'] ?? null;
        $parsed = app(DuplicateDetectionService::class)->parsedLeadPhone($attributes['phone'] ?? null, $countryIso);
        if (!$parsed) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid phone number. Use + country code for non-Indian numbers.',
            ]);
        }

        $normalized = $parsed['normalized'];

        return $this->duplicateGuard->withPhoneLock($normalized, function (string $lockedPhone) use ($attributes, $source, $actorId, $parsed) {
            $existing = $this->duplicateGuard->findExistingLeadByPhone($lockedPhone);
            $source = Lead::normalizeSource($source ?: ($attributes['source'] ?? 'other'));

            if ($existing) {
                $monitoringUntil = config('error_alerts.lead_duplicates.monitoring_until');
                $monitoringActive = config('error_alerts.lead_duplicates.monitoring_enabled')
                    && (!$monitoringUntil || now()->toDateString() <= $monitoringUntil);

                if ($monitoringActive) {
                    app(SystemErrorAlertService::class)->sendOperationalMessage(implode("\n", [
                        'CRM DUPLICATE CREATION BLOCKED',
                        '',
                        "Existing lead: #{$existing->id}",
                        "Phone: " . substr($lockedPhone, -10),
                        "Source: {$source}",
                        'Action: Saved as re-enquiry',
                        'Time: ' . now()->format('d M Y h:i A'),
                    ]));
                }

                return [
                    'lead' => $this->reenquiryService->markGenericReenquiry($existing, $source, $actorId),
                    'was_created' => false,
                    'was_duplicate' => true,
                ];
            }

            $attributes['phone'] = $parsed['e164'];
            $attributes['normalized_phone'] = $lockedPhone;
            $attributes['phone_country_iso'] = $parsed['country_iso'];
            $attributes['source'] = $source;

            return [
                'lead' => Lead::create($attributes),
                'was_created' => true,
                'was_duplicate' => false,
            ];
        });
    }
}
