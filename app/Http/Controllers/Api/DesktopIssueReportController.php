<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\DesktopIssueReport;
use App\Models\User;
use Illuminate\Http\Request;

class DesktopIssueReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'issue_type' => 'nullable|string|in:login,lead,task,whatsapp,attendance,slow_app,other',
            'issueType' => 'nullable|string|in:login,lead,task,whatsapp,attendance,slow_app,other',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:5000',
            'current_url' => 'nullable|string|max:2000',
            'currentUrl' => 'nullable|string|max:2000',
            'page_title' => 'nullable|string|max:255',
            'pageTitle' => 'nullable|string|max:255',
            'app_version_name' => 'nullable|string|max:50',
            'appVersionName' => 'nullable|string|max:50',
            'app_version_code' => 'nullable|integer|min:1|max:999999',
            'appVersionCode' => 'nullable|integer|min:1|max:999999',
            'device_name' => 'nullable|string|max:150',
            'deviceName' => 'nullable|string|max:150',
            'os_version' => 'nullable|string|max:255',
            'osVersion' => 'nullable|string|max:255',
            'activity_logs' => 'nullable|array',
            'activityLogs' => 'nullable|array',
            'last_error' => 'nullable|string|max:2000',
            'lastError' => 'nullable|string|max:2000',
        ]);

        $assignee = $this->defaultAssignee();

        $report = DesktopIssueReport::query()->create([
            'reported_by_user_id' => $request->user()->id,
            'assigned_to_user_id' => $assignee?->id,
            'issue_type' => $validated['issue_type'] ?? $validated['issueType'] ?? 'other',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'current_url' => $validated['current_url'] ?? $validated['currentUrl'] ?? null,
            'page_title' => $validated['page_title'] ?? $validated['pageTitle'] ?? null,
            'app_version_name' => $validated['app_version_name'] ?? $validated['appVersionName'] ?? null,
            'app_version_code' => $validated['app_version_code'] ?? $validated['appVersionCode'] ?? null,
            'device_name' => $validated['device_name'] ?? $validated['deviceName'] ?? null,
            'os_version' => $validated['os_version'] ?? $validated['osVersion'] ?? null,
            'activity_logs' => $validated['activity_logs'] ?? $validated['activityLogs'] ?? [],
            'last_error' => $validated['last_error'] ?? $validated['lastError'] ?? null,
            'reported_at' => now(),
        ]);

        if ($assignee) {
            AppNotification::query()->create([
                'user_id' => $assignee->id,
                'type' => AppNotification::TYPE_DESKTOP_ISSUE_REPORT,
                'title' => 'New desktop issue report',
                'message' => $request->user()->name . ' reported: ' . $report->title,
                'data' => [
                    'desktop_issue_report_id' => $report->id,
                    'reported_by_user_id' => $request->user()->id,
                    'issue_type' => $report->issue_type,
                ],
                'action_type' => AppNotification::ACTION_DESKTOP_ISSUE,
                'action_url' => route('admin.desktop-issue-reports.show', $report),
            ]);
        }

        return response()->json([
            'success' => true,
            'reportId' => $report->id,
            'assignedTo' => $assignee?->name,
        ], 201);
    }

    private function defaultAssignee(): ?User
    {
        return User::query()->where('email', 'vivek.baseinfra@gmail.com')->first()
            ?? User::query()
                ->whereHas('role', fn ($query) => $query
                    ->where('slug', 'admin')
                    ->orWhereRaw('LOWER(name) = ?', ['admin']))
                ->orderBy('id')
                ->first();
    }
}
