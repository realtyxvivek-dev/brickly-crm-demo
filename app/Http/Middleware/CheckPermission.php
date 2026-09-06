<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();

        // Admin has all permissions
        if ($user->isAdmin()) {
            return $next($request);
        }

        if (method_exists($user, 'hasRolePermission') && $user->hasRolePermission($permission)) {
            return $next($request);
        }

        // Check role-based permissions
        $hasPermission = match($permission) {
            'view_all_leads' => $user->canViewAllLeads(),
            'assign_leads' => $user->canAssignLeads(),
            'manage_users' => $user->canManageUsers(),
            'manage_site_visits' => !$user->isSalesExecutive(),
            'calling_center.view' => $user->canUseCallingCenter('calling_center.view'),
            'calling_center.create_campaign' => $user->canUseCallingCenter('calling_center.create_campaign'),
            'calling_center.agent_queue' => $user->canUseCallingCenter('calling_center.agent_queue'),
            'calling_center.submit_outcome' => $user->canUseCallingCenter('calling_center.submit_outcome'),
            'calling_center.pause_own_queue' => $user->canUseCallingCenter('calling_center.pause_own_queue'),
            'calling_center.manual_push' => $user->canUseCallingCenter('calling_center.manual_push'),
            'calling_center.approve_push' => $user->canUseCallingCenter('calling_center.approve_push'),
            'calling_center.view_own_report' => $user->canUseCallingCenter('calling_center.view_own_report'),
            'calling_center.view_team_report' => $user->canUseCallingCenter('calling_center.view_team_report'),
            'calling_center.view_recordings' => $user->canUseCallingCenter('calling_center.view_recordings'),
            'calling_center.export_reports' => $user->canUseCallingCenter('calling_center.export_reports'),
            default => false,
        };

        if (!$hasPermission) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Insufficient permissions.'], 403);
            }
            abort(403, 'Forbidden. Insufficient permissions.');
        }

        return $next($request);
    }
}
