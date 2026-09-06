<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\SystemSettings;
use App\Services\NewUserMailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getRoles()
    {
        $currentUser = auth()->user();
        $roles = Role::where('is_active', true)->get();
        
        // Filter roles for CRM users - only allow Telecaller, Sales Executive, Senior Manager
        if ($currentUser && $currentUser->isCrm() && !$currentUser->isAdmin()) {
            $roles = $roles->filter(function($role) {
                return in_array($role->slug, [Role::SALES_EXECUTIVE, Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER]);
            });
        }
        
        return response()->json($roles);
    }

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $query = User::with(['role', 'manager']);

        // Hide admin users from CRM view
        if ($currentUser && $currentUser->isCrm() && !$currentUser->isAdmin()) {
            $query->whereHas('role', function($q) {
                $q->where('slug', '!=', Role::ADMIN);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Validate role_id - CRM users cannot create Admin or CRM users
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->isCrm() && !$currentUser->isAdmin()) {
            $role = Role::find($validated['role_id']);
            if ($role && in_array($role->slug, [Role::ADMIN, Role::CRM])) {
                return response()->json(['message' => 'CRM users cannot create Admin or CRM users'], 403);
            }
        }

        // Default password if not provided
        if (empty($validated['password'])) {
            $validated['password'] = '123456';
        }

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['ui_preferences'] = [
            'must_change_password' => true,
            'password_change_requested_at' => now()->toDateTimeString(),
            'password_change_requested_by' => $currentUser?->id,
        ];

        $user = User::create($validated);

        if (filter_var(SystemSettings::get('send_welcome_email_to_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NewUserMailService::class)->sendWelcomeEmailIfEnabled($user, $plainPassword);
        }
        if (filter_var(SystemSettings::get('notify_admin_on_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NotificationService::class)->notifyAdminsNewUser($user);
        }

        return response()->json($user->load(['role', 'manager']), 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $passwordWasChanged = true;
        } else {
            $passwordWasChanged = false;
            unset($validated['password']);
        }

        // CRM users cannot assign Admin or CRM roles
        $currentUser = auth()->user();
        if (isset($validated['role_id']) && $currentUser && $currentUser->isCrm() && !$currentUser->isAdmin()) {
            $role = Role::find($validated['role_id']);
            if ($role && in_array($role->slug, [Role::ADMIN, Role::CRM])) {
                return response()->json(['message' => 'CRM users cannot assign Admin or CRM roles'], 403);
            }
        }

        $user->update($validated);
        if ($passwordWasChanged) {
            $user->markPasswordChangeRequired($currentUser?->id);
        }

        return response()->json($user->load(['role', 'manager']));
    }

    public function destroy($id)
    {
        $currentUser = auth()->user();
        
        // Only Admin can delete users
        if (!$currentUser || !$currentUser->isAdmin()) {
            return response()->json(['message' => 'Only administrators can delete users'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
