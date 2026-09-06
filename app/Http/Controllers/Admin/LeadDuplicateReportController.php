<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadMergeAudit;
use App\Services\LeadMergeService;
use Illuminate\Http\Request;

class LeadDuplicateReportController extends Controller
{
    public function index(Request $request, LeadMergeService $mergeService)
    {
        $groups = $mergeService->duplicateGroups($request->string('phone')->toString() ?: null, 100)
            ->map(fn (string $phone) => $mergeService->preview($phone, false));
        $audits = LeadMergeAudit::query()->latest('id')->paginate(50)->withQueryString();

        return view('admin.lead-duplicates.index', [
            'groups' => $groups,
            'remainingGroupCount' => $mergeService->duplicateGroups()->count(),
            'mergedCount' => LeadMergeAudit::where('status', 'merged')->count(),
            'failedCount' => LeadMergeAudit::where('status', 'failed')->count(),
            'audits' => $audits,
        ]);
    }
}
