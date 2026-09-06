<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrHiringController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $search = trim((string) $request->string('search'));
        $perPage = $this->resolvePerPage($request);

        $baseQuery = $this->candidateQuery($user->id);

        $counts = [];
        foreach (Lead::hiringStatusOptions() as $key => $label) {
            $counts[$key] = (clone $baseQuery)->where('hiring_status', $key)->count();
        }

        $candidateQuery = (clone $baseQuery)
            ->select([
                'id',
                'name',
                'phone',
                'source',
                'notes',
                'hiring_status',
                'hr_remark',
                'next_followup_at',
                'is_hiring_candidate',
                'created_at',
                'updated_at',
            ])
            ->when($status !== '' && array_key_exists($status, Lead::hiringStatusOptions()), function (Builder $query) use ($status) {
                $query->where('hiring_status', $status);
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->latest();

        $paginationSize = $perPage === 'all'
            ? max(1, (clone $candidateQuery)->count())
            : $perPage;

        $candidates = $candidateQuery
            ->paginate($paginationSize)
            ->withQueryString();

        return view('hr-manager.hiring.index', [
            'candidates' => $candidates,
            'counts' => $counts,
            'statusOptions' => Lead::hiringStatusOptions(),
            'selectedStatus' => $status,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => $this->perPageOptions(),
            'routePrefix' => $this->hiringRoutePrefix($request),
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        $candidate = $this->candidateQuery($request->user()->id, true)
            ->whereKey($lead->id)
            ->firstOrFail();

        return view('hr-manager.hiring.show', [
            'candidate' => $candidate,
            'statusOptions' => Lead::hiringStatusOptions(),
            'routePrefix' => $this->hiringRoutePrefix($request),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $candidate = $this->candidateQuery($request->user()->id)
            ->whereKey($lead->id)
            ->firstOrFail();

        $data = $request->validate([
            'hiring_status' => 'required|in:' . implode(',', array_keys(Lead::hiringStatusOptions())),
            'hr_remark' => 'nullable|string',
            'next_followup_at' => 'nullable|date',
        ]);

        if (in_array($data['hiring_status'], ['rejected', 'not_interested', 'not_reachable', 'wrong_number', 'duplicate'], true)
            && trim((string) ($data['hr_remark'] ?? '')) === '') {
            return back()
                ->withInput()
                ->withErrors(['hr_remark' => 'Remark required hai jab candidate close/drop status me ja raha hai.']);
        }

        if ($data['hiring_status'] === 'interview_scheduled' && empty($data['next_followup_at'])) {
            return back()
                ->withInput()
                ->withErrors(['next_followup_at' => 'Interview schedule ke liye date/time required hai.']);
        }

        $candidate->update([
            'hiring_status' => $data['hiring_status'],
            'hr_remark' => trim((string) ($data['hr_remark'] ?? '')) ?: null,
            'next_followup_at' => $data['next_followup_at'] ?? null,
        ]);

        if ($request->boolean('return_to_index')) {
            return redirect()
                ->route($this->hiringRoutePrefix($request) . '.index', array_filter([
                    'status' => $request->input('current_status'),
                    'search' => $request->input('current_search'),
                    'per_page' => $request->input('current_per_page'),
                ]))
                ->with('success', 'Candidate status updated successfully.');
        }

        return redirect()
            ->route($this->hiringRoutePrefix($request) . '.show', $candidate)
            ->with('success', 'Candidate status updated successfully.');
    }

    protected function hiringRoutePrefix(Request $request): string
    {
        return $request->routeIs('junior-hr.*') ? 'junior-hr.hiring' : 'hr-manager.hiring';
    }

    protected function resolvePerPage(Request $request): int|string
    {
        $requested = strtolower((string) $request->query('per_page', '15'));
        $allowed = array_map('strval', array_keys($this->perPageOptions()));

        if (! in_array($requested, $allowed, true)) {
            return 15;
        }

        return $requested === 'all' ? 'all' : (int) $requested;
    }

    protected function perPageOptions(): array
    {
        return [
            '15' => '15',
            '50' => '50',
            '100' => '100',
            '200' => '200',
            'all' => 'All',
        ];
    }

    protected function candidateQuery(int $userId, bool $withDetails = false): Builder
    {
        $query = Lead::query()
            ->where('is_hiring_candidate', true)
            ->whereHas('activeAssignments', function (Builder $query) use ($userId) {
                $query->where('assigned_to', $userId)
                    ->where('is_active', true);
            });

        if ($withDetails) {
            $query->with(['activeAssignments.assignedTo.role', 'latestFbLead.form.page']);
        }

        return $query;
    }
}
