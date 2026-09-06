<?php

namespace App\Services;

use App\Models\Incentive;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CloserWorkflowService
{
    public function __construct(
        protected KycFormSchemaService $kycFormSchemaService,
        protected LeadSingleOpenTaskService $leadSingleOpenTaskService
    ) {
    }

    public function moveToCloser(SiteVisit $siteVisit, User $actor): SiteVisit
    {
        $this->assertCanManage($siteVisit, $actor);
        $this->assertVerifiedVisit($siteVisit);

        if (in_array($siteVisit->closer_status, ['approved', 'rejected', 'pending_crm'], true)) {
            throw new \RuntimeException('This visit is already in the closer pipeline.');
        }

        return DB::transaction(function () use ($siteVisit) {
            $siteVisit->closer_status = 'draft';
            $siteVisit->converted_to_closer_at = $siteVisit->converted_to_closer_at ?: now();
            $siteVisit->closer_rejection_reason = null;
            $siteVisit->closer_review_remark = null;
            $siteVisit->closing_rejection_reason = null;
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function saveKycDraft(
        SiteVisit $siteVisit,
        User $actor,
        array $data,
        array $kycDocuments = [],
        array $proofPhotos = [],
        array $bookingPaymentProofs = [],
        array $customFiles = [],
        ?array $existingKycDocuments = null,
        ?array $existingProofPhotos = null,
        ?array $existingBookingPaymentProofs = null,
        bool $replaceKycDocuments = false,
        bool $replaceProofPhotos = false,
        bool $replaceBookingPaymentProofs = false
    ): SiteVisit {
        $this->assertCanManage($siteVisit, $actor);
        $this->assertEditableKyc($siteVisit);

        return DB::transaction(function () use ($siteVisit, $actor, $data, $kycDocuments, $proofPhotos, $bookingPaymentProofs, $customFiles, $existingKycDocuments, $existingProofPhotos, $existingBookingPaymentProofs, $replaceKycDocuments, $replaceProofPhotos, $replaceBookingPaymentProofs) {
            $this->persistKycData(
                $siteVisit,
                $data,
                $kycDocuments,
                $proofPhotos,
                $bookingPaymentProofs,
                $customFiles,
                $existingKycDocuments,
                $existingProofPhotos,
                $existingBookingPaymentProofs,
                $replaceKycDocuments,
                $replaceProofPhotos,
                $replaceBookingPaymentProofs,
                true
            );

            $this->syncDocumentReviewMetadata($siteVisit, $actor);
            $this->appendBookingActivity($siteVisit, 'draft_saved', $actor);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function saveFinanceKycDraft(
        SiteVisit $siteVisit,
        User $actor,
        array $data,
        array $kycDocuments = [],
        array $proofPhotos = [],
        array $bookingPaymentProofs = [],
        array $customFiles = [],
        ?array $existingKycDocuments = null,
        ?array $existingProofPhotos = null,
        ?array $existingBookingPaymentProofs = null,
        bool $replaceKycDocuments = false,
        bool $replaceProofPhotos = false,
        bool $replaceBookingPaymentProofs = false
    ): SiteVisit {
        $this->assertCanFinanceManage($siteVisit, $actor);
        $this->assertVerifiedVisit($siteVisit);

        return DB::transaction(function () use ($siteVisit, $actor, $data, $kycDocuments, $proofPhotos, $bookingPaymentProofs, $customFiles, $existingKycDocuments, $existingProofPhotos, $existingBookingPaymentProofs, $replaceKycDocuments, $replaceProofPhotos, $replaceBookingPaymentProofs) {
            $this->persistKycData(
                $siteVisit,
                $data,
                $kycDocuments,
                $proofPhotos,
                $bookingPaymentProofs,
                $customFiles,
                $existingKycDocuments,
                $existingProofPhotos,
                $existingBookingPaymentProofs,
                $replaceKycDocuments,
                $replaceProofPhotos,
                $replaceBookingPaymentProofs,
                false
            );

            $this->syncDocumentReviewMetadata($siteVisit, $actor);
            $this->appendBookingActivity($siteVisit, 'finance_kyc_updated', $actor);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function submitKyc(
        SiteVisit $siteVisit,
        User $actor,
        array $data,
        array $kycDocuments = [],
        array $proofPhotos = [],
        array $bookingPaymentProofs = [],
        array $customFiles = [],
        ?array $existingKycDocuments = null,
        ?array $existingProofPhotos = null,
        ?array $existingBookingPaymentProofs = null,
        bool $replaceKycDocuments = false,
        bool $replaceProofPhotos = false,
        bool $replaceBookingPaymentProofs = false
    ): SiteVisit {
        $this->saveKycDraft(
            $siteVisit,
            $actor,
            $data,
            $kycDocuments,
            $proofPhotos,
            $bookingPaymentProofs,
            $customFiles,
            $existingKycDocuments,
            $existingProofPhotos,
            $existingBookingPaymentProofs,
            $replaceKycDocuments,
            $replaceProofPhotos,
            $replaceBookingPaymentProofs
        );

        $siteVisit->refresh();

        $this->assertBookingV2Ready($siteVisit);

        if (!$siteVisit->hasCompleteKyc()) {
            throw new \RuntimeException('Complete KYC details and documents are required before submission.');
        }

        return DB::transaction(function () use ($siteVisit, $actor) {
            $wasCorrection = in_array($siteVisit->closer_status, ['correction_required', 'rejected'], true);
            if (in_array($siteVisit->closer_status, ['correction_required', 'rejected'], true)) {
                $siteVisit->closer_resubmission_count = (int) $siteVisit->closer_resubmission_count + 1;
            }

            $siteVisit->closer_status = 'pending_crm';
            $siteVisit->closing_verification_status = 'pending';
            $siteVisit->booking_lifecycle_status = 'submitted';
            $siteVisit->closer_submitted_at = now();
            $siteVisit->closer_submitted_by = $actor->id;
            $siteVisit->kyc_submitted_at = now();
            $siteVisit->closer_review_remark = null;
            $siteVisit->closer_rejection_reason = null;
            $siteVisit->closing_rejection_reason = null;
            $this->appendBookingActivity($siteVisit, $wasCorrection ? 'resubmitted' : 'submitted', $actor);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function submitFinanceDirectKyc(
        SiteVisit $siteVisit,
        User $actor,
        array $data,
        array $kycDocuments = [],
        array $proofPhotos = [],
        array $bookingPaymentProofs = [],
        array $customFiles = []
    ): SiteVisit {
        if (!$actor->isFinanceManager()) {
            throw new \RuntimeException('Only Finance Manager can create a direct closer request.');
        }

        $this->saveFinanceKycDraft(
            $siteVisit,
            $actor,
            $data,
            $kycDocuments,
            $proofPhotos,
            $bookingPaymentProofs,
            $customFiles,
            null,
            null,
            null,
            false,
            false,
            false
        );

        $siteVisit->refresh();
        $this->assertBookingV2Ready($siteVisit);

        if (!$siteVisit->hasCompleteKyc()) {
            throw new \RuntimeException('Complete KYC details and documents are required before submission.');
        }

        return DB::transaction(function () use ($siteVisit, $actor) {
            $siteVisit->closer_status = 'pending_crm';
            $siteVisit->closing_verification_status = 'pending';
            $siteVisit->booking_lifecycle_status = 'submitted';
            $siteVisit->closer_submitted_at = now();
            $siteVisit->closer_submitted_by = $actor->id;
            $siteVisit->kyc_submitted_at = now();
            $siteVisit->closer_review_remark = null;
            $siteVisit->closer_rejection_reason = null;
            $siteVisit->closing_rejection_reason = null;
            $this->appendBookingActivity($siteVisit, 'finance_direct_submitted', $actor);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function sendBackForCorrection(SiteVisit $siteVisit, User $actor, string $remark, ?array $sectionRemarks = null): SiteVisit
    {
        if (!$actor->isAdmin()) {
            throw new \RuntimeException('Only Admin can review closer submissions.');
        }

        if ($siteVisit->approval_admin_id && (int) $siteVisit->approval_admin_id !== (int) $actor->id) {
            throw new \RuntimeException('This closer is assigned to another Admin for approval.');
        }

        if ($siteVisit->closer_status !== 'pending_crm') {
            throw new \RuntimeException('Only pending CRM closers can be sent back for correction.');
        }

        return DB::transaction(function () use ($siteVisit, $actor, $remark, $sectionRemarks) {
            $siteVisit->closer_status = 'correction_required';
            $siteVisit->closing_verification_status = 'rejected';
            $siteVisit->booking_lifecycle_status = 'crm_correction_required';
            $siteVisit->closer_review_remark = $remark;
            $siteVisit->closing_rejection_reason = $remark;
            $siteVisit->kyc_section_remarks = $sectionRemarks;
            $siteVisit->closer_reviewed_at = now();
            $siteVisit->closer_reviewed_by = $actor->id;
            $siteVisit->kyc_last_corrected_at = now();
            $this->appendBookingActivity($siteVisit, 'crm_correction_required', $actor, $remark);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function approveCloser(SiteVisit $siteVisit, User $actor, ?string $remark = null, ?string $workflowType = null): SiteVisit
    {
        if (!$actor->isAdmin()) {
            throw new \RuntimeException('Only Admin can approve closers.');
        }

        if ($siteVisit->approval_admin_id && (int) $siteVisit->approval_admin_id !== (int) $actor->id) {
            throw new \RuntimeException('This closer is assigned to another Admin for approval.');
        }

        $canApprove = $actor->isAdmin()
            || ($workflowType
                ? app(VerificationRoutingService::class)->canVerify($actor, $siteVisit, $workflowType)
                : $actor->isCrm());

        if (!$canApprove) {
            throw new \RuntimeException('Only CRM or Admin can approve closers.');
        }

        if ($siteVisit->closer_status !== 'pending_crm') {
            throw new \RuntimeException('Only pending CRM closers can be approved.');
        }

        if (!$siteVisit->hasCompleteKyc()) {
            throw new \RuntimeException('Complete KYC is required before closer approval.');
        }

        return DB::transaction(function () use ($siteVisit, $actor, $remark) {
            $siteVisit->closer_status = 'approved';
            $siteVisit->booking_lifecycle_status = 'booking_confirmed';
            $siteVisit->closer_verified_at = now();
            $siteVisit->closer_verified_by = $actor->id;
            $siteVisit->closing_verification_status = 'verified';
            $siteVisit->closing_verified_at = now();
            $siteVisit->closing_verified_by = $actor->id;
            if ($siteVisit->actual_closer_date) {
                $siteVisit->actual_closer_date_approved_at = now();
                $siteVisit->actual_closer_date_approved_by = $actor->id;
            }
            $siteVisit->closer_reviewed_at = now();
            $siteVisit->closer_reviewed_by = $actor->id;
            $siteVisit->closer_review_remark = $remark;
            $siteVisit->closing_rejection_reason = null;
            $siteVisit->closer_rejection_reason = null;
            $siteVisit->kyc_section_remarks = null;
            $this->appendBookingActivity($siteVisit, 'booking_confirmed', $actor, $remark);
            $siteVisit->save();

            if ($siteVisit->lead) {
                $siteVisit->lead->update([
                    'status' => 'closed',
                    'status_auto_update_enabled' => false,
                ]);

                $this->leadSingleOpenTaskService->cancelOpenTasksAfterLeadClosure(
                    $siteVisit->lead->id,
                    $actor->id
                );
            }

            return $siteVisit->fresh();
        });
    }

    public function rejectCloser(SiteVisit $siteVisit, User $actor, string $reason, ?array $sectionRemarks = null, ?string $workflowType = null): SiteVisit
    {
        if (!$actor->isAdmin()) {
            throw new \RuntimeException('Only Admin can reject closers.');
        }

        if ($siteVisit->approval_admin_id && (int) $siteVisit->approval_admin_id !== (int) $actor->id) {
            throw new \RuntimeException('This closer is assigned to another Admin for approval.');
        }

        $canReject = $actor->isAdmin()
            || ($workflowType
                ? app(VerificationRoutingService::class)->canVerify($actor, $siteVisit, $workflowType)
                : $actor->isCrm());

        if (!$canReject) {
            throw new \RuntimeException('Only CRM or Admin can reject closers.');
        }

        if (!in_array($siteVisit->closer_status, ['pending_crm', 'correction_required'], true)) {
            throw new \RuntimeException('Only active closer submissions can be rejected.');
        }

        return DB::transaction(function () use ($siteVisit, $actor, $reason, $sectionRemarks) {
            $siteVisit->closer_status = 'rejected';
            $siteVisit->booking_lifecycle_status = 'cancelled';
            $siteVisit->closer_rejection_reason = $reason;
            $siteVisit->closing_verification_status = 'rejected';
            $siteVisit->closing_rejection_reason = $reason;
            $siteVisit->kyc_section_remarks = $sectionRemarks;
            $siteVisit->closer_reviewed_at = now();
            $siteVisit->closer_reviewed_by = $actor->id;
            $this->appendBookingActivity($siteVisit, 'cancelled', $actor, $reason);
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }

    public function isIncentiveUnlocked(SiteVisit $siteVisit, ?User $user = null): bool
    {
        $hasExistingIncentive = $siteVisit->incentives()
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->where('type', 'closer')
            ->exists();

        return $siteVisit->closer_status === 'approved'
            && $siteVisit->hasCompleteKyc()
            && !$hasExistingIncentive;
    }

    public function buildPipelineQuery(User $user): Builder
    {
        $query = SiteVisit::query()
            ->with([
                'lead:id,name,phone,email,source,preferred_location,budget,requirements,notes',
                'creator:id,name',
                'assignedTo:id,name',
                'closerSubmittedBy:id,name',
                'closerReviewedBy:id,name',
                'financeTransferredBy:id,name',
                'financeReviewedBy:id,name',
                'incentives',
            ])
            ->where('status', 'completed')
            ->where('verification_status', 'verified')
            ->where(function ($deadQuery) {
                $deadQuery->whereNull('is_dead')->orWhere('is_dead', false);
            });

        if ($user->isAdmin() || $user->isCrm()) {
            return $query;
        }

        if ($user->isSalesHead()) {
            $visibleOwnerIds = collect($user->getAllTeamMemberIds())
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            return $query->whereIn('assigned_to', $visibleOwnerIds);
        }

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            $visibleOwnerIds = $user->teamMembers()->pluck('id')
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            return $query->whereIn('assigned_to', $visibleOwnerIds);
        }

        if ($user->isAssistantSalesManager() || $user->isSalesExecutive()) {
            return $query->where('assigned_to', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function assertCanManage(SiteVisit $siteVisit, User $actor): void
    {
        if ($actor->isAdmin() || $actor->isCrm()) {
            return;
        }

        if (!$actor->isAssistantSalesManager() && !$actor->isSeniorManager() && !$actor->isSalesManager()) {
            throw new \RuntimeException('You are not allowed to manage this closer.');
        }

        if ((int) $siteVisit->assigned_to === (int) $actor->id || (int) $siteVisit->created_by === (int) $actor->id) {
            return;
        }

        $assignedUser = $siteVisit->assignedTo;
        if ($assignedUser && $actor->isSeniorOf($assignedUser)) {
            return;
        }

        throw new \RuntimeException('You are not allowed to manage this closer.');
    }

    private function assertVerifiedVisit(SiteVisit $siteVisit): void
    {
        if ($siteVisit->status !== 'completed' || $siteVisit->verification_status !== 'verified') {
            throw new \RuntimeException('Only verified site visits can enter the closer pipeline.');
        }
    }

    private function assertEditableKyc(SiteVisit $siteVisit): void
    {
        $this->assertVerifiedVisit($siteVisit);

        if (!in_array($siteVisit->closer_status, [null, 'draft', 'pending_crm', 'correction_required', 'rejected'], true)) {
            throw new \RuntimeException('CRM verified closer KYC is locked and cannot be edited.');
        }
    }

    private function assertCanFinanceManage(SiteVisit $siteVisit, User $actor): void
    {
        if ($actor->isAdmin() || $actor->isFinanceManager()) {
            return;
        }

        throw new \RuntimeException('You are not allowed to edit this KYC from finance.');
    }

    private function appendBookingActivity(SiteVisit $siteVisit, string $action, User $actor, ?string $remark = null): void
    {
        $activity = collect((array) $siteVisit->booking_activity_log)
            ->filter(fn ($entry) => is_array($entry))
            ->values()
            ->all();

        $activity[] = [
            'action' => $action,
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'remark' => $remark,
            'at' => now()->toIso8601String(),
        ];

        $siteVisit->booking_activity_log = array_slice($activity, -100);
    }

    /**
     * @param array<int, string>|null $current
     * @param array<int, string>|null $requested
     * @return array<int, string>
     */
    private function resolveRetainedFiles(?array $current, ?array $requested): array
    {
        $currentFiles = collect((array) $current)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();

        if ($requested === null) {
            return $currentFiles->all();
        }

        $requestedLookup = collect($requested)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->flip();

        return $currentFiles
            ->filter(fn ($path) => $requestedLookup->has($path))
            ->values()
            ->all();
    }

    private function extractKycFields(array $data): array
    {
        $primaryApplicant = [
            'name' => trim((string) ($data['primary_applicant_name'] ?? '')),
            'relation_name' => trim((string) ($data['primary_applicant_relation_name'] ?? '')),
            'date_of_birth' => $data['primary_applicant_date_of_birth'] ?? null,
            'nationality' => trim((string) ($data['primary_applicant_nationality'] ?? '')),
            'occupation_service' => !empty($data['primary_applicant_occupation_service']),
            'occupation_professional' => !empty($data['primary_applicant_occupation_professional']),
            'occupation_housewife' => !empty($data['primary_applicant_occupation_housewife']),
            'occupation_business' => !empty($data['primary_applicant_occupation_business']),
            'occupation_any_other' => trim((string) ($data['primary_applicant_occupation_any_other'] ?? '')),
            'resident_indian' => !empty($data['primary_applicant_resident_indian']),
            'resident_non_resident' => !empty($data['primary_applicant_resident_non_resident']),
            'resident_foreign_national' => !empty($data['primary_applicant_resident_foreign_national']),
            'resident_other' => trim((string) ($data['primary_applicant_resident_other'] ?? '')),
            'marital_status_married' => !empty($data['primary_applicant_marital_status_married']),
            'marital_status_unmarried' => !empty($data['primary_applicant_marital_status_unmarried']),
            'pan_no' => strtoupper(trim((string) ($data['primary_applicant_pan_no'] ?? ''))),
            'aadhaar_no' => trim((string) ($data['primary_applicant_aadhaar_no'] ?? '')),
            'address' => trim((string) ($data['primary_applicant_address'] ?? '')),
            'communication_address' => trim((string) ($data['primary_applicant_communication_address'] ?? '')),
            'city' => trim((string) ($data['primary_applicant_city'] ?? '')),
            'state' => trim((string) ($data['primary_applicant_state'] ?? '')),
            'country' => trim((string) ($data['primary_applicant_country'] ?? '')),
            'pin' => trim((string) ($data['primary_applicant_pin'] ?? '')),
            'email' => trim((string) ($data['primary_applicant_email'] ?? '')),
            'tel_no' => trim((string) ($data['primary_applicant_tel_no'] ?? '')),
            'mobile_no' => trim((string) ($data['primary_applicant_mobile_no'] ?? '')),
            'fax_no' => trim((string) ($data['primary_applicant_fax_no'] ?? '')),
        ];

        $jointApplicant = [
            'name' => trim((string) ($data['joint_applicant_name'] ?? '')),
            'relation_name' => trim((string) ($data['joint_applicant_relation_name'] ?? '')),
            'date_of_birth' => $data['joint_applicant_date_of_birth'] ?? null,
            'nationality' => trim((string) ($data['joint_applicant_nationality'] ?? '')),
            'occupation_service' => !empty($data['joint_applicant_occupation_service']),
            'occupation_professional' => !empty($data['joint_applicant_occupation_professional']),
            'occupation_housewife' => !empty($data['joint_applicant_occupation_housewife']),
            'occupation_business' => !empty($data['joint_applicant_occupation_business']),
            'occupation_any_other' => trim((string) ($data['joint_applicant_occupation_any_other'] ?? '')),
            'resident_indian' => !empty($data['joint_applicant_resident_indian']),
            'resident_non_resident' => !empty($data['joint_applicant_resident_non_resident']),
            'resident_foreign_national' => !empty($data['joint_applicant_resident_foreign_national']),
            'resident_other' => trim((string) ($data['joint_applicant_resident_other'] ?? '')),
            'marital_status_married' => !empty($data['joint_applicant_marital_status_married']),
            'marital_status_unmarried' => !empty($data['joint_applicant_marital_status_unmarried']),
            'pan_no' => strtoupper(trim((string) ($data['joint_applicant_pan_no'] ?? ''))),
            'address' => trim((string) ($data['joint_applicant_address'] ?? '')),
            'communication_address' => trim((string) ($data['joint_applicant_communication_address'] ?? '')),
            'city' => trim((string) ($data['joint_applicant_city'] ?? '')),
            'state' => trim((string) ($data['joint_applicant_state'] ?? '')),
            'country' => trim((string) ($data['joint_applicant_country'] ?? '')),
            'pin' => trim((string) ($data['joint_applicant_pin'] ?? '')),
            'email' => trim((string) ($data['joint_applicant_email'] ?? '')),
            'tel_no' => trim((string) ($data['joint_applicant_tel_no'] ?? '')),
            'mobile_no' => trim((string) ($data['joint_applicant_mobile_no'] ?? '')),
            'fax_no' => trim((string) ($data['joint_applicant_fax_no'] ?? '')),
        ];

        $unitDetails = [
            'applicant_type' => trim((string) ($data['booking_applicant_type'] ?? 'Individual')),
            'joint_applicant_available' => !empty($data['booking_joint_applicant_available']),
            'project_name' => trim((string) ($data['booking_project_name'] ?? '')),
            'booking_date' => $data['booking_date'] ?? null,
            'deal_type' => trim((string) ($data['booking_deal_type'] ?? '')),
            'booking_type' => trim((string) ($data['booking_type'] ?? '')),
            'unit_type' => trim((string) ($data['booking_unit_type'] ?? '')),
            'booking_amount_paid' => trim((string) ($data['booking_amount_paid'] ?? '')),
            'booking_payment_mode' => trim((string) ($data['booking_payment_mode'] ?? '')),
            'booking_payment_reference' => trim((string) ($data['booking_payment_reference'] ?? '')),
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'company_registered_address' => trim((string) ($data['company_registered_address'] ?? '')),
            'company_date_of_incorporation' => $data['company_date_of_incorporation'] ?? null,
            'company_incorporation_no' => trim((string) ($data['company_incorporation_no'] ?? '')),
            'company_pan_no' => strtoupper(trim((string) ($data['company_pan_no'] ?? ''))),
            'company_gst_no' => strtoupper(trim((string) ($data['company_gst_no'] ?? ''))),
            'company_business_nature' => trim((string) ($data['company_business_nature'] ?? '')),
            'company_signatory_name' => trim((string) ($data['company_signatory_name'] ?? '')),
            'company_signatory_relation' => trim((string) ($data['company_signatory_relation'] ?? '')),
            'company_signatory_designation' => trim((string) ($data['company_signatory_designation'] ?? '')),
            'company_email' => trim((string) ($data['company_email'] ?? '')),
            'company_contact_1' => trim((string) ($data['company_contact_1'] ?? '')),
            'company_contact_2' => trim((string) ($data['company_contact_2'] ?? '')),
            'unit_no' => trim((string) ($data['unit_details_unit_no'] ?? '')),
            'block_cluster' => trim((string) ($data['unit_details_block_cluster'] ?? '')),
            'floor' => trim((string) ($data['unit_details_floor'] ?? '')),
            'carpet_area_sq_mt' => trim((string) ($data['unit_details_carpet_area_sq_mt'] ?? '')),
            'carpet_area_sq_ft' => trim((string) ($data['unit_details_carpet_area_sq_ft'] ?? '')),
            'build_up_area_sq_ft' => trim((string) ($data['unit_details_build_up_area_sq_ft'] ?? '')),
            'super_area_sq_mt' => trim((string) ($data['unit_details_super_area_sq_mt'] ?? '')),
            'super_area_sq_ft' => trim((string) ($data['unit_details_super_area_sq_ft'] ?? '')),
            'basic_sale_price' => trim((string) ($data['unit_details_basic_sale_price'] ?? '')),
            'plc_amount' => trim((string) ($data['unit_details_plc_amount'] ?? '')),
            'other_charges' => trim((string) ($data['unit_details_other_charges'] ?? '')),
            'discount' => trim((string) ($data['unit_details_discount'] ?? '')),
            'discount_remark' => trim((string) ($data['unit_details_discount_remark'] ?? '')),
            'final_total' => trim((string) ($data['unit_details_final_total'] ?? '')),
            'override_total' => !empty($data['unit_details_override_total']),
            'override_reason' => trim((string) ($data['unit_details_override_reason'] ?? '')),
            'payment_plan' => trim((string) ($data['unit_details_payment_plan'] ?? '')),
            'car_parking_covered' => !empty($data['unit_details_car_parking_covered']),
            'car_parking_open' => !empty($data['unit_details_car_parking_open']),
            'club_membership_charges' => trim((string) ($data['unit_details_club_membership_charges'] ?? '')),
            'payment_plan_construction_linked' => !empty($data['unit_details_payment_plan_construction_linked']),
            'payment_plan_down_payment' => !empty($data['unit_details_payment_plan_down_payment']),
            'payment_plan_other' => !empty($data['unit_details_payment_plan_other']),
            'payment_plan_other_text' => trim((string) ($data['unit_details_payment_plan_other_text'] ?? '')),
            'finance_funding_source' => trim((string) ($data['finance_funding_source'] ?? '')),
            'finance_loan_required' => trim((string) ($data['finance_loan_required'] ?? 'No')),
            'finance_bank_name' => trim((string) ($data['finance_bank_name'] ?? '')),
            'finance_loan_amount' => trim((string) ($data['finance_loan_amount'] ?? '')),
            'finance_file_login_date' => $data['finance_file_login_date'] ?? null,
            'finance_sanction_status' => trim((string) ($data['finance_sanction_status'] ?? '')),
            'finance_loan_remark' => trim((string) ($data['finance_loan_remark'] ?? '')),
        ];

        return [
            'actual_closer_date' => $data['actual_closer_date'] ?? null,
            'actual_closer_backdate_reason' => trim((string) ($data['actual_closer_backdate_reason'] ?? '')) ?: null,
            'customer_name' => $primaryApplicant['name'] !== '' ? $primaryApplicant['name'] : ($data['customer_name'] ?? null),
            'nominee_name' => $jointApplicant['name'] !== '' ? $jointApplicant['name'] : ($data['nominee_name'] ?? null),
            'second_customer_name' => $jointApplicant['name'] !== '' ? $jointApplicant['name'] : ($data['second_customer_name'] ?? null),
            'customer_dob' => $primaryApplicant['date_of_birth'] ?: ($data['customer_dob'] ?? null),
            'pan_card' => $primaryApplicant['pan_no'] !== '' ? $primaryApplicant['pan_no'] : ($data['pan_card'] ?? null),
            'aadhaar_card_no' => $primaryApplicant['aadhaar_no'] !== '' ? $primaryApplicant['aadhaar_no'] : ($data['aadhaar_card_no'] ?? null),
            'primary_applicant_details' => $primaryApplicant,
            'joint_applicant_details' => $jointApplicant,
            'unit_details' => $unitDetails,
            'incentive_amount' => $data['incentive_amount'] ?? null,
        ];
    }

    private function persistKycData(
        SiteVisit $siteVisit,
        array $data,
        array $kycDocuments,
        array $proofPhotos,
        array $bookingPaymentProofs,
        array $customFiles,
        ?array $existingKycDocuments,
        ?array $existingProofPhotos,
        ?array $existingBookingPaymentProofs,
        bool $replaceKycDocuments,
        bool $replaceProofPhotos,
        bool $replaceBookingPaymentProofs,
        bool $ensureDraftDefaults
    ): void {
        $schema = $this->kycFormSchemaService->getResolvedSchema($data['kyc_form_id'] ?? $siteVisit->kyc_dynamic_form_id);
        $siteVisit->fill($this->extractKycFields($data));
        $siteVisit->kyc_dynamic_form_id = $schema['id'] ?? $siteVisit->kyc_dynamic_form_id;
        $siteVisit->kyc_custom_fields = $this->kycFormSchemaService->extractCustomFieldValues(
            $data,
            $customFiles,
            $schema['fields'],
            (array) $siteVisit->kyc_custom_fields
        );

        $siteVisit->kyc_documents = $this->mergeStoredFiles(
            $this->resolveRetainedFiles($siteVisit->kyc_documents, $existingKycDocuments),
            $kycDocuments,
            'closings/kyc',
            $replaceKycDocuments
        );
        $siteVisit->closer_request_proof_photos = $this->mergeStoredFiles(
            $this->resolveRetainedFiles($siteVisit->closer_request_proof_photos, $existingProofPhotos),
            $proofPhotos,
            'site-visits/closer-proof',
            $replaceProofPhotos
        );
        $siteVisit->booking_payment_proofs = $this->mergeStoredFiles(
            $this->resolveRetainedFiles($siteVisit->booking_payment_proofs, $existingBookingPaymentProofs),
            $bookingPaymentProofs,
            'closings/payment-proofs',
            $replaceBookingPaymentProofs
        );
        $siteVisit->booking_form_version = 'v2';
        $siteVisit->booking_lifecycle_status = $siteVisit->booking_lifecycle_status ?: 'draft';

        if ($ensureDraftDefaults) {
            $siteVisit->closer_status = $siteVisit->closer_status ?: 'draft';
            $siteVisit->converted_to_closer_at = $siteVisit->converted_to_closer_at ?: now();
        }

        $siteVisit->save();
    }

    private function assertBookingV2Ready(SiteVisit $siteVisit): void
    {
        if ($siteVisit->booking_form_version !== 'v2') {
            return;
        }

        $unit = (array) $siteVisit->unit_details;
        $required = [
            'applicant_type' => 'Applicant type',
            'project_name' => 'Project name',
            'booking_date' => 'Booking date',
            'deal_type' => 'Deal type',
            'booking_type' => 'Booking type',
        ];

        foreach ($required as $key => $label) {
            if (trim((string) ($unit[$key] ?? '')) === '') {
                throw new \RuntimeException($label . ' is required before submission.');
            }
        }

        if (($unit['applicant_type'] ?? '') === 'Company/Firm') {
            foreach (['company_name' => 'Company/Firm name', 'company_pan_no' => 'Company PAN no'] as $key => $label) {
                if (trim((string) ($unit[$key] ?? '')) === '') {
                    throw new \RuntimeException($label . ' is required for company/firm booking.');
                }
            }
        }

        $bookingType = trim((string) ($unit['booking_type'] ?? ''));
        $typeSpecificRequired = match ($bookingType) {
            'Plot' => [
                'unit_no' => 'Unit no',
                'super_area_sq_ft' => 'Super area (sq. ft.)',
                'basic_sale_price' => 'Basic sale price',
            ],
            'Villa' => [
                'unit_no' => 'Unit no',
                'super_area_sq_ft' => 'Super area (sq. ft.)',
                'build_up_area_sq_ft' => 'Build up area (sq. ft.)',
                'carpet_area_sq_ft' => 'Carpet area (sq. ft.)',
                'basic_sale_price' => 'Basic sale price',
            ],
            'Apartment' => [
                'unit_no' => 'Unit no',
                'unit_type' => 'Unit type',
                'block_cluster' => 'Block / Tower',
                'floor' => 'Floor',
                'super_area_sq_ft' => 'Super area (sq. ft.)',
                'build_up_area_sq_ft' => 'Build up area (sq. ft.)',
                'carpet_area_sq_ft' => 'Carpet area (sq. ft.)',
                'basic_sale_price' => 'Basic sale price',
            ],
            'Commercial' => [
                'unit_no' => 'Unit no',
                'unit_type' => 'Unit type',
                'block_cluster' => 'Block / Tower',
                'floor' => 'Floor',
                'super_area_sq_ft' => 'Super area (sq. ft.)',
                'carpet_area_sq_ft' => 'Carpet area (sq. ft.)',
                'basic_sale_price' => 'Basic sale price',
            ],
            default => [
                'unit_no' => 'Unit no',
            ],
        };

        foreach ($typeSpecificRequired as $key => $label) {
            if (trim((string) ($unit[$key] ?? '')) === '') {
                throw new \RuntimeException($label . ' is required for selected booking type.');
            }
        }

        if (trim((string) ($unit['discount'] ?? '')) !== '' && trim((string) ($unit['discount_remark'] ?? '')) === '') {
            throw new \RuntimeException('Discount remark is required when discount is filled.');
        }

        if (!empty($unit['override_total']) && trim((string) ($unit['override_reason'] ?? '')) === '') {
            throw new \RuntimeException('Override reason is required when final total is manually overridden.');
        }

        $loanRequired = strcasecmp(trim((string) ($unit['finance_loan_required'] ?? '')), 'Yes') === 0
            || in_array(trim((string) ($unit['finance_funding_source'] ?? '')), ['Loan', 'Self + Loan'], true);

        if ($loanRequired) {
            foreach (['finance_bank_name' => 'Bank name', 'finance_loan_amount' => 'Loan amount'] as $key => $label) {
                if (trim((string) ($unit[$key] ?? '')) === '') {
                    throw new \RuntimeException($label . ' is required when loan is selected.');
                }
            }
        }

        $this->assertNoDuplicateActiveUnit($siteVisit, $unit);
    }

    private function assertNoDuplicateActiveUnit(SiteVisit $siteVisit, array $unit): void
    {
        $projectName = trim((string) ($unit['project_name'] ?? ''));
        $unitNo = trim((string) ($unit['unit_no'] ?? ''));

        if ($projectName === '' || $unitNo === '') {
            return;
        }

        $duplicateExists = SiteVisit::query()
            ->withoutGlobalScopes()
            ->where('id', '!=', $siteVisit->id)
            ->whereIn('closer_status', ['pending_crm', 'correction_required', 'approved'])
            ->where('unit_details->project_name', $projectName)
            ->where('unit_details->unit_no', $unitNo)
            ->exists();

        if ($duplicateExists) {
            throw new \RuntimeException('This project unit is already used in another active booking. Please verify Project Name and Unit No.');
        }
    }

    private function syncDocumentReviewMetadata(SiteVisit $siteVisit, User $actor): void
    {
        $reviews = collect((array) $siteVisit->booking_document_reviews)
            ->map(fn ($group) => is_array($group) ? $group : [])
            ->all();

        $groups = [
            'kyc_documents' => (array) $siteVisit->kyc_documents,
            'proof_photos' => (array) $siteVisit->closer_request_proof_photos,
            'booking_payment_proofs' => (array) $siteVisit->booking_payment_proofs,
        ];

        foreach ($groups as $groupKey => $paths) {
            $groupReviews = (array) ($reviews[$groupKey] ?? []);
            $nextGroupReviews = [];

            foreach (array_values(array_filter($paths)) as $path) {
                $path = (string) $path;
                $nextGroupReviews[$path] = is_array($groupReviews[$path] ?? null)
                    ? $groupReviews[$path]
                    : [
                        'status' => 'pending',
                        'remark' => null,
                        'uploaded_by' => $actor->id,
                        'uploaded_by_name' => $actor->name,
                        'uploaded_at' => now()->toIso8601String(),
                        'reviewed_by' => null,
                        'reviewed_by_name' => null,
                        'reviewed_at' => null,
                    ];
            }

            $reviews[$groupKey] = $nextGroupReviews;
        }

        $siteVisit->booking_document_reviews = $reviews;
    }

    /**
     * @param array<int, UploadedFile> $uploads
     * @param array<int, string>|null $existing
     * @return array<int, string>
     */
    private function mergeStoredFiles(?array $existing, array $uploads, string $directory, bool $replace): array
    {
        $paths = $replace ? [] : array_values(array_filter((array) $existing));

        foreach ($uploads as $upload) {
            if (!$upload instanceof UploadedFile) {
                continue;
            }

            $filename = $directory . '/' . time() . '_' . uniqid() . '.' . $upload->getClientOriginalExtension();
            $upload->storeAs('public', $filename);
            $paths[] = $filename;
        }

        return array_values(array_unique($paths));
    }
}
