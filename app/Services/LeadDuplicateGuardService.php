<?php

namespace App\Services;

use App\Models\FbForm;
use App\Models\Lead;
use Closure;
use Illuminate\Support\Facades\DB;

class LeadDuplicateGuardService
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly LeadReenquiryService $leadReenquiryService,
    ) {
    }

    public function normalizePhone(?string $phone, ?string $countryIso = null): string
    {
        return $this->duplicateDetectionService->normalizeLeadPhone($phone, $countryIso);
    }

    public function findExistingLeadByPhone(?string $phone, ?string $countryIso = null): ?Lead
    {
        return $this->duplicateDetectionService->findExistingLeadByPhone($phone, $countryIso);
    }

    public function withPhoneLock(?string $phone, Closure $callback, ?string $countryIso = null): mixed
    {
        $normalizedPhone = $this->normalizePhone($phone, $countryIso);
        if ($normalizedPhone === '') {
            return $callback($normalizedPhone);
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $callback($normalizedPhone);
        }

        $lockName = 'lead_phone_' . sha1($normalizedPhone);
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new \RuntimeException('Could not lock lead phone for duplicate check. Please try again.');
        }

        try {
            return $callback($normalizedPhone);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    public function buildDuplicatePayload(Lead $lead): array
    {
        return [
            'duplicate' => true,
            'existing_lead_id' => $lead->id,
            'existing_lead_url' => route('leads.show', $lead),
        ];
    }

    public function createOrAttachMetaLead(FbForm $fbForm, array $mapped, int $createdBy): ?array
    {
        $name = trim((string) ($mapped['name'] ?? ''));
        $phone = $this->resolveMappedPhone($mapped);

        if ($name === '' && $phone === '') {
            return null;
        }

        return $this->withPhoneLock($phone, function (string $normalizedPhone) use ($fbForm, $mapped, $createdBy, $name) {
            $phone = $normalizedPhone;

            if ($phone !== '') {
            $existingLead = $this->findExistingLeadByPhone($phone);
            if ($existingLead) {
                $reopened = $existingLead->wasTerminalBeforeReenquiry();
                $lead = $this->leadReenquiryService->markMetaReenquiry($existingLead, $fbForm, $createdBy);

                return [
                    'lead' => $lead,
                    'was_created' => false,
                    'was_duplicate' => true,
                    'was_reopened' => $reopened,
                ];
            }
        }

        $notes = $mapped['notes'] ?? null;
        if (!$notes && !empty($mapped['meta']) && is_array($mapped['meta'])) {
            $parts = [];
            foreach ($mapped['meta'] as $key => $value) {
                $parts[] = $key . ': ' . $value;
            }
            $notes = implode("\n", $parts);
        }

        $lead = Lead::create([
            'name' => $name !== '' ? $name : 'Facebook Lead',
            'phone' => $phone !== '' ? '+' . $phone : 'N/A',
            'email' => $mapped['email'] ?? null,
            'address' => $mapped['address'] ?? null,
            'city' => $mapped['city'] ?? null,
            'state' => $mapped['state'] ?? null,
            'pincode' => $mapped['pincode'] ?? null,
            'requirements' => $mapped['requirements'] ?? null,
            'notes' => $notes,
            'source' => Lead::normalizeSource('facebook_lead_ads'),
            'status' => 'new',
            'created_by' => $createdBy,
        ]);

        return [
            'lead' => $lead,
            'was_created' => true,
            'was_duplicate' => false,
            'was_reopened' => false,
        ];
        });
    }

    private function resolveMappedPhone(array $mapped): string
    {
        $phoneCandidates = [
            $mapped['phone'] ?? null,
            $mapped['phone_number'] ?? null,
            $mapped['mobile'] ?? null,
            $mapped['mobile_number'] ?? null,
            $mapped['whatsapp_number'] ?? null,
        ];

        if (!empty($mapped['meta']) && is_array($mapped['meta'])) {
            $meta = $mapped['meta'];
            $phoneCandidates = array_merge($phoneCandidates, [
                $meta['phone'] ?? null,
                $meta['phone_number'] ?? null,
                $meta['mobile'] ?? null,
                $meta['mobile_number'] ?? null,
                $meta['whatsapp_number'] ?? null,
                $meta['contact_number'] ?? null,
                $meta['phone number'] ?? null,
                $meta['mobile number'] ?? null,
            ]);
        }

        foreach ($phoneCandidates as $candidate) {
            $normalized = $this->normalizePhone($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }
}
