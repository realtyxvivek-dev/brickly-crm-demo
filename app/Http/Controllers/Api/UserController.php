<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\SystemSettings;
use App\Services\UserDeletionTransferService;
use App\Services\NewUserMailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected UserDeletionTransferService $userDeletionTransferService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = User::with(['role', 'manager']);

        // Hide admin users from CRM view
        if ($user->isCrm() && !$user->isAdmin()) {
            $query->whereHas('role', function($q) {
                $q->where('slug', '!=', Role::ADMIN);
            });
        }

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $users = $query->latest()->paginate($perPage);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Validate role_id - CRM users cannot create Admin or CRM users
        if ($user->isCrm() && !$user->isAdmin()) {
            $role = Role::find($validated['role_id']);
            if ($role && in_array($role->slug, [Role::ADMIN, Role::CRM])) {
                return response()->json(['message' => 'CRM users cannot create Admin or CRM users'], 403);
            }
        }

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);

        $newUser = User::create($validated);

        if (filter_var(SystemSettings::get('send_welcome_email_to_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NewUserMailService::class)->sendWelcomeEmailIfEnabled($newUser, $plainPassword);
        }
        if (filter_var(SystemSettings::get('notify_admin_on_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NotificationService::class)->notifyAdminsNewUser($newUser);
        }

        return response()->json($newUser->load(['role', 'manager']), 201);
    }

    public function show(User $user)
    {
        $currentUser = request()->user();

        if (!$currentUser->canManageUsers() && $currentUser->id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user->load(['role', 'manager', 'teamMembers.role']);

        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!$currentUser->canManageUsers() && $currentUser->id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Only admins can change roles
        if (isset($validated['role_id']) && !$currentUser->canManageUsers()) {
            unset($validated['role_id']);
        } else if (isset($validated['role_id'])) {
            // CRM users cannot assign Admin or CRM roles
            if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
                $role = Role::find($validated['role_id']);
                if ($role && in_array($role->slug, [Role::ADMIN, Role::CRM])) {
                    return response()->json(['message' => 'CRM users cannot assign Admin or CRM roles'], 403);
                }
            }
        }

        $user->update($validated);

        return response()->json($user->load(['role', 'manager']));
    }

    public function destroy(User $user)
    {
        $currentUser = request()->user();

        // Only Admin can delete users
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Only administrators can delete users'], 403);
        }

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }

        $activeLeadCount = count($this->userDeletionTransferService->getActiveLeadIds($user));
        if ($activeLeadCount > 0) {
            return response()->json([
                'message' => 'Transfer active leads before deleting this user.',
                'action_required' => 'transfer_required',
                'active_leads_count' => $activeLeadCount,
                'transfer_url' => route('users.transfer-delete', $user),
            ], 409);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function impersonate(Request $request, \App\Models\User $user)
    {
        $admin = $request->user();

        if ($admin->id === $user->id) {
            return response()->json(['message' => 'Cannot impersonate yourself'], 422);
        }

        $impersonateToken = $user->createToken(
            'impersonate_by_admin_' . $admin->id,
            ['*'],
            now()->addHours(8)
        )->plainTextToken;

        return response()->json([
            'success'           => true,
            'message'           => 'Now logged in as ' . $user->name,
            'impersonate_token' => $impersonateToken,
            'target_user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'role'  => optional($user->role)->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function stopImpersonation(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if (str_starts_with($token->name, 'impersonate_by_admin_')) {
            $token->delete();
        }
        return response()->json(['success' => true, 'message' => 'Impersonation ended']);
    }
}
