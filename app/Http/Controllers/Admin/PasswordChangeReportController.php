<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class PasswordChangeReportController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'changed', 'all'], true)
            ? $request->query('status')
            : 'pending';
        $roleId = $request->query('role_id');

        $allUsers = User::query()
            ->with('role:id,name,slug')
            ->where('is_active', true)
            ->when($roleId !== null && $roleId !== '', fn ($query) => $query->where('role_id', $roleId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role_id', 'ui_preferences', 'created_at'])
            ->values();

        $pendingUsers = $allUsers
            ->filter(function (User $user): bool {
                $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];

                return empty($preferences['password_changed_at']);
            })
            ->values();

        $changedUsers = $allUsers
            ->filter(function (User $user): bool {
                $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];

                return !empty($preferences['password_changed_at']);
            })
            ->values();

        $users = match ($status) {
            'changed' => $changedUsers,
            'all' => $allUsers,
            default => $pendingUsers,
        };

        $requesterIds = $users
            ->map(fn (User $user) => data_get($user->ui_preferences, 'password_change_requested_by'))
            ->filter()
            ->unique()
            ->values();

        $requesters = User::query()
            ->whereIn('id', $requesterIds)
            ->pluck('name', 'id');

        return view('admin.password-change-pending.index', [
            'users' => $users,
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'requesters' => $requesters,
            'status' => $status,
            'pendingCount' => $pendingUsers->count(),
            'changedCount' => $changedUsers->count(),
            'totalCount' => $allUsers->count(),
        ]);
    }
}
