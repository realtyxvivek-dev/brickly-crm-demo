<?php

namespace App\Http\Controllers;

use App\Models\LeadDownloadRequest;
use App\Services\LeadExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SalesManagerLeadDownloadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            if (!$user || (!$user->isSalesManager() && !$user->isSeniorManager() && !$user->isAssistantSalesManager())) {
                abort(403, 'Unauthorized.');
            }

            return $next($request);
        });
    }

    public function index(LeadExportService $leadExportService)
    {
        $user = auth()->user();
        $availableUsers = $leadExportService->getAvailableAssigneesFor($user);
        $canUseTeamScope = $availableUsers->count() > 1;

        return view('sales-manager.lead-downloads.index', [
            'statuses' => $leadExportService->getAvailableStatuses(),
            'leadTypes' => $leadExportService->getLeadTypeOptions(),
            'dateRanges' => $leadExportService->getDateRangeOptions(),
            'fields' => $leadExportService->getFieldLabels(),
            'interestedProjects' => $leadExportService->getInterestedProjects(),
            'sources' => $leadExportService->getAvailableSources(),
            'availableUsers' => $availableUsers,
            'canUseTeamScope' => $canUseTeamScope,
            'requests' => LeadDownloadRequest::with(['reviewer'])
                ->where('requested_by', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, LeadExportService $leadExportService)
    {
        $validated = $this->validateExportRequest($request, $leadExportService);

        $filters = $leadExportService->sanitizeFiltersForUser(auth()->user(), $validated);
        $fields = $leadExportService->sanitizeFields($validated['fields']);
        $matchedCount = $leadExportService->countLeadsForUser(auth()->user(), $filters);

        if ($matchedCount === 0) {
            return back()
                ->withInput()
                ->with('error', 'No records found matching the selected filters. Please change filters and try again.');
        }

        LeadDownloadRequest::create([
            'requested_by' => auth()->id(),
            'status' => LeadDownloadRequest::STATUS_PENDING,
            'format' => $validated['format'],
            'filters' => array_merge($filters, ['matched_count_at_request' => $matchedCount]),
            'fields' => $fields,
        ]);

        $recordLabel = in_array(($filters['report_type'] ?? 'all'), ['visits', 'meetings'], true) ? 'records' : 'leads';

        return redirect()
            ->route('sales-manager.lead-downloads.index')
            ->with('success', "Lead download request submitted for admin approval. {$matchedCount} {$recordLabel} matched.");
    }

    public function preview(Request $request, LeadExportService $leadExportService)
    {
        $validated = $this->validateExportRequest($request, $leadExportService);
        $user = auth()->user();
        $filters = $leadExportService->sanitizeFiltersForUser($user, $validated);
        $fields = $leadExportService->sanitizeFields($validated['fields']);
        $matchedCount = $leadExportService->countLeadsForUser($user, $filters);

        return response()->json([
            'matched_count' => $matchedCount,
            'can_submit' => $matchedCount > 0,
            'format' => strtoupper($validated['format']),
            'record_label' => in_array(($filters['report_type'] ?? 'all'), ['visits', 'meetings'], true) ? 'records matched' : 'leads matched',
            'fields_count' => count($fields),
            'summary' => [
                'report_type' => ucfirst(str_replace('_', ' ', $filters['report_type'] ?? 'all')),
                'scope' => ucfirst(str_replace('_', ' ', $filters['assigned_scope'] ?? 'own')),
                'date_range' => ucfirst(str_replace('_', ' ', $filters['date_range'] ?? 'all_time')),
                'sources' => count($filters['source'] ?? []),
                'projects' => count($filters['interested_projects'] ?? []),
                'statuses' => count($filters['status'] ?? []),
                'lead_types' => count($filters['lead_type'] ?? []),
                'search' => $filters['search'] ?: null,
            ],
        ]);
    }

    public function download(LeadDownloadRequest $leadDownloadRequest)
    {
        $user = auth()->user();

        abort_unless($leadDownloadRequest->requested_by === $user->id, 403);

        if ($leadDownloadRequest->expires_at && $leadDownloadRequest->expires_at->isPast()) {
            $leadDownloadRequest->update(['status' => LeadDownloadRequest::STATUS_EXPIRED]);
            return back()->with('error', 'This download link has expired. Please submit a fresh request.');
        }

        if ($leadDownloadRequest->hasReachedDownloadLimit()) {
            return back()->with('error', 'Download limit reached. Please request again.');
        }

        if (!$leadDownloadRequest->isDownloadReady()) {
            return back()->with('error', 'This export file is not available for download yet.');
        }

        if (!Storage::disk($leadDownloadRequest->file_disk ?? 'local')->exists($leadDownloadRequest->file_path)) {
            return back()->with('error', 'The export file could not be found on the server.');
        }

        $leadDownloadRequest->forceFill([
            'download_click_count' => (int) $leadDownloadRequest->download_click_count + 1,
            'last_downloaded_at' => now(),
        ])->save();

        return Storage::disk($leadDownloadRequest->file_disk ?? 'local')->download(
            $leadDownloadRequest->file_path,
            $leadDownloadRequest->file_name
        );
    }

    private function validateExportRequest(Request $request, LeadExportService $leadExportService): array
    {
        $fieldKeys = array_keys($leadExportService->getFieldLabels());
        $dateRanges = array_keys($leadExportService->getDateRangeOptions());
        $leadTypeKeys = array_keys($leadExportService->getLeadTypeOptions());
        $statusKeys = $leadExportService->getAvailableStatuses();

        return $request->validate([
            'report_type' => ['nullable', Rule::in(['all', 'visits', 'meetings', 'prospects', 'closed', 'dead', 'source_project'])],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in($fieldKeys)],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::in($statusKeys)],
            'lead_type' => ['nullable', 'array'],
            'lead_type.*' => [Rule::in($leadTypeKeys)],
            'source' => ['nullable', 'array'],
            'source.*' => ['string', 'max:120'],
            'interested_projects' => ['nullable', 'array'],
            'interested_projects.*' => ['integer', 'exists:interested_project_names,id'],
            'date_range' => ['required', Rule::in($dateRanges)],
            'from_date' => ['nullable', 'date', 'required_if:date_range,custom'],
            'to_date' => ['nullable', 'date', 'required_if:date_range,custom', 'after_or_equal:from_date'],
            'assigned_scope' => ['required', Rule::in(['own', 'my_team', 'specific_user'])],
            'user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
