<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacebookLeadCenterAuditService;
use Illuminate\Http\Request;

class FacebookLeadCenterExtensionController extends Controller
{
    public function __construct(
        private readonly FacebookLeadCenterAuditService $service,
    ) {
    }

    public function compare(Request $request)
    {
        $user = $request->user();
        abort_unless($this->isEligibleUser($user), 403, 'You are not allowed to use the Facebook Lead Center extension.');

        $leads = $request->input('leads');
        if (!is_array($leads)) {
            return response()->json([
                'success' => false,
                'message' => 'No Facebook Lead Center rows were received. Refresh Lead Center and scan again.',
            ], 422);
        }

        $validated = [
            'source_url' => is_string($request->input('source_url')) ? mb_substr($request->input('source_url'), 0, 2048) : null,
            'page_title' => is_string($request->input('page_title')) ? mb_substr($request->input('page_title'), 0, 255) : null,
            'browser_meta' => is_array($request->input('browser_meta')) ? $request->input('browser_meta') : [],
            'leads' => collect($leads)
                ->filter(fn ($row) => is_array($row))
                ->take(500)
                ->values()
                ->all(),
        ];

        if (count($validated['leads']) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No readable Facebook Lead Center rows found on this screen.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->compareAndStore($user, $validated),
        ]);
    }

    public function store(Request $request)
    {
        return $this->compare($request);
    }

    public function importSelected(Request $request)
    {
        $user = $request->user();
        abort_unless($this->isEligibleUser($user), 403, 'You are not allowed to use the Facebook Lead Center extension.');

        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1', 'max:100'],
            'row_ids.*' => ['integer', 'distinct'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->importSelected($user, $validated['row_ids']),
        ]);
    }

    public function importCaptured(Request $request)
    {
        $user = $request->user();
        abort_unless($this->isEligibleUser($user), 403, 'You are not allowed to use the Facebook Lead Center extension.');

        $validated = $request->validate([
            'leads' => ['required', 'array', 'min:1', 'max:250'],
            'leads.*.client_key' => ['nullable', 'string', 'max:255'],
            'leads.*.name' => ['nullable', 'string', 'max:255'],
            'leads.*.phone' => ['nullable', 'string', 'max:80'],
            'leads.*.email' => ['nullable', 'string', 'max:255'],
            'leads.*.facebook_lead_id' => ['nullable', 'string', 'max:120'],
            'leads.*.page_name' => ['nullable', 'string', 'max:255'],
            'leads.*.form_name' => ['nullable', 'string', 'max:255'],
            'leads.*.lead_time' => ['nullable', 'string', 'max:255'],
            'leads.*.source' => ['nullable', 'string', 'max:120'],
            'leads.*.stage' => ['nullable', 'string', 'max:120'],
            'leads.*.assigned_to' => ['nullable', 'string', 'max:255'],
            'leads.*.raw_text' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->importCapturedLeads($user, $validated['leads']),
        ]);
    }

    private function isEligibleUser($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();
    }
}
