<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\SiteVisitController;
use App\Http\Controllers\Api\TelecallerController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BuilderController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectCollateralController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\UnitTypeController;
use App\Http\Controllers\Api\ProjectDetailController;
use App\Http\Controllers\Api\Crm\AuthController as CrmAuthController;
use App\Http\Controllers\Api\Crm\DashboardController as CrmDashboardController;
use App\Http\Controllers\Api\Crm\LeadController as CrmLeadController;
use App\Http\Controllers\Api\Crm\UserController as CrmUserController;
use App\Http\Controllers\Api\Crm\TransferController as CrmTransferController;
use App\Http\Controllers\Api\Crm\BlacklistController as CrmBlacklistController;
use App\Http\Controllers\Api\Crm\TargetController as CrmTargetController;
use App\Http\Controllers\Api\Crm\VerificationController as CrmVerificationController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\InterestedProjectNameController;
use App\Http\Controllers\Api\PabblyWebhookController;
use App\Http\Controllers\Api\IncentiveController;
use App\Http\Controllers\Api\LeadsPendingResponseController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceLeaveController;
use App\Http\Controllers\Api\AttendanceOutsidePunchRequestController;
use App\Http\Controllers\Api\AttendanceOvertimeController;
use App\Http\Controllers\Api\AttendanceReportExportController;
use App\Http\Controllers\Api\AttendanceRegularizationController;
use App\Http\Controllers\Api\UserPayslipController;
use App\Http\Controllers\Api\FacebookLeadCenterExtensionController;
use App\Http\Controllers\Api\WhatsAppWebExtensionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-api');
Route::post('/login/firebase', [\App\Http\Controllers\Api\FirebaseAuthController::class, 'login']);
Route::get('/mobile/app-update', [\App\Http\Controllers\Api\MobileAppUpdateController::class, 'show']);

// Webhook endpoints (public - rate limited to 60 requests/min per IP)
Route::middleware('throttle:180,1')->group(function () {
    // Pabbly Webhook
    Route::post('/pabbly/webhook', [PabblyWebhookController::class, 'store']);

    // Facebook Lead Ads Webhook (Meta verification + receive)
    Route::get('/webhooks/facebook/leads', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'verify']);
    Route::post('/webhooks/facebook/leads', [\App\Http\Controllers\Api\FacebookWebhookController::class, 'receive']);

    // Facebook Page Webhook Dispatcher (single callback for old manual + OAuth connector routing)
    Route::get('/webhooks/facebook/page', [\App\Http\Controllers\Api\FacebookPageWebhookDispatcherController::class, 'verify']);
    Route::post('/webhooks/facebook/page', [\App\Http\Controllers\Api\FacebookPageWebhookDispatcherController::class, 'receive']);

    // Facebook OAuth Connector Webhook (isolated Phase 1 store-only endpoint)
    Route::get('/webhooks/facebook/oauth-leads', [\App\Http\Controllers\Api\MetaOauthWebhookController::class, 'verify']);
    Route::post('/webhooks/facebook/oauth-leads', [\App\Http\Controllers\Api\MetaOauthWebhookController::class, 'receive']);

    // Instagram Webhook (Meta verification + receive)
    Route::get('/webhooks/instagram', [\App\Http\Controllers\Api\InstagramWebhookController::class, 'verify']);
    Route::post('/webhooks/instagram', [\App\Http\Controllers\Api\InstagramWebhookController::class, 'receive']);

    // MCube Call Tracking Webhook (token validated inside controller)
    Route::post('/webhooks/mcube', [\App\Http\Controllers\Api\McubeWebhookController::class, 'receive']);
    Route::post('/webhooks/bulksmsplans-ivr', [\App\Http\Controllers\Api\BulkSmsPlansIvrWebhookController::class, 'receive']);
    Route::get('/webhooks/meta-waba', [\App\Http\Controllers\Api\MetaWabaWebhookController::class, 'verify']);
    Route::post('/webhooks/meta-waba', [\App\Http\Controllers\Api\MetaWabaWebhookController::class, 'receive']);
    Route::get('/webhooks/whatsapp/incoming', [\App\Http\Controllers\Api\WhatsAppIncomingWebhookController::class, 'verify']);
    Route::post('/webhooks/whatsapp/incoming', [\App\Http\Controllers\Api\WhatsAppIncomingWebhookController::class, 'receive']);
    Route::post('/webhooks/website-leads/{slug}', [\App\Http\Controllers\Api\WebsiteLeadWebhookController::class, 'receive']);
    Route::post('/webhooks/99acres/leads', [\App\Http\Controllers\Api\NinetyNineAcresWebhookController::class, 'receive']);
    Route::post('/webhooks/google-sheets/{integration}', [\App\Http\Controllers\Api\GoogleSheetsWebhookController::class, 'receive'])
        ->name('api.webhooks.google-sheets');

});

// Telecaller public routes
Route::post('/telecaller/login', [TelecallerController::class, 'login'])->middleware('throttle:auth-api');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Notifications (for all authenticated roles - used by chatbot/notification bell)
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'getUnread']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{notification}/click', [\App\Http\Controllers\Api\NotificationController::class, 'markAsClicked']);
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\Api\NotificationController::class, 'clear']);
    Route::delete('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'clearAll']);
    Route::post('/notifications/announcements/{broadcast}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAnnouncementAsRead']);
    Route::post('/notifications/announcements/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAnnouncementsAsRead']);
    Route::post('/notifications/announcements/{broadcast}/acknowledge', [\App\Http\Controllers\Api\NotificationController::class, 'acknowledgeAnnouncement']);
    Route::post('/notifications/announcements/{broadcast}/click', [\App\Http\Controllers\Api\NotificationController::class, 'clickAnnouncement']);
    Route::post('/notifications/announcements/{broadcast}/dismiss', [\App\Http\Controllers\Api\NotificationController::class, 'dismissAnnouncement']);
    Route::post('/notifications/announcements/{broadcast}/popup-shown', [\App\Http\Controllers\Api\NotificationController::class, 'markAnnouncementPopupShown']);
    Route::post('/notifications/announcements/dismiss-all', [\App\Http\Controllers\Api\NotificationController::class, 'dismissAllAnnouncements']);

    // PWA Web Push subscription (for lead-assigned etc. notifications)
    Route::post('/push-subscription', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscription', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'destroy']);

    // FCM Token subscription (Firebase Cloud Messaging)
    Route::post('/fcm-subscription', [\App\Http\Controllers\Api\FcmTokenController::class, 'store']);
    Route::delete('/fcm-subscription', [\App\Http\Controllers\Api\FcmTokenController::class, 'destroy']);
    Route::get('/mobile/summary', [\App\Http\Controllers\Api\MobileSummaryController::class, 'show'])
        ->middleware(['relief.block', 'throttle:12,1']);
    Route::post('/mobile/app-installation', [\App\Http\Controllers\Api\MobileAppInstallationController::class, 'store']);
    Route::post('/mobile/app-download-click', [\App\Http\Controllers\Api\MobileAppInstallationController::class, 'downloadClicked']);
    Route::get('/mobile/features', [\App\Http\Controllers\Api\MobileFeatureController::class, 'show']);
    Route::post('/mobile/diagnostics', [\App\Http\Controllers\Api\MobileDiagnosticController::class, 'store']);
    Route::get('/mobile/incoming-call-lookup', [\App\Http\Controllers\Api\MobileIncomingCallController::class, 'lookup']);
    Route::post('/mobile/incoming-call-leads', [\App\Http\Controllers\Api\MobileIncomingCallController::class, 'store']);
    Route::post('/desktop/issue-reports', [\App\Http\Controllers\Api\DesktopIssueReportController::class, 'store']);

    Route::post('/mcube/outbound-call', [\App\Http\Controllers\Api\McubeOutboundCallController::class, 'store'])
        ->name('api.mcube.outbound-call')
        ->middleware(['prevent.cache', 'throttle:10,1']);
    Route::post('/mcube/outbound-call/{attempt}/fallback', [\App\Http\Controllers\Api\McubeOutboundCallController::class, 'fallback'])
        ->name('api.mcube.outbound-call.fallback')
        ->middleware(['prevent.cache', 'throttle:10,1']);
    Route::post('/leads/{lead}/whatsapp-direct', [\App\Http\Controllers\Api\LeadCommunicationController::class, 'whatsappDirect'])
        ->name('api.leads.whatsapp-direct')
        ->middleware(['prevent.cache', 'throttle:20,1']);

    Route::prefix('integrations/whatsapp-web')->group(function () {
        Route::post('/lookup', [WhatsAppWebExtensionController::class, 'lookup']);
        Route::post('/process', [WhatsAppWebExtensionController::class, 'process']);
    });

    Route::prefix('integrations/facebook-lead-center')->group(function () {
        Route::post('/compare', [FacebookLeadCenterExtensionController::class, 'compare']);
        Route::post('/audit/store', [FacebookLeadCenterExtensionController::class, 'store']);
        Route::post('/import-selected', [FacebookLeadCenterExtensionController::class, 'importSelected']);
    });
    Route::post('/facebook-leads/import', [FacebookLeadCenterExtensionController::class, 'importCaptured']);

    Route::prefix('attendance')->group(function () {
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::post('/punch-in', [AttendanceController::class, 'punchIn']);
        Route::post('/punch-out', [AttendanceController::class, 'punchOut']);
        Route::post('/outside-punch-requests', [AttendanceOutsidePunchRequestController::class, 'store']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/month-summary', [AttendanceController::class, 'monthSummary']);
        Route::get('/leave-types', [AttendanceLeaveController::class, 'types']);
        Route::get('/leave-balances', [AttendanceLeaveController::class, 'balances']);
        Route::get('/leaves', [AttendanceLeaveController::class, 'index']);
        Route::post('/leaves', [AttendanceLeaveController::class, 'store']);
        Route::get('/regularizations', [AttendanceRegularizationController::class, 'index']);
        Route::post('/regularizations', [AttendanceRegularizationController::class, 'store']);
        Route::get('/overtimes', [AttendanceOvertimeController::class, 'index']);
    });

    Route::get('/payslips/me', [UserPayslipController::class, 'index']);
    Route::get('/payslips/{payslip}/download', [UserPayslipController::class, 'download']);

    Route::middleware('role:admin')->prefix('admin/attendance')->group(function () {
        Route::get('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'index']);
        Route::post('/offices', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'store']);
        Route::put('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'update']);
        Route::delete('/offices/{office}', [\App\Http\Controllers\Admin\AttendanceOfficeController::class, 'destroy']);
        Route::get('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'index']);
        Route::post('/policies', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'store']);
        Route::put('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'update']);
        Route::delete('/policies/{policy}', [\App\Http\Controllers\Admin\AttendancePolicyController::class, 'destroy']);
        Route::get('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'index']);
        Route::post('/user-mappings', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'store']);
        Route::put('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'update']);
        Route::delete('/user-mappings/{mapping}', [\App\Http\Controllers\Admin\UserAttendanceMappingController::class, 'destroy']);
        Route::get('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'index']);
        Route::post('/leave-types', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'store']);
        Route::put('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'update']);
        Route::delete('/leave-types/{leaveType}', [\App\Http\Controllers\Admin\LeaveTypeController::class, 'destroy']);
        Route::get('/simulator', [\App\Http\Controllers\Admin\AttendancePolicySimulatorController::class, 'index']);
        Route::post('/leaves/{leaveRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveLeave']);
        Route::post('/leaves/{leaveRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectLeave']);
        Route::post('/regularizations/{regularization}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveRegularization']);
        Route::post('/regularizations/{regularization}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectRegularization']);
    });

    Route::middleware('role:admin')->prefix('admin/hr')->group(function () {
        Route::get('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'index']);
        Route::post('/salary-structures', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'store']);
        Route::put('/salary-structures/{salaryStructure}', [\App\Http\Controllers\Admin\Hr\SalaryStructureController::class, 'update']);
        Route::get('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'index']);
        Route::post('/salary-profiles', [\App\Http\Controllers\Admin\Hr\SalaryProfileController::class, 'store']);
        Route::get('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'index']);
        Route::post('/deduction-heads', [\App\Http\Controllers\Admin\Hr\DeductionHeadController::class, 'store']);
        Route::get('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'index']);
        Route::post('/payslip-settings', [\App\Http\Controllers\Admin\Hr\PayslipSettingsController::class, 'update']);
    });

    Route::middleware('role:hr_manager')->prefix('hr/attendance')->group(function () {
        Route::get('/register', [\App\Http\Controllers\Hr\AttendanceRegisterController::class, 'index']);
        Route::get('/problems', [\App\Http\Controllers\Hr\AttendanceRegisterController::class, 'problems']);
        Route::get('/reports', [\App\Http\Controllers\Hr\AttendanceReportController::class, 'index']);
        Route::post('/leaves/{leaveRequest}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveLeave']);
        Route::post('/leaves/{leaveRequest}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectLeave']);
        Route::post('/regularizations/{regularization}/approve', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'approveRegularization']);
        Route::post('/regularizations/{regularization}/reject', [\App\Http\Controllers\Hr\AttendanceApprovalController::class, 'rejectRegularization']);
        Route::get('/overtimes', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'index']);
        Route::post('/overtimes/{overtime}/approve', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'approve']);
        Route::post('/overtimes/{overtime}/reject', [\App\Http\Controllers\Hr\AttendanceOvertimeController::class, 'reject']);
        Route::post('/suspicion/{log}', [\App\Http\Controllers\Hr\AttendanceSuspicionReviewController::class, 'update']);
        Route::get('/fraud-reviews', [\App\Http\Controllers\Hr\FraudReviewController::class, 'index']);
        Route::post('/fraud-reviews/{review}/approve', [\App\Http\Controllers\Hr\FraudReviewController::class, 'approve']);
        Route::post('/fraud-reviews/{review}/reject', [\App\Http\Controllers\Hr\FraudReviewController::class, 'reject']);
    });

    Route::middleware(['role:hr_manager,sales_manager,senior_manager,assistant_sales_manager', 'prevent.cache'])->prefix('hr-manager')->group(function () {
        Route::get('/verifications/pending', function (Request $request) {
            $user = $request->user();
            $routing = app(\App\Services\VerificationRoutingService::class);
            $photoUrls = function ($photos) {
                return collect($photos ?? [])
                    ->filter()
                    ->map(function ($path) {
                        $path = trim(str_replace('\\', '/', (string) $path));

                        if ($path === '') {
                            return null;
                        }

                        if (filter_var($path, FILTER_VALIDATE_URL)) {
                            $urlPath = parse_url($path, PHP_URL_PATH);
                            if (!is_string($urlPath) || !preg_match('#/(?:public/)?storage/#i', $urlPath)) {
                                return $path;
                            }

                            $path = $urlPath;
                        }

                        $path = preg_replace('#^/?(?:public/)?storage/#i', '', $path);
                        $path = preg_replace('#^/?public/#i', '', (string) $path);
                        $path = preg_replace('#^storage/#i', '', (string) $path);
                        $path = ltrim((string) $path, '/');

                        return route('storage.proxy', ['path' => $path]);
                    })
                    ->filter()
                    ->values()
                    ->all();
            };
            $meetingPayload = function ($meeting, bool $isReady = true) use ($photoUrls) {
                $proofPhotos = $photoUrls($meeting->completion_proof_photos ?? $meeting->photos ?? []);

                return [
                    'type' => 'meeting',
                    'id' => $meeting->id,
                    'customer_name' => $meeting->customer_name,
                    'phone' => $meeting->phone,
                    'completed_at' => optional($meeting->completed_at)->toIso8601String(),
                    'scheduled_at' => optional($meeting->scheduled_at)->toIso8601String(),
                    'date_of_visit' => optional($meeting->date_of_visit)->toDateString(),
                    'status' => $meeting->status,
                    'verification_status' => $meeting->verification_status ?: 'pending',
                    'is_ready' => $isReady,
                    'ready_note' => $isReady ? null : 'Complete/proof upload ke baad verification allow hoga.',
                    'project' => $meeting->project,
                    'budget_range' => $meeting->budget_range,
                    'property_type' => $meeting->property_type,
                    'notes' => $meeting->meeting_notes,
                    'proof_photos' => $proofPhotos,
                    'lead' => $meeting->lead ? ['id' => $meeting->lead->id, 'name' => $meeting->lead->name, 'phone' => $meeting->lead->phone] : null,
                    'prospect' => $meeting->prospect ? ['id' => $meeting->prospect->id, 'customer_name' => $meeting->prospect->customer_name] : null,
                    'creator' => $meeting->creator ? ['id' => $meeting->creator->id, 'name' => $meeting->creator->name] : null,
                    'assignedTo' => $meeting->assignedTo ? ['id' => $meeting->assignedTo->id, 'name' => $meeting->assignedTo->name] : null,
                ];
            };
            $visitPayload = function ($visit, bool $isReady = true) use ($photoUrls) {
                $proofPhotos = collect($visit->completion_proof_photos ?? $visit->photos ?? [])
                    ->merge($visit->closer_request_proof_photos ?? [])
                    ->pipe($photoUrls);

                return [
                    'type' => 'site_visit',
                    'id' => $visit->id,
                    'customer_name' => $visit->customer_name,
                    'phone' => $visit->phone,
                    'completed_at' => optional($visit->completed_at)->toIso8601String(),
                    'scheduled_at' => optional($visit->scheduled_at)->toIso8601String(),
                    'date_of_visit' => optional($visit->date_of_visit)->toDateString(),
                    'status' => $visit->status,
                    'verification_status' => $visit->verification_status ?: 'pending',
                    'is_ready' => $isReady,
                    'ready_note' => $isReady ? null : 'Complete/proof upload ke baad verification allow hoga.',
                    'project' => $visit->project,
                    'property_name' => $visit->property_name,
                    'property_address' => $visit->property_address,
                    'budget_range' => $visit->budget_range,
                    'property_type' => $visit->property_type,
                    'notes' => $visit->visit_notes,
                    'proof_photos' => $proofPhotos,
                    'lead' => $visit->lead ? ['id' => $visit->lead->id, 'name' => $visit->lead->name, 'phone' => $visit->lead->phone] : null,
                    'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
                    'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                ];
            };

            $meetings = \App\Models\Meeting::query()
                ->where('status', 'completed')
                ->where(function ($query) {
                    $query->where('verification_status', 'pending')->orWhereNull('verification_status');
                })
                ->with(['lead:id,name,phone', 'prospect:id,customer_name', 'creator:id,name,manager_id', 'assignedTo:id,name'])
                ->latest('completed_at')
                ->get()
                ->filter(fn ($meeting) => $routing->canVerify($user, $meeting, \App\Services\VerificationRoutingService::WORKFLOW_MEETING))
                ->map(fn ($meeting) => $meetingPayload($meeting, true));

            $siteVisits = \App\Models\SiteVisit::query()
                ->where('status', 'completed')
                ->where(function ($query) {
                    $query->where('verification_status', 'pending')->orWhereNull('verification_status');
                })
                ->where(function ($query) {
                    $query->whereNull('closer_status')->orWhere('closer_status', '!=', 'pending');
                })
                ->with(['lead:id,name,phone', 'creator:id,name,manager_id', 'assignedTo:id,name'])
                ->latest('completed_at')
                ->get()
                ->filter(fn ($visit) => $routing->canVerify($user, $visit, \App\Services\VerificationRoutingService::WORKFLOW_SITE_VISIT))
                ->map(fn ($visit) => $visitPayload($visit, true));

            $notReadyMeetings = \App\Models\Meeting::query()
                ->where(function ($query) {
                    $query->whereNull('status')->orWhereNotIn('status', ['completed', 'cancelled']);
                })
                ->where(function ($query) {
                    $query->where('verification_status', 'pending')->orWhereNull('verification_status');
                })
                ->with(['lead:id,name,phone', 'prospect:id,customer_name', 'creator:id,name,manager_id', 'assignedTo:id,name'])
                ->latest('scheduled_at')
                ->limit(100)
                ->get()
                ->filter(fn ($meeting) => $routing->canVerify($user, $meeting, \App\Services\VerificationRoutingService::WORKFLOW_MEETING))
                ->map(fn ($meeting) => $meetingPayload($meeting, false));

            $notReadyVisits = \App\Models\SiteVisit::query()
                ->where(function ($query) {
                    $query->whereNull('status')->orWhereNotIn('status', ['completed', 'cancelled']);
                })
                ->where(function ($query) {
                    $query->where('verification_status', 'pending')->orWhereNull('verification_status');
                })
                ->where(function ($query) {
                    $query->whereNull('closer_status')->orWhere('closer_status', '!=', 'pending');
                })
                ->with(['lead:id,name,phone', 'creator:id,name,manager_id', 'assignedTo:id,name'])
                ->latest('scheduled_at')
                ->limit(100)
                ->get()
                ->filter(fn ($visit) => $routing->canVerify($user, $visit, \App\Services\VerificationRoutingService::WORKFLOW_SITE_VISIT))
                ->map(fn ($visit) => $visitPayload($visit, false));

            return response()->json([
                'data' => $meetings->concat($siteVisits)->sortByDesc('completed_at')->values()->all(),
                'not_ready' => $notReadyMeetings->concat($notReadyVisits)->sortByDesc(fn ($item) => $item['scheduled_at'] ?? $item['date_of_visit'] ?? '')->values()->all(),
            ]);
        });

        Route::get('/verifications/verified', [\App\Http\Controllers\Hr\VerificationController::class, 'verified']);

        Route::post('/meetings/{meeting}/verify', [\App\Http\Controllers\Api\MeetingController::class, 'verify']);
        Route::post('/meetings/{meeting}/reject', [\App\Http\Controllers\Api\MeetingController::class, 'reject']);
        Route::post('/site-visits/{siteVisit}/verify', [\App\Http\Controllers\Api\SiteVisitController::class, 'verify']);
        Route::post('/site-visits/{siteVisit}/reject', [\App\Http\Controllers\Api\SiteVisitController::class, 'reject']);
    });

    Route::middleware('role:finance_manager')->prefix('finance/payroll')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\Finance\PayrollController::class, 'summary']);
        Route::post('/freeze', [\App\Http\Controllers\Finance\PayrollController::class, 'freeze']);
        Route::post('/freezes/{freeze}/release', [\App\Http\Controllers\Finance\PayrollController::class, 'release']);
        Route::post('/manual-adjustments', [\App\Http\Controllers\Finance\PayslipController::class, 'storeAdjustment']);
        Route::post('/generate-payslips', [\App\Http\Controllers\Finance\PayslipController::class, 'generate']);
        Route::get('/payslips', [\App\Http\Controllers\Finance\PayslipController::class, 'index']);
    });

    Route::middleware('role:admin,hr_manager,finance_manager')->group(function () {
        Route::get('/reports/attendance/export', [AttendanceReportExportController::class, 'attendance']);
        Route::get('/reports/payroll/export', [AttendanceReportExportController::class, 'payroll']);
        Route::get('/reports/suspicious/export', [AttendanceReportExportController::class, 'suspicious']);
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Targets
    Route::get('/targets/my-targets', [TargetController::class, 'myTargets']);
    Route::get('/targets/team-progress', [TargetController::class, 'teamProgress'])->middleware('role:sales_manager');
    Route::get('/targets/overview', [TargetController::class, 'overview'])->middleware('role:admin,crm');

    Route::middleware('prevent.cache')->group(function () {
        // Leads
        Route::get('/leads', [LeadController::class, 'index'])->middleware('profile.heavy')->name('api.leads.index');
        Route::apiResource('leads', LeadController::class)->except(['index'])->names(['store' => 'api.leads.store', 'show' => 'api.leads.show', 'update' => 'api.leads.update', 'destroy' => 'api.leads.destroy']);
        Route::post('/leads/bulk-assign', [LeadController::class, 'bulkAssign']);
        Route::post('/leads/transfer-all-from-user', [LeadController::class, 'transferAllFromUser']);
        Route::post('/leads/{lead}/assign', [LeadController::class, 'assign']);
        Route::post('/leads/{lead}/mark-closer-draft', [LeadController::class, 'markCloserDraft'])->middleware('role:admin,crm,sales_head,sales_manager,senior_manager,assistant_sales_manager,sales_executive');
        Route::post('/leads/{lead}/old-tasks/complete', [LeadController::class, 'completeOldTask'])->middleware('role:admin,crm,sales_head,sales_manager,senior_manager,assistant_sales_manager,sales_executive,telecaller');
        Route::post('/leads/{lead}/old-tasks/transfer', [LeadController::class, 'transferOldTask'])->middleware('role:admin,crm');
        Route::delete('/leads/{lead}/old-tasks', [LeadController::class, 'deleteOldTask'])->middleware('role:admin,crm');
        Route::post('/leads/{lead}/old-tasks/delete', [LeadController::class, 'deleteOldTask'])->middleware('role:admin,crm');
        Route::get('/leads/{lead}/requirement-form', [\App\Http\Controllers\Api\SalesManagerController::class, 'getLeadRequirementForm']);
        Route::post('/leads/{lead}/update-requirements', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateLeadRequirements']);

        // Site Visits
        Route::apiResource('site-visits', SiteVisitController::class)->names('api.site-visits');

        // Follow-ups
        Route::post('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('api.follow-ups.complete');
        Route::apiResource('follow-ups', FollowUpController::class)->names('api.follow-ups');
    });

    // Interested Project Names
    Route::get('/interested-project-names', [InterestedProjectNameController::class, 'index']);

    // Telecallers list
    Route::get('/telecallers', [TelecallerController::class, 'getTelecallers']);

    // Admin/CRM/HR announcement management. Kept outside the telecaller role group so HR Manager
    // can manage announcements without getting unrelated telecaller API access.
    Route::prefix('telecaller')->middleware(['role:admin,crm,hr_manager', 'prevent.cache'])->group(function () {
        Route::post('/broadcast/send', [\App\Http\Controllers\Api\BroadcastController::class, 'sendBroadcast']);
        Route::get('/broadcast/manage', [\App\Http\Controllers\Api\BroadcastController::class, 'indexManage']);
        Route::get('/broadcast/audience-options', [\App\Http\Controllers\Api\BroadcastController::class, 'audienceOptions']);
    });

    // Users (Admin only)
    Route::apiResource('users', UserController::class)->middleware('permission:manage_users')->names([
        'index' => 'api.users.index',
        'show' => 'api.users.show',
        'store' => 'api.users.store',
        'update' => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);

    // Admin Impersonation
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:manage_users');
    Route::post('/impersonate/stop', [UserController::class, 'stopImpersonation'])->middleware('permission:manage_users');

    // Telecaller / Sales Executive routes (both roles use same API)
    Route::prefix('telecaller')->middleware(['role:telecaller,sales_executive,admin,crm,sales_manager,senior_manager,assistant_sales_manager,sales_head', 'prevent.cache'])->group(function () {
        // Auth routes
        Route::get('/whoami', [TelecallerController::class, 'whoami']);
        Route::post('/logout', [TelecallerController::class, 'logout']);
        
        // Dashboard & Stats
        Route::get('/stats', [TelecallerController::class, 'getStats']);
        Route::get('/top-performers', [TelecallerController::class, 'getTopPerformers']);
        
        // Dashboard API endpoints
        Route::get('/dashboard', [\App\Http\Controllers\Api\TelecallerDashboardController::class, 'index']);
        Route::get('/dashboard/stats', [\App\Http\Controllers\Api\TelecallerDashboardController::class, 'stats']);
        Route::get('/dashboard/urgent-tasks', [\App\Http\Controllers\Api\TelecallerDashboardController::class, 'urgentTasks']);
        Route::get('/dashboard/schedule', [\App\Http\Controllers\Api\TelecallerDashboardController::class, 'schedule']);
        Route::get('/dashboard/performance', [\App\Http\Controllers\Api\TelecallerDashboardController::class, 'performance']);
        Route::get('/leads-pending-response', [LeadsPendingResponseController::class, 'forCurrentUser']);
        
        // Leads & Calls
        Route::get('/leads', [TelecallerController::class, 'getLeads']);
        Route::get('/calling-queue', [TelecallerController::class, 'getCallingQueue']);
        Route::get('/completed-calls', [TelecallerController::class, 'getCompletedCalls']);
        Route::get('/follow-up-calls', [TelecallerController::class, 'getFollowUpCalls']);
        Route::get('/cnp-calls', [TelecallerController::class, 'getCnpCalls']);
        Route::get('/prospects', [TelecallerController::class, 'getProspects']);
        Route::get('/prospects/list', [TelecallerController::class, 'getProspects']); // Alias for verification pending
        
        // Tasks
        Route::get('/tasks', [TelecallerController::class, 'getTasks']);
        Route::get('/tasks/stats', [TelecallerController::class, 'getTaskStats']);
        Route::post('/tasks/schedule-call', [\App\Http\Controllers\Api\SalesManagerController::class, 'scheduleCallTask']);
        Route::post('/tasks/{task}/initiate-call', [TelecallerController::class, 'initiateCall']);
        Route::post('/tasks/{task}/call-outcome', [TelecallerController::class, 'callOutcome']);
        Route::post('/tasks/{taskId}/outcome', [TelecallerController::class, 'recordOutcome']);
        Route::get('/tasks/{task}/lead-form', [TelecallerController::class, 'getLeadFormForModal']);
        Route::post('/tasks/{taskId}/submit-for-verification', [TelecallerController::class, 'submitLeadFormForVerification']);
        
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/notifications/unread', [\App\Http\Controllers\Api\NotificationController::class, 'getUnread']);
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/{notification}/click', [\App\Http\Controllers\Api\NotificationController::class, 'markAsClicked']);
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
        
        // Broadcasts
        Route::get('/broadcast/unread', [\App\Http\Controllers\Api\BroadcastController::class, 'getUnreadBroadcasts']);
        Route::post('/broadcast/{broadcast}/read', [\App\Http\Controllers\Api\BroadcastController::class, 'markAsRead']);
        
        // Actions
        Route::post('/update-call-status', [TelecallerController::class, 'updateCallStatus']);
        Route::post('/mark-cnp', [TelecallerController::class, 'markCnp']);
        Route::post('/mark-broker', [TelecallerController::class, 'markBroker']);
        Route::post('/schedule-follow-up', [TelecallerController::class, 'scheduleFollowUp']);
        Route::post('/create-prospect', [TelecallerController::class, 'createProspect']);
        Route::post('/prospects/create', [TelecallerController::class, 'createProspectFromTask']);
        Route::post('/recall-assignment', [TelecallerController::class, 'recallAssignment']);
        Route::post('/blacklist-number', [TelecallerController::class, 'blacklistNumber']);
        
        // Supporting
        Route::get('/users', [TelecallerController::class, 'getUsers']);
        
        // Profile
        Route::get('/profile', [TelecallerController::class, 'getProfile']);
        Route::put('/profile', [TelecallerController::class, 'updateProfile']);
        Route::post('/profile/picture', [TelecallerController::class, 'uploadProfilePicture']);
        Route::post('/profile/password', [TelecallerController::class, 'changePassword']);
        Route::post('/profile/availability', [TelecallerController::class, 'updateAvailability']);
        
        // Call Tracking (Legacy - kept for backward compatibility)
        Route::post('/call-logs', [TelecallerController::class, 'saveCallLog']);
        Route::get('/call-logs', [TelecallerController::class, 'getCallLogs']);
        Route::get('/call-statistics', [TelecallerController::class, 'getCallStatistics']);
        
        // Site Visit Incentives (for Telecallers)
        Route::get('/site-visits/eligible-for-incentive', [TelecallerController::class, 'getEligibleSiteVisitsForIncentive']);
        Route::post('/site-visits/{siteVisit}/request-incentive', [TelecallerController::class, 'requestSiteVisitIncentive']);
        Route::get('/incentives', [IncentiveController::class, 'index']);
        Route::get('/incentives/{incentive}', [IncentiveController::class, 'show']);
    });

    // Enhanced Call Logs API (for all roles)
    Route::prefix('call-logs')->name('api.call-logs.')->middleware('prevent.cache')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CallLogController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CallLogController::class, 'store']);
        Route::post('/bulk-sync', [\App\Http\Controllers\Api\CallLogController::class, 'bulkSync']);
        Route::post('/{id}/recording', [\App\Http\Controllers\Api\CallLogController::class, 'uploadRecording']);
        Route::get('/statistics', [\App\Http\Controllers\Api\CallLogController::class, 'getStatistics']);
        Route::get('/team-statistics', [\App\Http\Controllers\Api\CallLogController::class, 'getTeamStatistics']);
        Route::get('/dashboard-stats', [\App\Http\Controllers\Api\CallLogController::class, 'getDashboardStats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\CallLogController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CallLogController::class, 'update']);
    });

    // Sales Manager routes (Admin, CRM, Sales Head, Senior Manager, Manager, Assistant Sales Manager)
    Route::prefix('sales-manager')->middleware(['role:admin,crm,sales_head,sales_manager,senior_manager,assistant_sales_manager', 'prevent.cache'])->group(function () {
        // Profile
        Route::get('/leads-pending-response', [LeadsPendingResponseController::class, 'forCurrentUser']);
        Route::get('/profile', [\App\Http\Controllers\Api\SalesManagerController::class, 'getProfile']);
        Route::put('/profile', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateProfile']);
        Route::post('/profile/picture', [\App\Http\Controllers\Api\SalesManagerController::class, 'uploadProfilePicture']);
        Route::post('/profile/password', [\App\Http\Controllers\Api\SalesManagerController::class, 'changePassword']);
        Route::post('/profile/availability', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateAvailability']);
        Route::get('/dashboard-settings', [\App\Http\Controllers\Api\SalesManagerController::class, 'getDashboardSettings']);
        Route::post('/dashboard-settings', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateDashboardSettings']);
        Route::post('/dashboard/clear-cache', [\App\Http\Controllers\Api\SalesManagerController::class, 'clearDashboardCache']);
        Route::post('/dialer/call', [\App\Http\Controllers\Api\SalesManagerDialerController::class, 'call'])
            ->middleware('throttle:10,1');
        
        // Team management
        Route::get('/team/member/{memberId}', [\App\Http\Controllers\Api\SalesManagerController::class, 'getTeamMemberDetails']);
        Route::get('/team/performance', [\App\Http\Controllers\Api\SalesManagerController::class, 'getTeamPerformance'])->middleware('profile.heavy');
        Route::get('/dashboard/team-overview', [\App\Http\Controllers\Api\SalesManagerController::class, 'getDashboardTeamOverview'])->middleware('profile.heavy');
        Route::get('/dashboard/team-member/{member}/fresh-leads', [\App\Http\Controllers\Api\SalesManagerController::class, 'getDashboardTeamMemberFreshLeads'])->middleware('profile.heavy');
        Route::get('/dashboard/team-member/{member}/workload', [\App\Http\Controllers\Api\SalesManagerController::class, 'getDashboardTeamMemberWorkload'])->middleware('profile.heavy');
        Route::put('/dashboard/team-member/{member}/lead-availability', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateDashboardTeamMemberLeadAvailability']);
        
        // Achievements
        Route::get('/achievements', [\App\Http\Controllers\Api\SalesManagerController::class, 'getAchievements']);
        
        // Prospects
        Route::get('/prospects', [\App\Http\Controllers\Api\SalesManagerController::class, 'getProspects'])->middleware('profile.heavy');
        Route::post('/prospects', [\App\Http\Controllers\Api\SalesManagerController::class, 'createProspect']);
        Route::get('/prospects/pending', [\App\Http\Controllers\Api\Crm\VerificationController::class, 'getPending']);
        Route::post('/prospects/{prospect}/verify', [\App\Http\Controllers\Api\Crm\VerificationController::class, 'verify']);
        Route::post('/prospects/{prospect}/reject', [\App\Http\Controllers\Api\Crm\VerificationController::class, 'reject']);

        // Favorite leads
        Route::get('/favorite-leads', [\App\Http\Controllers\Api\SalesManagerController::class, 'getFavoriteLeads']);
        Route::post('/leads/{lead}/favorite', [\App\Http\Controllers\Api\SalesManagerController::class, 'addFavoriteLead']);
        Route::delete('/leads/{lead}/favorite', [\App\Http\Controllers\Api\SalesManagerController::class, 'removeFavoriteLead']);
        Route::get('/leads/{lead}/open-task', [\App\Http\Controllers\Api\SalesManagerController::class, 'getLeadOpenTask']);
        
        // Tasks
        Route::get('/tasks', [\App\Http\Controllers\Api\SalesManagerController::class, 'getTasks'])->middleware('profile.heavy');
        Route::get('/tasks/{task}', [\App\Http\Controllers\Api\SalesManagerController::class, 'getTask']);
        Route::post('/tasks/schedule-call', [\App\Http\Controllers\Api\SalesManagerController::class, 'scheduleCallTask']);
        Route::post('/tasks/{task}/update-lead', [\App\Http\Controllers\Api\SalesManagerController::class, 'updateLeadFromTask']);
        Route::get('/tasks/{task}/lead-requirement-form', [\App\Http\Controllers\Api\SalesManagerController::class, 'getLeadRequirementFormForTask']);
        Route::post('/tasks/{task}/outcome', [\App\Http\Controllers\Api\SalesManagerController::class, 'submitTaskOutcome']);
        Route::post('/tasks/{task}/verify', [\App\Http\Controllers\Api\SalesManagerController::class, 'verifyProspectFromTask']);
        Route::post('/tasks/{task}/reject', [\App\Http\Controllers\Api\SalesManagerController::class, 'rejectProspectFromTask']);
        Route::post('/tasks/{task}/cnp', [\App\Http\Controllers\Api\SalesManagerController::class, 'markAsCNP']);
        Route::post('/tasks/{task}/complete', [\App\Http\Controllers\Api\SalesManagerController::class, 'completeTask']);
        Route::post('/tasks/{task}/workflow-action', [\App\Http\Controllers\Api\SalesManagerController::class, 'executeTaskWorkflowAction']);
        Route::post('/tasks/remove-all-overdue', [\App\Http\Controllers\Api\SalesManagerController::class, 'removeAllOverdueTasks']);
        
        // Meetings
        Route::get('/meetings', [\App\Http\Controllers\Api\MeetingController::class, 'index'])->middleware('profile.heavy');
        Route::get('/meetings/lead-options', [\App\Http\Controllers\Api\MeetingController::class, 'leadOptions']);
        Route::post('/meetings/quick', [\App\Http\Controllers\Api\MeetingController::class, 'quickStore']);
        Route::post('/meetings/quick-schedule-with-reminder', [\App\Http\Controllers\Api\MeetingController::class, 'quickScheduleWithReminder']);
        Route::post('/meetings', [\App\Http\Controllers\Api\MeetingController::class, 'store']);
        Route::get('/meetings/{meeting}', [\App\Http\Controllers\Api\MeetingController::class, 'show']);
        Route::put('/meetings/{meeting}', [\App\Http\Controllers\Api\MeetingController::class, 'update']);
        Route::post('/meetings/{meeting}/complete', [\App\Http\Controllers\Api\MeetingController::class, 'complete']);
        Route::post('/meetings/{meeting}/complete-pre-call', [\App\Http\Controllers\Api\MeetingController::class, 'completePreCall']);
        Route::post('/meetings/{meeting}/cancel', [\App\Http\Controllers\Api\MeetingController::class, 'cancelMeeting']);
        Route::post('/meetings/{meeting}/reschedule', [\App\Http\Controllers\Api\MeetingController::class, 'reschedule']);
        Route::post('/meetings/{meeting}/convert-to-site-visit', [\App\Http\Controllers\Api\MeetingController::class, 'convertToSiteVisit']);
        Route::post('/meetings/{meeting}/mark-dead', [\App\Http\Controllers\Api\MeetingController::class, 'markDead']);
        Route::post('/meetings/{meeting}/verify', [\App\Http\Controllers\Api\MeetingController::class, 'verify']);
        Route::post('/meetings/{meeting}/reject', [\App\Http\Controllers\Api\MeetingController::class, 'reject']);
        Route::get('/leads/{leadId}/meeting-history', [\App\Http\Controllers\Api\MeetingController::class, 'getMeetingHistory']);
        
        // Site Visits
        Route::get('/site-visits', [\App\Http\Controllers\Api\SiteVisitController::class, 'index'])->middleware('profile.heavy');
        Route::get('/site-visits/closer-pipeline', [\App\Http\Controllers\Api\SiteVisitController::class, 'closerPipeline']);
        Route::post('/site-visits', [\App\Http\Controllers\Api\SiteVisitController::class, 'store']);
        Route::post('/site-visits/{siteVisit}/complete', [\App\Http\Controllers\Api\SiteVisitController::class, 'complete']);
        Route::post('/site-visits/{siteVisit}/reschedule', [\App\Http\Controllers\Api\SiteVisitController::class, 'reschedule']);
        Route::post('/site-visits/{siteVisit}/resubmit', [\App\Http\Controllers\Api\SiteVisitController::class, 'resubmit']);
        Route::post('/site-visits/{siteVisit}/request-close', [\App\Http\Controllers\Api\SiteVisitController::class, 'requestClose']);
        Route::post('/site-visits/{siteVisit}/convert-to-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'convertToCloser']);
        Route::post('/site-visits/{siteVisit}/request-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'requestCloser']);
        Route::post('/site-visits/{siteVisit}/move-to-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'moveToCloser']);
        Route::post('/site-visits/{siteVisit}/kyc/draft', [\App\Http\Controllers\Api\SiteVisitController::class, 'saveCloserKycDraft']);
        Route::post('/site-visits/{siteVisit}/kyc/submit', [\App\Http\Controllers\Api\SiteVisitController::class, 'submitCloserKyc']);
        Route::post('/site-visits/{siteVisit}/closer/resubmit', [\App\Http\Controllers\Api\SiteVisitController::class, 'resubmitCloser']);
        Route::post('/site-visits/{siteVisit}/submit-kyc', [\App\Http\Controllers\Api\SiteVisitController::class, 'submitKyc']);
        Route::post('/site-visits/{siteVisit}/mark-dead', [\App\Http\Controllers\Api\SiteVisitController::class, 'markDead']);
        Route::post('/site-visits/{siteVisit}/verify', [\App\Http\Controllers\Api\SiteVisitController::class, 'verify']);
        Route::post('/site-visits/{siteVisit}/reject', [\App\Http\Controllers\Api\SiteVisitController::class, 'reject']);
        Route::post('/site-visits/{siteVisit}/verify-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'verifyCloser']);
        Route::post('/site-visits/{siteVisit}/reject-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'rejectCloser']);
        Route::post('/site-visits/{siteVisit}/send-back-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'sendBackCloser']);
        Route::post('/site-visits/{siteVisit}/verify-closing', [\App\Http\Controllers\Api\SiteVisitController::class, 'verifyClosing']);
        Route::post('/site-visits/{siteVisit}/reject-closing', [\App\Http\Controllers\Api\SiteVisitController::class, 'rejectClosing']);
        
        // Incentives (for Managers/Sales Executives - closer incentives)
        Route::get('/incentives', [IncentiveController::class, 'index']);
        Route::get('/incentives/{incentive}', [IncentiveController::class, 'show']);
        // Request incentive after closing is verified
        Route::post('/site-visits/{siteVisit}/request-incentive', [IncentiveController::class, 'requestIncentive']);
    });

    // CRM routes
    Route::prefix('crm')->group(function () {
        // Authentication
        Route::post('/login', [CrmAuthController::class, 'login'])->middleware('throttle:auth-api');
        
        // Dashboard API: all roles except CRM Admin and Sale Head (sbka dikhega unko chhod kar)
        Route::middleware(['auth:sanctum', 'crm_dashboard_access', 'prevent.cache'])->group(function () {
            Route::get('/whoami', [CrmAuthController::class, 'whoami']);
            Route::get('/dashboard/stats', [CrmDashboardController::class, 'getStats']);
            Route::get('/dashboard/filter-roles', [CrmDashboardController::class, 'getPerformanceFilterRoles']);
            Route::get('/dashboard/telecaller-stats', [CrmDashboardController::class, 'getTelecallerStats']);
            Route::get('/dashboard/lead-operations-summary', [CrmDashboardController::class, 'getLeadOperationsSummary']);
            Route::get('/dashboard/previous-over-pod', [CrmDashboardController::class, 'getPreviousOverPod']);
            Route::get('/dashboard/leads-pending-response', [CrmDashboardController::class, 'getLeadsPendingResponse']);
            Route::get('/dashboard/new-leads-not-completed', [CrmDashboardController::class, 'getNewLeadsNotCompleted']);
            Route::get('/dashboard/average-response-time', [CrmDashboardController::class, 'getAverageResponseTime']);
            Route::get('/dashboard/lead-allocation-overview', [CrmDashboardController::class, 'getLeadAllocationOverview']);
            Route::get('/dashboard/source-distribution', [CrmDashboardController::class, 'getSourceDistribution']);
            Route::get('/dashboard/recent-leads', [CrmDashboardController::class, 'getRecentLeads']);
            Route::get('/dashboard/fresh-lead-cnp-summary', [CrmDashboardController::class, 'getFreshLeadCnpSummary']);
            Route::get('/dashboard/daily-prospects', [CrmDashboardController::class, 'getDailyProspects']);
            Route::post('/dashboard/clear-cache', [CrmDashboardController::class, 'clearDashboardCache']);
        });
        
        Route::middleware(['auth:sanctum', 'crm'])->group(function () {
            // Auth routes (logout etc. – CRM/Admin only for other CRM features)
            Route::post('/logout', [CrmAuthController::class, 'logout']);
            
            // Leads
            Route::post('/add-lead', [CrmLeadController::class, 'addLead']);
            Route::get('/imported-leads', [CrmLeadController::class, 'getImportedLeads']);
            Route::post('/assign-leads', [CrmLeadController::class, 'assignLeads']);
            
            // Users
            Route::get('/users', [CrmUserController::class, 'index']);
            Route::get('/roles', [CrmUserController::class, 'getRoles']);
            Route::post('/users', [CrmUserController::class, 'store']);
            Route::put('/users/{id}', [CrmUserController::class, 'update']);
            Route::delete('/users/{id}', [CrmUserController::class, 'destroy']);
            
            // Transfer
            Route::post('/transfer-leads', [CrmTransferController::class, 'transfer']);
            
            // Blacklist
            Route::get('/blacklist', [CrmBlacklistController::class, 'index']);
            Route::post('/blacklist', [CrmBlacklistController::class, 'store']);
            Route::delete('/blacklist/{id}', [CrmBlacklistController::class, 'destroy']);
            
            // Targets
            Route::get('/targets', [CrmTargetController::class, 'index']);
            Route::post('/targets', [CrmTargetController::class, 'store']);
            Route::put('/targets/{id}', [CrmTargetController::class, 'update']);
            
            // Verifications
            Route::get('/pending-verifications', [CrmVerificationController::class, 'getPending']);
            Route::get('/verifications/pending-prospects', [CrmVerificationController::class, 'getPending']);
            Route::post('/verify-prospect/{prospect}', [CrmVerificationController::class, 'verify']);
            Route::post('/reject-prospect/{prospect}', [CrmVerificationController::class, 'reject']);
            
            // Meeting & Site Visit Verifications
            Route::post('/meetings/{meeting}/verify', [\App\Http\Controllers\Api\MeetingController::class, 'verify']);
            Route::post('/meetings/{meeting}/reject', [\App\Http\Controllers\Api\MeetingController::class, 'reject']);
            Route::post('/site-visits/{siteVisit}/verify', [\App\Http\Controllers\Api\SiteVisitController::class, 'verify']);
            Route::post('/site-visits/{siteVisit}/reject', [\App\Http\Controllers\Api\SiteVisitController::class, 'reject']);
            Route::post('/site-visits/{siteVisit}/verify-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'verifyCloser']);
            Route::post('/site-visits/{siteVisit}/reject-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'rejectCloser']);
            Route::post('/site-visits/{siteVisit}/send-back-closer', [\App\Http\Controllers\Api\SiteVisitController::class, 'sendBackCloser']);
            Route::post('/site-visits/{siteVisit}/transfer-to-finance', [\App\Http\Controllers\Api\SiteVisitController::class, 'transferCloserToFinance']);
            
            // Closing Verification (CRM) - New flow
            Route::post('/site-visits/{siteVisit}/verify-closing', [\App\Http\Controllers\Api\SiteVisitController::class, 'verifyClosing']);
            Route::post('/site-visits/{siteVisit}/reject-closing', [\App\Http\Controllers\Api\SiteVisitController::class, 'rejectClosing']);
            
            // Incentive Verifications (CRM) - Deprecated, kept for backward compatibility
            Route::post('/incentives/{incentive}/verify', [IncentiveController::class, 'verifyByCrm']);
            Route::post('/incentives/{incentive}/reject', [IncentiveController::class, 'rejectByCrm']);
        });
    });

    // Sales Head routes (for incentive verification - DEPRECATED)
    Route::prefix('sales-head')->middleware(['auth:sanctum', 'role:sales_head'])->group(function () {
        // Incentive Verifications (Sales Head) - Deprecated, kept for backward compatibility
        Route::post('/incentives/{incentive}/verify', [IncentiveController::class, 'verifyBySalesHead']);
        Route::post('/incentives/{incentive}/reject', [IncentiveController::class, 'rejectBySalesHead']);
    });

    // Finance Manager routes (for incentive verification)
    Route::prefix('finance-manager')->middleware(['auth:sanctum', 'role:finance_manager'])->group(function () {
        // Incentive Verifications (Finance Manager)
        Route::get('/closer-transfers', [\App\Http\Controllers\Api\SiteVisitController::class, 'financeTransferQueue']);
        Route::post('/site-visits/{siteVisit}/approve-transfer', [\App\Http\Controllers\Api\SiteVisitController::class, 'approveFinanceTransfer']);
        Route::get('/incentives', [IncentiveController::class, 'index']);
        Route::get('/incentives/{incentive}', [IncentiveController::class, 'show']);
        Route::post('/incentives/{incentive}/verify', [IncentiveController::class, 'verifyByFinanceManager']);
        Route::post('/incentives/{incentive}/reject', [IncentiveController::class, 'rejectByFinanceManager']);
    });

    // Flow Testing routes (Admin and CRM)
    Route::prefix('admin/flow-test')->middleware(['auth:sanctum', 'role:admin,crm'])->group(function () {
        Route::get('/stages', [\App\Http\Controllers\Admin\FlowTestController::class, 'getFlowStages']);
        Route::post('/login-as/{userId}', [\App\Http\Controllers\Admin\FlowTestController::class, 'loginAsUser']);
        Route::post('/restore-original-user', [\App\Http\Controllers\Admin\FlowTestController::class, 'restoreOriginalUser']);
        Route::post('/stages/{stageId}/test', [\App\Http\Controllers\Admin\FlowTestController::class, 'testStage']);
        Route::post('/stages/{stageId}/validate', [\App\Http\Controllers\Admin\FlowTestController::class, 'validateStage']);
        Route::get('/stages/{stageId}/data', [\App\Http\Controllers\Admin\FlowTestController::class, 'getStageData']);
        Route::post('/stages/{stageId}/fix', [\App\Http\Controllers\Admin\FlowTestController::class, 'fixErrors']);
        Route::get('/users-by-role', [\App\Http\Controllers\Admin\FlowTestController::class, 'getUsersByRole']);
        Route::post('/reset', [\App\Http\Controllers\Admin\FlowTestController::class, 'resetFlow']);
    });

    // Admin routes (for verification)
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,crm,sales_manager,senior_manager,assistant_sales_manager'])->group(function () {
        // Dead Leads/Items
        Route::get('/dead-leads', function (Request $request) {
            $query = \App\Models\Lead::where('is_dead', true)
                ->with(['markedDeadBy', 'creator']);
            
            if ($request->has('dead_at_stage')) {
                $query->where('dead_at_stage', $request->dead_at_stage);
            }
            
            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $leads = $query->latest('marked_dead_at')->paginate($perPage);
            return response()->json($leads);
        });
        
        Route::get('/dead-meetings', function (Request $request) {
            $query = \App\Models\Meeting::where('is_dead', true)
                ->with(['markedDeadBy', 'creator', 'lead']);
            
            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $meetings = $query->latest('marked_dead_at')->paginate($perPage);
            return response()->json($meetings);
        });
        
        Route::get('/dead-site-visits', function (Request $request) {
            $query = \App\Models\SiteVisit::where('is_dead', true)
                ->with(['markedDeadBy', 'creator', 'lead']);
            
            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $visits = $query->latest('marked_dead_at')->paginate($perPage);
            return response()->json($visits);
        });
        
        // Meeting and Site Visit details for CRM/Admin
        Route::get('/meetings/{meeting}', function (Request $request, $meetingId) {
            try {
                $meeting = \App\Models\Meeting::with(['lead', 'prospect', 'creator', 'assignedTo', 'verifiedBy'])->findOrFail($meetingId);
                return response()->json([
                    'data' => $meeting,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Meeting not found',
                    'message' => $e->getMessage(),
                ], 404);
            }
        });

        Route::get('/site-visits/{siteVisit}', function (Request $request, $siteVisitId) {
            try {
                $siteVisit = \App\Models\SiteVisit::with(['lead', 'creator', 'assignedTo'])->findOrFail($siteVisitId);
                return response()->json([
                    'data' => $siteVisit,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Site visit not found',
                    'message' => $e->getMessage(),
                ], 404);
            }
        });

        // Prospect details for CRM/Admin
        Route::get('/prospects/{prospect}', function (Request $request, $prospectId) {
            try {
                $prospect = \App\Models\Prospect::with([
                    'lead', 
                    'createdBy', 
                    'assignedManager', 
                    'telecaller', 
                    'manager', 
                    'verifiedBy',
                    'assignment'
                ])->findOrFail($prospectId);
                return response()->json([
                    'data' => $prospect,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Prospect not found',
                    'message' => $e->getMessage(),
                ], 404);
            }
        });

        Route::get('/verifications/pending', function (Request $request) {
            try {
                $user = $request->user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
                $routing = app(\App\Services\VerificationRoutingService::class);

                // Get all pending meetings - verification_status 'pending' (or null for legacy)
                // Optimize: Eager load relationships and select only needed columns
                $meetings = \App\Models\Meeting::where('status', 'completed')
                    ->where(function ($q) {
                        $q->where('verification_status', 'pending')
                          ->orWhereNull('verification_status');
                    })
                    ->orderBy('completed_at', 'desc')
                    ->with([
                        'lead:id,name,phone',
                        'prospect:id,customer_name',
                        'creator:id,name,manager_id',
                        'assignedTo:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'budget_range', 'property_type',
                        'meeting_notes', 'employee', 'occupation', 'date_of_visit',
                        'project', 'payment_mode', 'tentative_period', 'lead_type',
                        'team_leader', 'lead_id', 'prospect_id', 'created_by', 'assigned_to',
                        'photos', 'completion_proof_photos'
                    ])
                    ->get()
                    ->map(function($meeting) use ($user, $routing) {
                    $canVerify = $routing->canVerify($user, $meeting, \App\Services\VerificationRoutingService::WORKFLOW_MEETING);
                    return [
                        'id' => $meeting->id,
                        'customer_name' => $meeting->customer_name,
                        'phone' => $meeting->phone,
                        'scheduled_at' => $meeting->scheduled_at ? $meeting->scheduled_at->toIso8601String() : null,
                        'completed_at' => $meeting->completed_at ? $meeting->completed_at->toIso8601String() : null,
                        'status' => $meeting->status,
                        'verification_status' => $meeting->verification_status,
                        'budget_range' => $meeting->budget_range,
                        'property_type' => $meeting->property_type,
                        'meeting_notes' => $meeting->meeting_notes,
                        'employee' => $meeting->employee,
                        'occupation' => $meeting->occupation,
                        'date_of_visit' => $meeting->date_of_visit ? $meeting->date_of_visit->toIso8601String() : null,
                        'project' => $meeting->project,
                        'payment_mode' => $meeting->payment_mode,
                        'tentative_period' => $meeting->tentative_period,
                        'lead_type' => $meeting->lead_type,
                        'team_leader' => $meeting->team_leader,
                        'photos' => $meeting->photos ?? [],
                        'completion_proof_photos' => $meeting->completion_proof_photos ?? [],
                        'lead' => $meeting->lead ? ['id' => $meeting->lead->id, 'name' => $meeting->lead->name, 'phone' => $meeting->lead->phone] : null,
                        'prospect' => $meeting->prospect ? ['id' => $meeting->prospect->id, 'customer_name' => $meeting->prospect->customer_name] : null,
                        'creator' => $meeting->creator ? ['id' => $meeting->creator->id, 'name' => $meeting->creator->name] : null,
                        'assignedTo' => $meeting->assignedTo ? ['id' => $meeting->assignedTo->id, 'name' => $meeting->assignedTo->name] : null,
                        'can_verify' => $canVerify,
                    ];
                });
                
                // Get all pending site visits - verification_status 'pending' (or null for legacy)
                // Exclude site visits that are in "closer" flow (they appear under Closer Requests tab)
                // Optimize: Eager load relationships and select only needed columns
                $isScopedVerifier = !$user->isAdmin() && !$user->isCrm() && !$user->isSalesHead();
                $siteVisitStatuses = $isScopedVerifier ? ['completed', 'scheduled'] : ['completed'];

                $siteVisits = \App\Models\SiteVisit::whereIn('status', $siteVisitStatuses)
                    ->where(function ($q) {
                        $q->where('verification_status', 'pending')
                          ->orWhereNull('verification_status');
                    })
                    ->where(function($query) {
                        $query->whereNull('closer_status')
                              ->orWhere('closer_status', '!=', 'pending');
                    })
                    ->orderBy('completed_at', 'desc')
                    ->with([
                        'lead:id,name,phone',
                        'creator:id,name,manager_id',
                        'assignedTo:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'employee', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'property_name', 'property_address',
                        'budget_range', 'visit_notes', 'closer_status', 'project',
                        'property_type', 'lead_type', 'lead_id', 'created_by', 'assigned_to',
                        'photos', 'completion_proof_photos', 'resubmission_count',
                        'resubmitted_at', 'latest_rejected_at', 'rejection_reason'
                    ])
                    ->get()
                    ->map(function($visit) use ($user, $routing) {
                    $canVerify = $routing->canVerify($user, $visit, \App\Services\VerificationRoutingService::WORKFLOW_SITE_VISIT);
                    return [
                        'id' => $visit->id,
                        'customer_name' => $visit->customer_name,
                        'phone' => $visit->phone,
                        'scheduled_at' => $visit->scheduled_at ? $visit->scheduled_at->toIso8601String() : null,
                        'completed_at' => $visit->completed_at ? $visit->completed_at->toIso8601String() : null,
                        'status' => $visit->status,
                        'verification_status' => $visit->verification_status,
                        'property_name' => $visit->property_name,
                        'property_address' => $visit->property_address,
                        'budget_range' => $visit->budget_range,
                        'visit_notes' => $visit->visit_notes,
                        'closer_status' => $visit->closer_status,
                        'project' => $visit->project,
                        'property_type' => $visit->property_type,
                        'lead_type' => $visit->lead_type,
                        'employee' => $visit->employee,
                        'photos' => $visit->photos ?? [],
                        'completion_proof_photos' => $visit->completion_proof_photos ?? [],
                        'resubmission_count' => (int) ($visit->resubmission_count ?? 0),
                        'resubmitted_at' => $visit->resubmitted_at ? $visit->resubmitted_at->toIso8601String() : null,
                        'latest_rejected_at' => $visit->latest_rejected_at ? $visit->latest_rejected_at->toIso8601String() : null,
                        'rejection_reason' => $visit->rejection_reason,
                        'closer_request_proof_photos' => $visit->closer_request_proof_photos ?? [],
                        'lead' => $visit->lead ? ['id' => $visit->lead->id, 'name' => $visit->lead->name, 'phone' => $visit->lead->phone] : null,
                        'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
                        'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                        'can_verify' => $canVerify,
                    ];
                })
                    ->filter(fn ($visit) => !$isScopedVerifier || !empty($visit['can_verify']))
                    ->values();
                
                return response()->json([
                    'meetings' => array_values($meetings->toArray()),
                    'site_visits' => array_values($siteVisits->toArray()),
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading pending verifications: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Failed to load pending verifications',
                    'message' => $e->getMessage(),
                    'meetings' => [],
                    'site_visits' => [],
                ], 500);
            }
        });
        
        Route::get('/verifications/pending-closers', function (Request $request) {
            try {
                $user = $request->user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
                $routing = app(\App\Services\VerificationRoutingService::class);
                $closers = \App\Models\SiteVisit::where('closer_status', 'pending_crm')
                    ->where('verification_status', 'verified')
                    ->with([
                        'lead:id,name,phone',
                        'creator:id,name',
                        'assignedTo:id,name',
                        'approvalAdmin:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'property_name', 'budget_range',
                        'visit_notes', 'closer_status', 'lead_id', 'created_by', 'assigned_to',
                        'photos', 'completion_proof_photos', 'closer_request_proof_photos', 'incentive_amount',
                        'closing_verification_status', 'closer_submitted_at', 'actual_closer_date',
                        'actual_closer_backdate_reason', 'actual_closer_date_approved_at',
                        'actual_closer_date_approved_by', 'is_finance_direct_closer', 'approval_admin_id'
                    ])
                    ->with('incentives')
                    ->get()
                    ->map(function($visit) use ($user, $routing) {
                        $incentive = $visit->incentives()->where('type', 'closer')->first();
                        return [
                            'id' => $visit->id,
                            'customer_name' => $visit->customer_name ?: optional($visit->lead)->name,
                            'phone' => $visit->phone ?: optional($visit->lead)->phone,
                            'scheduled_at' => $visit->scheduled_at ? $visit->scheduled_at->toIso8601String() : null,
                            'completed_at' => $visit->completed_at ? $visit->completed_at->toIso8601String() : null,
                            'status' => $visit->status,
                            'verification_status' => $visit->verification_status,
                            'property_name' => $visit->property_name,
                            'property_address' => $visit->property_address,
                            'budget_range' => $visit->budget_range,
                            'visit_notes' => $visit->visit_notes,
                            'closer_status' => $visit->closer_status,
                            'closing_verification_status' => $visit->closing_verification_status ?? null,
                            'closer_submitted_at' => $visit->closer_submitted_at ? $visit->closer_submitted_at->toIso8601String() : null,
                            'actual_closer_date' => $visit->actual_closer_date ? $visit->actual_closer_date->toDateString() : null,
                            'actual_closer_backdate_reason' => $visit->actual_closer_backdate_reason,
                            'actual_closer_date_approved_at' => $visit->actual_closer_date_approved_at ? $visit->actual_closer_date_approved_at->toIso8601String() : null,
                            'actual_closer_date_approved_by' => $visit->actual_closer_date_approved_by,
                            'incentive_amount' => $visit->incentive_amount ?? ($incentive ? $incentive->amount : 0),
                            'incentive_id' => $incentive ? $incentive->id : null,
                            'photos' => $visit->photos ?? [],
                            'completion_proof_photos' => $visit->completion_proof_photos ?? [],
                            'closer_request_proof_photos' => $visit->closer_request_proof_photos ?? [],
                            'kyc_dynamic_form_id' => $visit->kyc_dynamic_form_id,
                            'kyc_custom_fields' => $visit->kyc_custom_fields ?? [],
                            'kyc_section_remarks' => $visit->kyc_section_remarks ?? [],
                            'kyc_schema' => app(\App\Services\KycFormSchemaService::class)->buildReadPayload($visit),
                            'is_finance_direct_closer' => (bool) $visit->is_finance_direct_closer,
                            'approval_admin_id' => $visit->approval_admin_id,
                            'approval_admin' => $visit->approvalAdmin ? ['id' => $visit->approvalAdmin->id, 'name' => $visit->approvalAdmin->name] : null,
                            'lead' => $visit->lead ? ['id' => $visit->lead->id, 'name' => $visit->lead->name, 'phone' => $visit->lead->phone] : null,
                            'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
                            'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                            'can_verify' => $routing->canVerify($user, $visit, \App\Services\VerificationRoutingService::WORKFLOW_CLOSER),
                            'can_verify_closing' => $routing->canVerify($user, $visit, \App\Services\VerificationRoutingService::WORKFLOW_CLOSING),
                        ];
                    });
                
                return response()->json([
                    'data' => array_values($closers->toArray()),
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading pending closers: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Failed to load pending closers',
                    'message' => $e->getMessage(),
                    'data' => [],
                ], 500);
            }
        });
        
        Route::get('/verifications/verified', function (Request $request) {
            try {
                $user = $request->user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                // Get all verified meetings
                $meetings = \App\Models\Meeting::where('verification_status', 'verified')
                    ->where('status', 'completed')
                    ->with([
                        'lead:id,name,phone',
                        'prospect:id,customer_name',
                        'creator:id,name',
                        'assignedTo:id,name',
                        'verifiedBy:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'budget_range', 'property_type',
                        'meeting_notes', 'employee', 'occupation', 'date_of_visit',
                        'project', 'payment_mode', 'tentative_period', 'lead_type',
                        'team_leader', 'lead_id', 'prospect_id', 'created_by', 'assigned_to',
                        'verified_by', 'verified_at', 'photos', 'completion_proof_photos'
                    ])
                    ->orderBy('verified_at', 'desc')
                    ->get()
                    ->map(function($meeting) {
                        return [
                            'id' => $meeting->id,
                            'customer_name' => $meeting->customer_name,
                            'phone' => $meeting->phone,
                            'scheduled_at' => $meeting->scheduled_at ? $meeting->scheduled_at->toIso8601String() : null,
                            'completed_at' => $meeting->completed_at ? $meeting->completed_at->toIso8601String() : null,
                            'verified_at' => $meeting->verified_at ? $meeting->verified_at->toIso8601String() : null,
                            'status' => $meeting->status,
                            'verification_status' => $meeting->verification_status,
                            'budget_range' => $meeting->budget_range,
                            'property_type' => $meeting->property_type,
                            'meeting_notes' => $meeting->meeting_notes,
                            'employee' => $meeting->employee,
                            'occupation' => $meeting->occupation,
                            'date_of_visit' => $meeting->date_of_visit ? $meeting->date_of_visit->toIso8601String() : null,
                            'project' => $meeting->project,
                            'payment_mode' => $meeting->payment_mode,
                            'tentative_period' => $meeting->tentative_period,
                            'lead_type' => $meeting->lead_type,
                            'team_leader' => $meeting->team_leader,
                            'photos' => $meeting->photos ?? [],
                            'completion_proof_photos' => $meeting->completion_proof_photos ?? [],
                            'lead' => $meeting->lead ? ['id' => $meeting->lead->id, 'name' => $meeting->lead->name, 'phone' => $meeting->lead->phone] : null,
                            'prospect' => $meeting->prospect ? ['id' => $meeting->prospect->id, 'customer_name' => $meeting->prospect->customer_name] : null,
                            'creator' => $meeting->creator ? ['id' => $meeting->creator->id, 'name' => $meeting->creator->name] : null,
                            'assignedTo' => $meeting->assignedTo ? ['id' => $meeting->assignedTo->id, 'name' => $meeting->assignedTo->name] : null,
                            'verifiedBy' => $meeting->verifiedBy ? ['id' => $meeting->verifiedBy->id, 'name' => $meeting->verifiedBy->name] : null,
                        ];
                    });
                
                // Get verified site visits (not closers) - verification_status = 'verified' but closer_status is NOT 'verified'
                $siteVisits = \App\Models\SiteVisit::where('verification_status', 'verified')
                    ->where('status', 'completed')
                    ->where(function($query) {
                        $query->whereNull('closer_status')
                              ->orWhere('closer_status', '!=', 'verified');
                    })
                    ->with([
                        'lead:id,name,phone',
                        'creator:id,name',
                        'assignedTo:id,name',
                        'verifiedBy:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'property_name', 'property_address',
                        'budget_range', 'visit_notes', 'closer_status', 'project',
                        'property_type', 'lead_type', 'lead_id', 'created_by', 'assigned_to',
                        'verified_by', 'verified_at', 'photos', 'completion_proof_photos'
                    ])
                    ->orderBy('verified_at', 'desc')
                    ->get()
                    ->map(function($visit) {
                        return [
                            'id' => $visit->id,
                            'customer_name' => $visit->customer_name ?: optional($visit->lead)->name,
                            'phone' => $visit->phone ?: optional($visit->lead)->phone,
                            'scheduled_at' => $visit->scheduled_at ? $visit->scheduled_at->toIso8601String() : null,
                            'completed_at' => $visit->completed_at ? $visit->completed_at->toIso8601String() : null,
                            'verified_at' => $visit->verified_at ? $visit->verified_at->toIso8601String() : null,
                            'status' => $visit->status,
                            'verification_status' => $visit->verification_status,
                            'property_name' => $visit->property_name,
                            'property_address' => $visit->property_address,
                            'budget_range' => $visit->budget_range,
                            'visit_notes' => $visit->visit_notes,
                            'closer_status' => $visit->closer_status,
                            'project' => $visit->project,
                            'property_type' => $visit->property_type,
                            'lead_type' => $visit->lead_type,
                            'employee' => $visit->employee,
                            'photos' => $visit->photos ?? [],
                            'completion_proof_photos' => $visit->completion_proof_photos ?? [],
                            'lead' => $visit->lead ? ['id' => $visit->lead->id, 'name' => $visit->lead->name, 'phone' => $visit->lead->phone] : null,
                            'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
                            'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                            'verifiedBy' => $visit->verifiedBy ? ['id' => $visit->verifiedBy->id, 'name' => $visit->verifiedBy->name] : null,
                        ];
                    });
                
                // Get approved/legacy verified closers after CRM approval
                $closers = \App\Models\SiteVisit::whereIn('closer_status', ['approved', 'verified'])
                    ->where('verification_status', 'verified')
                    ->where('status', 'completed')
                    ->with([
                        'lead:id,name,phone',
                        'creator:id,name',
                        'assignedTo:id,name',
                        'verifiedBy:id,name',
                        'closerVerifiedBy:id,name'
                    ])
                    ->select([
                        'id', 'customer_name', 'phone', 'scheduled_at', 'completed_at',
                        'status', 'verification_status', 'property_name', 'property_address',
                        'budget_range', 'visit_notes', 'closer_status', 'project',
                        'property_type', 'lead_type', 'lead_id', 'created_by', 'assigned_to',
                        'verified_by', 'verified_at', 'closer_verified_by', 'closer_verified_at',
                        'photos', 'completion_proof_photos', 'closer_request_proof_photos'
                    ])
                    ->orderBy('closer_verified_at', 'desc')
                    ->get()
                    ->map(function($visit) {
                        return [
                            'id' => $visit->id,
                            'customer_name' => $visit->customer_name ?: optional($visit->lead)->name,
                            'phone' => $visit->phone ?: optional($visit->lead)->phone,
                            'scheduled_at' => $visit->scheduled_at ? $visit->scheduled_at->toIso8601String() : null,
                            'completed_at' => $visit->completed_at ? $visit->completed_at->toIso8601String() : null,
                            'verified_at' => $visit->verified_at ? $visit->verified_at->toIso8601String() : null,
                            'closer_verified_at' => $visit->closer_verified_at ? $visit->closer_verified_at->toIso8601String() : null,
                            'status' => $visit->status,
                            'verification_status' => $visit->verification_status,
                            'closer_status' => $visit->closer_status,
                            'property_name' => $visit->property_name,
                            'property_address' => $visit->property_address,
                            'budget_range' => $visit->budget_range,
                            'visit_notes' => $visit->visit_notes,
                            'project' => $visit->project,
                            'property_type' => $visit->property_type,
                            'lead_type' => $visit->lead_type,
                            'employee' => $visit->employee,
                            'photos' => $visit->photos ?? [],
                            'completion_proof_photos' => $visit->completion_proof_photos ?? [],
                            'closer_request_proof_photos' => $visit->closer_request_proof_photos ?? [],
                            'lead' => $visit->lead ? ['id' => $visit->lead->id, 'name' => $visit->lead->name, 'phone' => $visit->lead->phone] : null,
                            'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
                            'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                            'verifiedBy' => $visit->verifiedBy ? ['id' => $visit->verifiedBy->id, 'name' => $visit->verifiedBy->name] : null,
                            'closerVerifiedBy' => $visit->closerVerifiedBy ? ['id' => $visit->closerVerifiedBy->id, 'name' => $visit->closerVerifiedBy->name] : null,
                        ];
                    });
                
                $totalCount = $meetings->count() + $siteVisits->count() + $closers->count();
                
                return response()->json([
                    'meetings' => array_values($meetings->toArray()),
                    'site_visits' => array_values($siteVisits->toArray()),
                    'closers' => array_values($closers->toArray()),
                    'total_count' => $totalCount,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading verified items: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Failed to load verified items',
                    'message' => $e->getMessage(),
                    'meetings' => [],
                    'site_visits' => [],
                    'closers' => [],
                    'total_count' => 0,
                ], 500);
            }
        });

        Route::get('/verifications/pending-incentives', function (Request $request) {
            try {
                $user = $request->user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                $incentives = \App\Models\Incentive::where('status', 'pending_finance_manager')
                    ->with([
                        'siteVisit.lead:id,name,phone',
                        'user:id,name',
                    ])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($incentive) use ($user) {
                        return [
                            'id' => $incentive->id,
                            'type' => $incentive->type,
                            'amount' => $incentive->amount,
                            'status' => $incentive->status,
                            'created_at' => optional($incentive->created_at)->toIso8601String(),
                            'site_visit_id' => $incentive->site_visit_id,
                            'user' => $incentive->user ? [
                                'id' => $incentive->user->id,
                                'name' => $incentive->user->name,
                            ] : null,
                            'site_visit' => $incentive->siteVisit ? [
                                'id' => $incentive->siteVisit->id,
                                'customer_name' => $incentive->siteVisit->customer_name,
                                'phone' => $incentive->siteVisit->phone,
                                'closing_verification_status' => $incentive->siteVisit->closing_verification_status,
                                'lead' => $incentive->siteVisit->lead ? [
                                    'id' => $incentive->siteVisit->lead->id,
                                    'name' => $incentive->siteVisit->lead->name,
                                    'phone' => $incentive->siteVisit->lead->phone,
                                ] : null,
                            ] : null,
                            'can_verify' => $user->isAdmin() || $user->isCrm(),
                        ];
                    });

                return response()->json([
                    'data' => array_values($incentives->toArray()),
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading pending incentives: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'error' => 'Failed to load pending incentives',
                    'message' => $e->getMessage(),
                    'data' => [],
                ], 500);
            }
        });
    });

    // Builder routes
    Route::prefix('builders')->group(function () {
        Route::get('/', [BuilderController::class, 'index']);
        Route::post('/', [BuilderController::class, 'store'])->middleware('role:admin,crm');
        Route::get('/{builder}', [BuilderController::class, 'show']);
        Route::put('/{builder}', [BuilderController::class, 'update'])->middleware('role:admin,crm');
        Route::delete('/{builder}', [BuilderController::class, 'destroy'])->middleware('role:admin,crm');
        
        // Builder logo upload
        Route::post('/{builder}/logo', [BuilderController::class, 'uploadLogo'])->middleware('role:admin,crm');
        
        // Builder contacts
        Route::post('/{builder}/contacts', [BuilderController::class, 'addContact'])->middleware('role:admin,crm');
        Route::put('/{builder}/contacts/{contact}', [BuilderController::class, 'updateContact'])->middleware('role:admin,crm');
        Route::delete('/{builder}/contacts/{contact}', [BuilderController::class, 'deleteContact'])->middleware('role:admin,crm');
        
        // Builder projects (nested)
        Route::get('/{builder}/projects', [ProjectController::class, 'index']);
        Route::post('/{builder}/projects', [ProjectController::class, 'store'])->middleware('role:admin,crm');
    });

    // Project routes
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update'])->middleware('role:admin,crm');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->middleware('role:admin,crm');
        
        // Project detail (with contacts and collaterals)
        Route::get('/{project}/detail', [ProjectDetailController::class, 'show']);
        
        // Project collaterals
        Route::get('/{project}/collaterals', [ProjectCollateralController::class, 'index']);
        Route::get('/{project}/collaterals/buttons', [ProjectCollateralController::class, 'buttons']);
        Route::post('/{project}/collaterals', [ProjectCollateralController::class, 'store'])->middleware('role:admin,crm');
        
        // Pricing
        Route::get('/{project}/pricing', [PricingController::class, 'show']);
        Route::put('/{project}/pricing', [PricingController::class, 'update'])->middleware('role:admin,crm');
        
        // Unit types
        Route::get('/{project}/unit-types', [UnitTypeController::class, 'index']);
        Route::post('/{project}/unit-types', [UnitTypeController::class, 'store'])->middleware('role:admin,crm');
    });

    // Collateral routes (standalone)
    Route::prefix('collaterals')->group(function () {
        Route::put('/{collateral}', [ProjectCollateralController::class, 'update'])->middleware('role:admin,crm');
        Route::delete('/{collateral}', [ProjectCollateralController::class, 'destroy'])->middleware('role:admin,crm');
    });

    // Unit type routes (standalone)
    Route::prefix('unit-types')->group(function () {
        Route::put('/{unitType}', [UnitTypeController::class, 'update'])->middleware('role:admin,crm');
        Route::delete('/{unitType}', [UnitTypeController::class, 'destroy'])->middleware('role:admin,crm');
    });

    // Dynamic Forms API
    Route::prefix('forms')->group(function () {
        Route::get('/{identifier}', [\App\Http\Controllers\Api\DynamicFormController::class, 'getForm']);
        Route::get('/{identifier}/render', [\App\Http\Controllers\Api\DynamicFormController::class, 'renderForm']);
        Route::post('/{identifier}/submit', [\App\Http\Controllers\Api\DynamicFormController::class, 'submitForm']);
    });
});
