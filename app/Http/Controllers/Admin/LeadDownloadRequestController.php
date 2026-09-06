<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\LeadDownloadReadyMail;
use App\Models\LeadDownloadRequest;
use App\Models\MailDeliveryLog;
use App\Services\LeadExportService;
use App\Services\MailDeliveryLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadDownloadRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = LeadDownloadRequest::with(['requester.role', 'reviewer'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return view('admin.lead-download-requests.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'statuses' => [
                LeadDownloadRequest::STATUS_PENDING,
                LeadDownloadRequest::STATUS_APPROVED,
                LeadDownloadRequest::STATUS_PROCESSING,
                LeadDownloadRequest::STATUS_COMPLETED,
                LeadDownloadRequest::STATUS_REJECTED,
                LeadDownloadRequest::STATUS_EXPIRED,
                LeadDownloadRequest::STATUS_FAILED,
            ],
            'currentStatus' => $status,
        ]);
    }

    public function approve(Request $request, LeadDownloadRequest $leadDownloadRequest, LeadExportService $leadExportService, MailDeliveryLogger $mailLogger)
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!in_array($leadDownloadRequest->status, [LeadDownloadRequest::STATUS_PENDING, LeadDownloadRequest::STATUS_APPROVED], true)) {
            return back()->with('error', 'Only pending or previously approved requests can be processed.');
        }

        DB::transaction(function () use ($leadDownloadRequest, $validated) {
            $leadDownloadRequest->update([
                'status' => LeadDownloadRequest::STATUS_PROCESSING,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'admin_note' => $validated['admin_note'] ?? null,
            ]);
        });

        try {
            $export = $leadExportService->generateLeadExportFile(
                $leadDownloadRequest->requester,
                $leadDownloadRequest->filters ?? [],
                $leadDownloadRequest->fields ?? [],
                $leadDownloadRequest->format
            );

            $leadDownloadRequest->update([
                'status' => LeadDownloadRequest::STATUS_COMPLETED,
                'actual_format' => $export['actual_format'],
                'file_disk' => $export['disk'],
                'file_path' => $export['path'],
                'file_name' => $export['file_name'],
                'file_mime' => $export['mime_type'],
                'exported_records_count' => $export['record_count'],
                'processed_at' => now(),
                'expires_at' => now()->addHours(24),
                'download_click_count' => 0,
                'last_downloaded_at' => null,
            ]);

            $mailLog = $mailLogger->sendMailable(
                MailDeliveryLog::TYPE_LEAD_DOWNLOAD_READY,
                'Your Lead Export Is Ready',
                $leadDownloadRequest->requester,
                new LeadDownloadReadyMail($leadDownloadRequest),
                [
                    'request_id' => $leadDownloadRequest->id,
                    'format' => $leadDownloadRequest->actual_format,
                    'records' => $leadDownloadRequest->exported_records_count,
                    'expires_at' => optional($leadDownloadRequest->expires_at)->toDateTimeString(),
                    'max_downloads' => LeadDownloadRequest::MAX_DOWNLOAD_CLICKS,
                ],
                $leadDownloadRequest,
                auth()->id()
            );

            if ($mailLog->status !== MailDeliveryLog::STATUS_SENT) {
                return back()->with('success', 'Lead download approved and export generated. Email delivery failed, but the file is available in the ASM portal.');
            }

            return back()->with('success', 'Lead download approved and export generated successfully.');
        } catch (\Throwable $throwable) {
            $leadDownloadRequest->update([
                'status' => LeadDownloadRequest::STATUS_FAILED,
                'admin_note' => trim(($validated['admin_note'] ?? '') . "\nGeneration failed: " . $throwable->getMessage()),
            ]);

            return back()->with('error', 'Approval saved, but export generation failed: ' . $throwable->getMessage());
        }
    }

    public function reject(Request $request, LeadDownloadRequest $leadDownloadRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!in_array($leadDownloadRequest->status, [LeadDownloadRequest::STATUS_PENDING, LeadDownloadRequest::STATUS_APPROVED, LeadDownloadRequest::STATUS_PROCESSING], true)) {
            return back()->with('error', 'This request can no longer be rejected.');
        }

        $leadDownloadRequest->update([
            'status' => LeadDownloadRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'admin_note' => $validated['admin_note'] ?? null,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'Lead download request rejected.');
    }
}
