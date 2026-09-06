<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadAuditorDashboardService;
use App\Services\InsightSheetService;
use App\Services\PhonePrivacyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadAuditorDashboardController extends Controller
{
    public function __construct(private LeadAuditorDashboardService $dashboard, private PhonePrivacyService $privacy)
    {
        $this->middleware(['auth', 'role:lead_quality_auditor']);
    }

    public function index()
    {
        return view('admin.lead-quality-auditor.dashboard', ['salesUsers' => $this->dashboard->users()->select('u.id', 'u.name')->orderBy('u.name')->get()]);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['user_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'range' => ['sometimes', Rule::in(['all', 'today', 'week', 'month', 'year'])]]);
    }

    public function summary(Request $request)
    {
        $filters = $this->filters($request);
        return response()->json($this->dashboard->summary($filters['user_id'] ?? null, $filters['range'] ?? 'all'));
    }

    public function untouched(Request $request)
    {
        $filters = $this->filters($request);
        $page = $this->dashboard->untouched($filters['user_id'] ?? null, $filters['range'] ?? 'all')->orderByRaw('assigned_at IS NULL')->orderBy('assigned_at')->orderBy('lead_id')->paginate(25);
        $page->getCollection()->transform(function ($row) use ($request) {
            $row->phone = $this->privacy->display($row->phone, $request->user());
            return $row;
        });
        return response()->json($page);
    }

    public function tasks(Request $request)
    {
        $filters = $request->validate(['user_id' => ['required', 'integer', 'min:1'], 'bucket' => ['required', Rule::in(['due', 'done', 'completed_today', 'remaining'])], 'category' => ['nullable', Rule::in(LeadAuditorDashboardService::CATEGORIES)], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = $this->dashboard->drilldown($filters['user_id'], $filters['bucket'], $filters['category'] ?? null)->paginate(25);
        $page->getCollection()->transform(function ($row) use ($request) {
            $row->phone = $this->privacy->display($row->phone, $request->user());
            return $row;
        });
        return response()->json($page);
    }

    public function details(Request $request, InsightSheetService $sheet)
    {
        $id = $request->validate(['lead_id' => ['required', 'integer', 'min:1']])['lead_id'];
        $details = $sheet->leadDetails('lead:'.$id);
        abort_unless($details, 404);
        return response()->json($details);
    }
}
