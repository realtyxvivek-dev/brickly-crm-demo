<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSessionController extends Controller
{
    public function __construct(
        protected AdminSessionService $adminSessionService
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.sessions.index', [
            'sessionGroups' => $this->adminSessionService->getGroupedActiveSessions(
                search: $request->string('search')->toString(),
                currentSessionId: $request->session()->getId(),
            ),
            'databaseSessionsEnabled' => $this->adminSessionService->usesDatabaseSessions(),
        ]);
    }

    public function revokeUserSessions(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $deletedCount = $this->adminSessionService->revokeUserSessions(
            targetUser: $user,
            actor: $request->user(),
            preserveSessionId: $user->is($request->user()) ? $request->session()->getId() : null,
        );

        return back()->with('success', "Revoked {$deletedCount} active session(s) for {$user->name}.");
    }

    public function revokeAllNonAdminSessions(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $deletedCount = $this->adminSessionService->revokeAllNonAdminSessions(
            actor: $request->user(),
            preserveSessionId: $request->session()->getId(),
        );

        return back()->with('success', "Revoked {$deletedCount} non-admin session(s).");
    }

    public function revokeAllSessions(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $deletedCount = $this->adminSessionService->revokeAllSessions(
            actor: $request->user(),
            preserveSessionId: $request->session()->getId(),
        );

        return back()->with('success', "Revoked {$deletedCount} active session(s) while keeping your current admin session.");
    }
}
