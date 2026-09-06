<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginSecurityEvent;
use App\Models\User;
use App\Services\LoginSecurityService;
use Illuminate\Http\Request;

class LoginSecurityController extends Controller
{
    public function __construct(protected LoginSecurityService $loginSecurityService)
    {
        $this->middleware(['auth', 'role:admin']);
    }

    public function index(Request $request)
    {
        $query = LoginSecurityEvent::with(['user.role', 'reviewer'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('email_normalized', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $events = $query->paginate(25)->withQueryString();
        $lockedUsers = User::query()
            ->whereNotNull('ui_preferences')
            ->where(function ($query) {
                $query->whereNotNull('ui_preferences->login_security->admin_locked_at')
                    ->orWhere('ui_preferences->login_security->lock_until', '>', now()->toDateTimeString());
            })
            ->with('role')
            ->orderBy('name')
            ->get();

        return view('admin.login-security.index', [
            'events' => $events,
            'lockedUsers' => $lockedUsers,
        ]);
    }

    public function show(LoginSecurityEvent $event)
    {
        $event->load(['user.role', 'reviewer']);
        $history = LoginSecurityEvent::query()
            ->where('email_normalized', $event->email_normalized)
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.login-security.show', compact('event', 'history'));
    }

    public function unlock(Request $request, LoginSecurityEvent $event)
    {
        abort_unless($event->user, 404);

        $this->loginSecurityService->unlock($event->user, $request->user(), $event);

        return redirect()
            ->route('admin.login-security.show', $event)
            ->with('success', 'Account unlocked. The user can try login again.');
    }

    public function reject(Request $request, LoginSecurityEvent $event)
    {
        $this->loginSecurityService->reject($event, $request->user());

        return redirect()
            ->route('admin.login-security.show', $event)
            ->with('success', 'Urgent access request rejected.');
    }
}
