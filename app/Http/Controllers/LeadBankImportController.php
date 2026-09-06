<?php

namespace App\Http\Controllers;

use App\Models\LeadBankImportSession;
use App\Services\LeadBankImportService;
use Illuminate\Http\Request;

class LeadBankImportController extends Controller
{
    public function __construct(private readonly LeadBankImportService $importService)
    {
    }

    public function index()
    {
        return view('lead-bank.import', [
            'sessions' => LeadBankImportSession::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }

    public function downloadSample()
    {
        $path = public_path('downloads/lead-bank-import-sample.xlsx');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'lead-bank-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
            'source_type' => ['required', 'string', 'max:80'],
            'default_tags' => ['nullable', 'string', 'max:500'],
            'create_folder' => ['nullable', 'boolean'],
            'folder_name' => ['nullable', 'required_if:create_folder,1', 'string', 'max:80'],
            'folder_color' => ['nullable', 'required_if:create_folder,1', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $folder = $request->boolean('create_folder')
            ? [
                'name' => trim((string) $data['folder_name']),
                'color' => strtoupper((string) ($data['folder_color'] ?? '#205A44')),
            ]
            : null;

        try {
            $session = $this->importService->createSession(
                $data['file'],
                auth()->id(),
                $data['source_type'],
                $this->tagsFromString((string) ($data['default_tags'] ?? '')),
                $folder
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('lead-bank.import.show', $session)->with('success', 'Draft import session created.');
    }

    public function show(LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        $isImportProcessing = $session->import_batch_id && $session->status !== 'imported';
        $isPreviewReady = !$isImportProcessing && ($session->status === 'ready' || $session->status === 'imported');

        return view('lead-bank.import-show', [
            'session' => $isPreviewReady
                ? $session->load(['rows' => fn ($query) => $query->orderBy('row_number')])
                : $session,
            'fieldOptions' => $this->importService->fieldOptions(),
            'summary' => $this->importService->summary($session),
            'isPreviewReady' => $isPreviewReady,
            'isImportProcessing' => $isImportProcessing,
        ]);
    }

    public function processPreview(Request $request, LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        if ($session->status === 'imported') {
            return response()->json([
                'success' => true,
                'done' => true,
                'redirect_url' => route('lead-bank.import.show', $session),
            ]);
        }

        try {
            $result = $this->importService->processPreviewChunk($session, 100);
        } catch (\Throwable $e) {
            $session->update(['processing_error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'done' => $result['done'],
            'processed' => $result['processed'],
            'total' => $result['total'],
            'percent' => $result['percent'],
            'redirect_url' => $result['done'] ? route('lead-bank.import.show', $session) : null,
        ]);
    }

    public function processConfirm(Request $request, LeadBankImportSession $session)
    {
        $this->authorizeSession($session);

        try {
            $result = $this->importService->processConfirmChunk($session, auth()->id(), 100);
        } catch (\Throwable $e) {
            $session->update(['processing_error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'done' => $result['done'],
            'processed' => $result['processed'],
            'total' => $result['total'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
            'percent' => $result['percent'],
            'redirect_url' => $result['done'] ? route('lead-bank.index') : null,
        ]);
    }

    public function updateMapping(Request $request, LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        if ($session->status === 'imported') {
            return back()->with('error', 'Imported sessions cannot be remapped.');
        }

        $data = $request->validate([
            'column_mapping' => ['required', 'array'],
            'column_mapping.*' => ['required', 'string'],
        ]);

        $this->importService->updateMapping($session, $data['column_mapping']);

        return redirect()->route('lead-bank.import.show', $session)->with('success', 'Column mapping refreshed.');
    }

    public function updateRows(Request $request, LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        if ($session->status === 'imported') {
            return back()->with('error', 'Imported sessions cannot be edited.');
        }
        if ($session->status !== 'ready') {
            return back()->with('error', 'Preview is still processing. Please wait until it is ready.');
        }

        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.tags' => ['nullable', 'string', 'max:500'],
            'rows.*.import_action' => ['nullable', 'in:create,update_existing,skip'],
            'rows.*.include' => ['nullable'],
        ]);

        $this->importService->updateRows($session, $data['rows'] ?? []);

        return redirect()->route('lead-bank.import.show', $session)->with('success', 'Preview row decisions saved.');
    }

    public function bulkTag(Request $request, LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        if ($session->status === 'imported') {
            return back()->with('error', 'Imported sessions cannot be edited.');
        }
        if ($session->status !== 'ready') {
            return back()->with('error', 'Preview is still processing. Please wait until it is ready.');
        }

        $data = $request->validate([
            'bulk_tag' => ['required', 'string', 'max:80'],
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['integer', 'exists:lead_bank_import_rows,id'],
        ]);

        $count = $this->importService->applyBulkTag($session, $data['bulk_tag'], $data['row_ids']);

        return redirect()->route('lead-bank.import.show', $session)->with('success', "Tag applied to {$count} preview row(s).");
    }

    public function confirm(LeadBankImportSession $session)
    {
        $this->authorizeSession($session);
        if ($session->status !== 'ready') {
            return back()->with('error', 'Preview is still processing. Please wait until it is ready.');
        }

        try {
            $this->importService->startConfirm($session, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('lead-bank.import.show', $session)
            ->with('success', 'Lead Bank import started. Keep this page open until it completes.');
    }

    private function authorizeSession(LeadBankImportSession $session): void
    {
        abort_unless((int) $session->user_id === (int) auth()->id(), 403);
    }

    private function tagsFromString(string $tags): array
    {
        return collect(explode(',', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => strtolower($tag))
            ->values()
            ->all();
    }
}
