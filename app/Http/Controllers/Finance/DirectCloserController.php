<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\CloserWorkflowService;
use App\Services\KycFormSchemaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DirectCloserController extends Controller
{
    public function create(KycFormSchemaService $kycFormSchemaService)
    {
        $bookingUsers = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ]))
            ->with('role')
            ->orderBy('name')
            ->get();

        $approvalAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
            ->orderBy('name')
            ->get();

        $schema = $kycFormSchemaService->getResolvedSchema();
        $sources = Lead::sourceOptions();

        return view('finance-manager.direct-closers.create', compact('bookingUsers', 'approvalAdmins', 'schema', 'sources'));
    }

    public function store(
        Request $request,
        KycFormSchemaService $kycFormSchemaService,
        CloserWorkflowService $closerWorkflowService
    ) {
        $rules = array_merge([
            'booking_user_id' => ['required', 'integer', 'exists:users,id'],
            'approval_admin_id' => ['required', 'integer', 'exists:users,id'],
            'lead_source' => ['required', 'string', 'max:100'],
            'lead_name' => ['required', 'string', 'max:255'],
            'lead_phone' => ['required', 'string', 'max:30'],
            'lead_phone_country_iso' => ['nullable', 'string', 'size:2'],
            'lead_email' => ['nullable', 'email', 'max:255'],
            'lead_location' => ['nullable', 'string', 'max:255'],
            'lead_budget' => ['nullable', 'string', 'max:255'],
            'finance_remark' => ['nullable', 'string', 'max:1000'],
        ], $kycFormSchemaService->getValidationRules(true, $request->integer('kyc_form_id')));
        $rules['actual_closer_date'] = ['required', 'date', 'before_or_equal:today'];
        $rules['booking_date'] = ['required', 'date', 'before_or_equal:today'];

        $validated = $request->validate($rules);

        $bookingUser = User::query()
            ->where('id', $validated['booking_user_id'])
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ]))
            ->firstOrFail();

        $approvalAdmin = User::query()
            ->where('id', $validated['approval_admin_id'])
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))
            ->firstOrFail();

        $customFiles = $this->extractCustomKycFiles($request);

        try {
            $siteVisit = DB::transaction(function () use ($request, $validated, $bookingUser, $approvalAdmin, $closerWorkflowService, $customFiles) {
                $intake = app(\App\Services\LeadIntakeService::class)->createOrReenquire([
                    'name' => trim((string) $validated['lead_name']),
                    'phone' => trim((string) $validated['lead_phone']),
                    'phone_country_iso' => $validated['lead_phone_country_iso'] ?? null,
                    'email' => $validated['lead_email'] ?? null,
                    'source' => Lead::normalizeSource((string) $validated['lead_source']),
                    'status' => 'connected',
                    'preferred_location' => $validated['lead_location'] ?? null,
                    'budget' => $validated['lead_budget'] ?? null,
                    'notes' => trim((string) ($validated['finance_remark'] ?? '')) ?: 'Finance direct closer request',
                    'created_by' => $request->user()->id,
                    'status_auto_update_enabled' => false,
                ], (string) $validated['lead_source'], $request->user()->id);
                $lead = $intake['lead'];

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $bookingUser->id,
                    'assigned_by' => $request->user()->id,
                    'assignment_type' => $intake['was_created'] ? 'primary' : 'secondary',
                    'assignment_method' => 'finance_direct_closer',
                    'notes' => 'Finance direct closer booking credit',
                    'assigned_at' => now(),
                    'is_active' => $intake['was_created'] || !$lead->activeAssignments()->exists(),
                ]);

                $actualCloserDate = Carbon::parse($validated['actual_closer_date'])->startOfDay();
                $propertyName = trim((string) ($validated['booking_project_name'] ?? $validated['project'] ?? 'Direct Booking'));

                $siteVisit = SiteVisit::create([
                    'lead_id' => $lead->id,
                    'created_by' => $request->user()->id,
                    'assigned_to' => $bookingUser->id,
                    'property_name' => $propertyName !== '' ? $propertyName : 'Direct Booking',
                    'property_address' => $validated['lead_location'] ?? null,
                    'scheduled_at' => $actualCloserDate,
                    'completed_at' => now(),
                    'status' => 'completed',
                    'verification_status' => 'verified',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'customer_name' => trim((string) $validated['lead_name']),
                    'phone' => $lead->phone,
                    'project' => $propertyName,
                    'budget_range' => $validated['lead_budget'] ?? null,
                    'visit_notes' => trim((string) ($validated['finance_remark'] ?? 'Finance direct closer request')),
                    'is_finance_direct_closer' => true,
                    'approval_admin_id' => $approvalAdmin->id,
                    'booking_form_version' => 'v2',
                    'booking_lifecycle_status' => 'draft',
                    'booking_activity_log' => [[
                        'action' => 'finance_direct_created',
                        'user_id' => $request->user()->id,
                        'user_name' => $request->user()->name,
                        'remark' => trim((string) ($validated['finance_remark'] ?? '')),
                        'approval_admin_id' => $approvalAdmin->id,
                        'booking_user_id' => $bookingUser->id,
                        'at' => now()->toIso8601String(),
                    ]],
                ]);

                return $closerWorkflowService->submitFinanceDirectKyc(
                    $siteVisit,
                    $request->user(),
                    $validated,
                    $request->file('kyc_documents', []),
                    $request->file('proof_photos', []),
                    $request->file('booking_payment_proofs', []),
                    $customFiles
                );
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['direct_closer' => $e->getMessage()]);
        }

        return redirect()
            ->route('finance-manager.direct-closers.create')
            ->with('success', 'Direct closer request Admin approval ke liye bhej diya. Request ID #' . $siteVisit->id);
    }

    private function extractCustomKycFiles(Request $request): array
    {
        return collect($request->allFiles())
            ->except(['kyc_documents', 'proof_photos', 'booking_payment_proofs'])
            ->all();
    }
}
