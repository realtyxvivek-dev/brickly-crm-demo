<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadAuditService;
use Illuminate\Http\Request;

class LeadAuditController extends Controller
{
    public function __construct(
        private readonly LeadAuditService $leadAuditService
    ) {
        $this->middleware(['auth', 'role:admin,crm']);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $contextType = (string) $request->query('context_type', '');
        $contextId = (int) $request->query('context_id', 0);

        $audit = null;
        $candidates = collect();

        if ($contextType !== '' && $contextId > 0) {
            $audit = $this->leadAuditService->buildAudit($contextType, $contextId);
        } elseif ($search !== '') {
            $candidates = $this->leadAuditService->searchCandidates($search);

            if ($candidates->count() === 1) {
                $single = $candidates->first();
                return redirect()->route('admin.lead-audit.index', [
                    'search' => $search,
                    'context_type' => $single['type'],
                    'context_id' => $single['id'],
                ]);
            }
        }

        return view('admin.lead-audit.index', compact('search', 'audit', 'candidates'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'context_type' => 'required|in:lead,fb_lead,fb_webhook_event,mcube_webhook_log',
            'context_id' => 'required|integer|min:1',
        ]);

        $audit = $this->leadAuditService->buildAudit(
            (string) $request->query('context_type'),
            (int) $request->query('context_id')
        );

        $html = view('admin.lead-audit.pdf', compact('audit'))->render();
        $filename = 'lead_audit_' . now()->format('Y-m-d_His') . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download($filename);
        }

        if (class_exists('PDF')) {
            return \PDF::loadHTML($html)->download($filename);
        }

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
    }
}
