<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Services\KycFormSchemaService;
use App\Services\VerificationRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerificationController extends Controller
{
    /**
     * Show verification panel
     */
    public function index(VerificationRoutingService $routing)
    {
        $user = auth()->user();
        abort_unless($routing->userHasVerifierAccess($user), 403);

        $isSalesManagerPanel = request()->routeIs('sales-manager.verifications*');

        // Create API token for authenticated requests
        $token = $user->createToken('crm-verification-token')->plainTextToken;

        return view($user->isAdmin() ? 'admin.verifications' : 'crm.verifications', [
            'api_token' => $token,
            'verification_panel_role' => $user->isAdmin() ? 'Admin' : ($isSalesManagerPanel ? 'Manager' : 'CRM'),
            'verification_layout' => $isSalesManagerPanel ? 'sales-manager.layout' : 'layouts.app',
            'verification_api_base_url' => url($isSalesManagerPanel ? '/api/sales-manager' : '/api/crm'),
            'verification_pending_url' => url('/api/admin/verifications/pending'),
            'verification_pending_closers_url' => url('/api/admin/verifications/pending-closers'),
            'verification_verified_url' => url('/api/admin/verifications/verified'),
            'verification_pending_incentives_url' => url('/api/admin/verifications/pending-incentives'),
            'verification_media_url' => route($isSalesManagerPanel ? 'sales-manager.verifications.media' : 'crm.verifications.media'),
            'verification_closer_kyc_url_template' => route($isSalesManagerPanel ? 'sales-manager.verifications.closer-kyc' : 'crm.verifications.closer-kyc', ['siteVisit' => '__SITE_VISIT__']),
        ]);
    }

    public function media(Request $request)
    {
        $path = trim((string) $request->query('path', ''));
        if ($path === '') {
            abort(404);
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^https?://[^/]+#i', '', $path);
        $path = ltrim($path, '/');
        $path = preg_replace('#^public/storage/#i', '', $path);
        $path = preg_replace('#^storage/storage/#i', 'storage/', $path);
        $path = preg_replace('#^public/#i', '', $path);
        $path = preg_replace('#^storage/#i', '', $path);

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }

    public function closerKyc(SiteVisit $siteVisit, KycFormSchemaService $kycFormSchemaService)
    {
        abort_unless(
            in_array($siteVisit->closer_status, ['pending_crm', 'approved', 'verified', 'rejected'], true)
                || $siteVisit->kyc_submitted_at !== null,
            404
        );

        $siteVisit->load([
            'lead:id,name,phone,email,source,preferred_location,budget',
            'assignedTo:id,name',
            'creator:id,name',
        ]);

        return view('crm.closer-kyc', [
            'siteVisit' => $siteVisit,
            'kycPayload' => $this->buildCloserKycPayload($siteVisit, $kycFormSchemaService),
        ]);
    }

    private function buildCloserKycPayload(SiteVisit $siteVisit, KycFormSchemaService $kycFormSchemaService): array
    {
        $kycDocuments = collect($siteVisit->kyc_documents ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildVerificationFileUrl($path, ['closings/kyc']))
            ->values()
            ->all();

        $proofPhotos = collect($siteVisit->closer_request_proof_photos ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildVerificationFileUrl($path, ['site-visits/closer-proof']))
            ->values()
            ->all();

        $bookingPaymentProofs = collect($siteVisit->booking_payment_proofs ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildVerificationFileUrl($path, ['closings/payment-proofs']))
            ->values()
            ->all();

        $schema = $kycFormSchemaService->buildReadPayload($siteVisit);
        $schema['sections'] = collect($schema['sections'] ?? [])->map(function (array $section) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
            $section['fields'] = collect($section['fields'] ?? [])->map(function (array $field) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
                if (($field['field_key'] ?? null) === 'kyc_documents') {
                    $field['value'] = $kycDocuments;
                }

                if (($field['field_key'] ?? null) === 'proof_photos') {
                    $field['value'] = $proofPhotos;
                }

                if (($field['field_key'] ?? null) === 'booking_payment_proofs') {
                    $field['value'] = $bookingPaymentProofs;
                }

                return $field;
            })->values()->all();

            return $section;
        })->values()->all();

        return [
            'schema' => $schema,
            'kyc_documents' => $kycDocuments,
            'proof_photos' => $proofPhotos,
            'booking_payment_proofs' => $bookingPaymentProofs,
            'booking_document_reviews' => $siteVisit->booking_document_reviews ?? [],
            'customer_name' => $siteVisit->customer_name ?: ($siteVisit->lead?->name ?: 'Customer'),
            'phone' => $siteVisit->phone ?: $siteVisit->lead?->phone,
            'project' => $siteVisit->project ?: ($siteVisit->property_name ?: 'Project not filled'),
            'status' => $siteVisit->hasCompleteKyc() ? 'KYC complete' : 'KYC pending',
        ];
    }

    private function buildVerificationFileUrl(?string $path, array $directoryHints = []): ?string
    {
        $resolvedPath = $this->resolveVerificationStoragePath($path, $directoryHints);

        if ($resolvedPath === null) {
            return null;
        }

        if (filter_var($resolvedPath, FILTER_VALIDATE_URL)) {
            return $resolvedPath;
        }

        return route('crm.verifications.media', ['path' => $resolvedPath]);
    }

    private function resolveVerificationStoragePath(?string $path, array $directoryHints = []): ?string
    {
        $normalized = trim((string) $path);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_URL)) {
            return $normalized !== '' ? $normalized : null;
        }

        $normalized = preg_replace('#^/?storage/#i', '', $normalized);
        $normalized = preg_replace('#^/?public/#i', '', $normalized);
        $normalized = ltrim((string) $normalized, '/');

        $candidates = [$normalized];

        if (!str_contains($normalized, '/')) {
            foreach ($directoryHints as $hint) {
                $hint = trim((string) $hint, '/');
                if ($hint !== '') {
                    $candidates[] = $hint . '/' . $normalized;
                }
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
