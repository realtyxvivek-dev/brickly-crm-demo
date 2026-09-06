<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesktopIssueReport;
use Illuminate\Http\Request;

class DesktopIssueReportController extends Controller
{
    public function index(Request $request)
    {
        $query = DesktopIssueReport::query()
            ->with(['reporter.role', 'assignee'])
            ->latest('reported_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('issue_type')) {
            $query->where('issue_type', $request->issue_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%")
                    ->orWhereHas('reporter', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $reports = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => DesktopIssueReport::query()->count(),
            'open' => DesktopIssueReport::query()->where('status', 'open')->count(),
            'in_progress' => DesktopIssueReport::query()->where('status', 'in_progress')->count(),
            'resolved' => DesktopIssueReport::query()->where('status', 'resolved')->count(),
        ];

        return view('admin.desktop-issue-reports.index', [
            'reports' => $reports,
            'stats' => $stats,
            'issueTypes' => DesktopIssueReport::ISSUE_TYPES,
        ]);
    }

    public function show(DesktopIssueReport $desktopIssueReport)
    {
        $desktopIssueReport->load(['reporter.role', 'assignee']);

        return view('admin.desktop-issue-reports.show', [
            'report' => $desktopIssueReport,
        ]);
    }

    public function updateStatus(Request $request, DesktopIssueReport $desktopIssueReport)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $desktopIssueReport->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'Desktop issue status updated.');
    }
}
