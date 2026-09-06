<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Models\Role;
use App\Models\Lead;
use App\Models\PurchaseOrderAccess;
use App\Models\ActivityLog;
use App\Models\SystemSettings;
use App\Services\AdminSessionService;
use App\Services\UserDeletionTransferService;
use App\Services\NewUserMailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class UserController extends Controller
{
    private const ASM_ALLOWED_MANAGER_SLUGS = [
        Role::ADMIN,
        Role::CRM,
        Role::SALES_MANAGER,
        Role::SENIOR_MANAGER,
    ];

    public function __construct(
        protected UserDeletionTransferService $userDeletionTransferService,
        protected AdminSessionService $adminSessionService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $currentUser = $request->user();
        
        // Ensure role is loaded
        if ($currentUser && !$currentUser->relationLoaded('role')) {
            $currentUser->load('role');
        }
        
        if (!$currentUser->canManageUsers() && !$currentUser->isSalesHead()) {
            abort(403, 'Unauthorized action.');
        }

        $query = User::with(['role', 'manager', 'employeeProfile.exitWorkflow'])
            ->withCount([
                'activeAssignedLeads as active_assigned_leads_count'
            ]);

        // If Sales Head, show only team members
        if ($currentUser->isSalesHead() && !$currentUser->canManageUsers()) {
            $teamMemberIds = $currentUser->getAllTeamMemberIds();
            if (!empty($teamMemberIds)) {
                $query->whereIn('id', array_merge([$currentUser->id], $teamMemberIds));
            } else {
                // If no team members, show only the Sales Head
                $query->where('id', $currentUser->id);
            }
        }

        // Hide admin users from CRM view
        if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
            $query->whereHas('role', function($q) {
                $q->where('slug', '!=', Role::ADMIN);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->filled('login_method')) {
            $loginMethod = $request->input('login_method');

            if ($loginMethod === User::TWO_FACTOR_EMAIL) {
                $query->where(function ($otpQuery) {
                    $otpQuery->where('two_factor_mode', User::TWO_FACTOR_EMAIL)
                        ->orWhere(function ($defaultQuery) {
                            $defaultQuery->whereNull('two_factor_mode')
                                ->where(function ($roleQuery) {
                                    $roleQuery->whereHas('role', function ($roleBuilder) {
                                        $roleBuilder->whereIn('slug', [
                                            Role::ADMIN,
                                            Role::CRM,
                                            Role::HR_MANAGER,
                                            Role::JUNIOR_HR,
                                            Role::FINANCE_MANAGER,
                                            Role::LEAD_MANAGER,
                                        ]);
                                    })->orWhere(function ($salesHeadQuery) {
                                        $salesHeadQuery->whereHas('role', function ($roleBuilder) {
                                            $roleBuilder->where('slug', Role::SALES_MANAGER);
                                        })->whereNull('manager_id');
                                    });
                                });
                        });
                });
            }

            if ($loginMethod === User::TWO_FACTOR_OFF) {
                $query->where(function ($offQuery) {
                    $offQuery->where('two_factor_mode', User::TWO_FACTOR_OFF)
                        ->orWhere(function ($defaultQuery) {
                            $defaultQuery->whereNull('two_factor_mode')
                                ->where(function ($roleQuery) {
                                    $roleQuery->whereHas('role', function ($roleBuilder) {
                                        $roleBuilder->whereNotIn('slug', [
                                            Role::ADMIN,
                                            Role::CRM,
                                            Role::HR_MANAGER,
                                            Role::JUNIOR_HR,
                                            Role::FINANCE_MANAGER,
                                            Role::LEAD_MANAGER,
                                        ]);
                                    })->where(function ($nonHeadSalesManager) {
                                        $nonHeadSalesManager->whereDoesntHave('role', function ($roleBuilder) {
                                            $roleBuilder->where('slug', Role::SALES_MANAGER);
                                        })->orWhereNotNull('manager_id');
                                    });
                                });
                        });
                });
            }
        }

        $users = $query->latest()->paginate(15);
        $sessionCounts = $currentUser->isAdmin()
            ? app(AdminSessionService::class)->getUserSessionCountMap($users->getCollection()->pluck('id'))
            : [];

        $users->getCollection()->transform(function (User $user) use ($sessionCounts) {
            $user->active_session_count = $sessionCounts[$user->id] ?? 0;

            return $user;
        });

        $roles = Role::where('is_active', true)->get();

        // Hierarchy data (all active users with role + manager, for org chart)
        $hierarchyUsers = User::with(['role', 'manager'])
            ->where('is_active', true)
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'role'      => $u->role->name ?? 'Unknown',
                'role_slug' => $u->role->slug ?? '',
                'manager_id'=> $u->manager_id,
                'avatar'    => strtoupper(substr($u->name, 0, 1)),
            ])->values()->toArray();

        return view('users.index', compact('users', 'roles', 'hierarchyUsers'));
    }

    public function create()
    {
        $currentUser = request()->user();
        
        if (!$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        $roles = Role::where('is_active', true)->get();
        
        // Filter roles for CRM users - only allow Sales Executive, Assistant Sales Manager, Senior Manager
        if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
            $roles = $roles->filter(function($role) {
                return in_array($role->slug, [Role::SALES_EXECUTIVE, Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER]);
            });
        }
        
        $managers = $this->getManagerCandidates();

        return view('users.form', [
            'user' => null,
            'roles' => $roles,
            'managers' => $managers,
            'leadSourceOptions' => Lead::sourceOptions(),
            'teamNotificationEventOptions' => $this->teamNotificationEventOptions(),
            'canManageAdManagerAccess' => $currentUser->isAdmin(),
            'canCreateEmployeeProfile' => $currentUser->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'two_factor_mode' => ['nullable', Rule::in([User::TWO_FACTOR_OFF, User::TWO_FACTOR_EMAIL])],
            'otp_recovery_allowed' => 'nullable|boolean',
            'allowed_lead_sources' => ['nullable', 'array'],
            'allowed_lead_sources.*' => ['string', Rule::in(array_keys(Lead::sourceOptions()))],
            'can_raise_po_request' => ['nullable', 'boolean'],
            'team_notifications_enabled' => ['nullable', 'boolean'],
            'team_notification_events' => ['nullable', 'array'],
            'team_notification_events.*' => ['string', Rule::in(array_keys($this->teamNotificationEventOptions()))],
        ]);

        // Validate role_id - CRM users cannot create Admin or CRM users
        if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
            $role = Role::find($validated['role_id']);
            if ($role && !in_array($role->slug, [Role::SALES_EXECUTIVE, Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER], true)) {
                return redirect()->back()
                    ->withErrors(['role_id' => 'CRM users cannot create this role.'])
                    ->withInput();
            }
        }

        $this->validateManagerSelection($validated);

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated = $this->applyTwoFactorSettings($validated, $request, $currentUser);

        $selectedRole = Role::find($validated['role_id']);
        if ($currentUser->isAdmin()) {
            $validated['ui_preferences'] = $this->adManagerPreferencesPayload($request, $selectedRole?->slug);
        }
        $preferences = is_array($validated['ui_preferences'] ?? null) ? $validated['ui_preferences'] : [];
        $preferences['must_change_password'] = true;
        $preferences['password_change_requested_at'] = now()->toDateTimeString();
        $preferences['password_change_requested_by'] = $currentUser->id;
        unset($preferences['password_changed_at']);
        $validated['ui_preferences'] = $preferences;
        unset($validated['allowed_lead_sources'], $validated['can_raise_po_request'], $validated['team_notifications_enabled'], $validated['team_notification_events']);

        $access = app(\App\Services\LeadAuditorAccessService::class);
        $auditorIds = $access->validateSelection($request, null, $selectedRole?->slug);
        $user = DB::transaction(function () use ($validated, $access, $auditorIds, $request) {
            $user = User::create($validated);
            $access->sync($user, $auditorIds, $request);
            return $user;
        });
        if ($currentUser->isAdmin()) {
            $this->syncAdManagerPurchaseOrderAccess($user, $request, $selectedRole?->slug, $currentUser);
        }
        $this->logTwoFactorSettingsChange($currentUser, $user, true);
        $shouldOpenEmployeeProfile = $request->boolean('open_employee_profile') && $currentUser->isAdmin();

        if (filter_var(SystemSettings::get('send_welcome_email_to_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NewUserMailService::class)->sendWelcomeEmailIfEnabled($user, $plainPassword);
        }
        if (filter_var(SystemSettings::get('notify_admin_on_new_user', '1'), FILTER_VALIDATE_BOOLEAN)) {
            app(NotificationService::class)->notifyAdminsNewUser($user);
        }

        if ($shouldOpenEmployeeProfile) {
            return redirect()
                ->route('admin.hr.employees.create', ['user_id' => $user->id])
                ->with('success', 'User created. Continue with employee master details.');
        }

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->canManageUsers() && $currentUser->id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $user->load(['role', 'manager', 'teamMembers.role', 'advisorPublicProfile', 'employeeProfile']);
        $assignableTeamMembers = $currentUser->canManageUsers()
            ? $this->getAssignableTeamMemberCandidates($user)
            : collect();

        $canManageSessions = $currentUser->isAdmin();
        $userSessions = $canManageSessions
            ? app(AdminSessionService::class)->getUserSessions($user, request()->session()->getId())
            : collect();

        return view('users.show', compact('user', 'assignableTeamMembers', 'canManageSessions', 'userSessions'));
    }

    /**
     * Send credentials email to existing user (new temp password, then email).
     */
    public function sendCredentialsEmail(User $user)
    {
        $currentUser = request()->user();
        if (!$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $user->refresh();
            $user->load(['role', 'manager']);
            $tempPassword = Str::random(10);
            $user->update(['password' => Hash::make($tempPassword)]);
            $user->markPasswordChangeRequired($currentUser->id);
            app(NewUserMailService::class)->sendCredentialsEmail($user, $tempPassword);
            return redirect()->back()->with('success', 'Credentials email sent to ' . $user->email);
        } catch (\Exception $e) {
            Log::error('Send credentials email failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }

    /**
     * Send password reset OTP email to an existing user without changing their current password.
     */
    public function sendPasswordResetEmail(User $user)
    {
        $currentUser = request()->user();
        if (!$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $user->refresh();

            DB::table('password_reset_otps')->where('email', $user->email)->delete();

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $token = Str::random(64);

            DB::table('password_reset_otps')->insert([
                'email' => $user->email,
                'otp' => $otp,
                'token' => $token,
                'is_verified' => false,
                'expires_at' => Carbon::now()->addMinutes(10),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Mail::to($user->email)->send(new PasswordResetOtpMail($otp, $user->name));

            return redirect()->back()->with('success', 'Password reset email sent to ' . $user->email);
        } catch (\Exception $e) {
            Log::error('Send password reset email failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send password reset email: ' . $e->getMessage());
        }
    }

    public function updatePasswordByAdmin(Request $request, User $user)
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            DB::transaction(function () use ($user, $validated, $currentUser) {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                ])->save();
                $user->markPasswordChangeRequired($currentUser->id);
            });

            $deletedCount = $this->adminSessionService->revokeUserSessions(
                targetUser: $user,
                actor: $currentUser,
                preserveSessionId: $user->is($currentUser) ? $request->session()->getId() : null,
            );

            return redirect()
                ->back()
                ->with('success', "Password updated and {$deletedCount} active session(s) logged out for {$user->email}.");
        } catch (\Throwable $e) {
            Log::error('Admin password update failed: ' . $e->getMessage(), [
                'target_user_id' => $user->id,
                'actor_user_id' => $currentUser->id,
            ]);

            return redirect()->back()->with('error', 'Failed to update password: ' . $e->getMessage());
        }
    }

    public function attachTeamMember(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!$currentUser || !$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'member_id' => ['required', 'exists:users,id'],
        ]);

        $user->load('role');
        $member = User::with(['role', 'manager'])->findOrFail($validated['member_id']);

        if ($member->is($user)) {
            return back()->with('error', 'User apni hi team me add nahi ho sakta.');
        }

        if ($member->manager_id === $user->id) {
            return back()->with('success', $member->name . ' already team me hai.');
        }

        if (!$this->isTeamMemberRoleAllowedForManager($user, $member)) {
            return back()->withErrors([
                'member_id' => $member->getDisplayRoleName() . ' ko ' . $user->getDisplayRoleName() . ' ki team me add nahi kar sakte.',
            ]);
        }

        if ($member->isSeniorOf($user)) {
            return back()->withErrors([
                'member_id' => 'Hierarchy loop avoid karne ke liye senior user ko junior manager ke under add nahi kar sakte.',
            ]);
        }

        $oldManagerId = $member->manager_id;
        $oldManager = $member->manager?->name;

        $this->validateManagerSelection([
            'role_id' => $member->role_id,
            'manager_id' => $user->id,
        ], $member);

        $member->forceFill(['manager_id' => $user->id])->save();

        ActivityLog::create([
            'user_id' => $currentUser->id,
            'action' => 'team_member_attached',
            'model_type' => User::class,
            'model_id' => $member->id,
            'description' => "{$member->name} ko {$user->name} ki team me add kiya.",
            'old_values' => ['manager_id' => $oldManagerId, 'manager_name' => $oldManager],
            'new_values' => ['manager_id' => $user->id, 'manager_name' => $user->name],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('success', $member->name . ' team me add ho gaya.');
    }

    public function detachTeamMember(Request $request, User $user, User $member)
    {
        $currentUser = $request->user();

        if (!$currentUser || !$currentUser->canManageUsers()) {
            abort(403, 'Unauthorized action.');
        }

        if ($member->manager_id !== $user->id) {
            return back()->with('error', $member->name . ' is manager ki direct team me nahi hai.');
        }

        $member->loadMissing('role');
        $oldManagerId = $member->manager_id;

        $member->forceFill(['manager_id' => null])->save();

        ActivityLog::create([
            'user_id' => $currentUser->id,
            'action' => 'team_member_detached',
            'model_type' => User::class,
            'model_id' => $member->id,
            'description' => "{$member->name} ko {$user->name} ki team se remove kiya.",
            'old_values' => ['manager_id' => $oldManagerId, 'manager_name' => $user->name],
            'new_values' => ['manager_id' => null, 'manager_name' => null],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('success', $member->name . ' team se remove ho gaya.');
    }

    public function toggleTwoFactorMode(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!$currentUser || !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $oldTwoFactor = [
            'two_factor_mode' => $user->resolveTwoFactorMode(),
            'two_factor_enforced_by_admin' => (bool) $user->two_factor_enforced_by_admin,
            'otp_recovery_allowed' => (bool) $user->otp_recovery_allowed,
        ];

        $nextMode = $user->resolveTwoFactorMode() === User::TWO_FACTOR_EMAIL
            ? User::TWO_FACTOR_OFF
            : User::TWO_FACTOR_EMAIL;

        $user->forceFill([
            'two_factor_mode' => $nextMode,
            'two_factor_enforced_by_admin' => true,
        ])->save();

        $user->refresh();
        $this->logTwoFactorSettingsChange($currentUser, $user, false, $oldTwoFactor);

        return back()->with('success', $user->name . ' login method updated to ' . ($nextMode === User::TWO_FACTOR_EMAIL ? 'Email OTP' : 'Password Login') . '.');
    }

    public function edit(User $user)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->canManageUsers() && $currentUser->id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $roles = Role::where('is_active', true)->get();
        
        // Filter roles for CRM users - only allow Sales Executive, Assistant Sales Manager, Senior Manager
        if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
            $roles = $roles->filter(function($role) {
                return in_array($role->slug, [Role::SALES_EXECUTIVE, Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER]);
            });
        }
        
        $managers = $this->getManagerCandidates($user);

        return view('users.form', [
            'user' => $user,
            'roles' => $roles,
            'managers' => $managers,
            'leadSourceOptions' => Lead::sourceOptions(),
            'teamNotificationEventOptions' => $this->teamNotificationEventOptions(),
            'canManageAdManagerAccess' => $currentUser->isAdmin(),
            'canCreateEmployeeProfile' => $currentUser->isAdmin(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->canManageUsers() && $currentUser->id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'current_password' => 'sometimes|required_with:password',
            'two_factor_mode' => ['nullable', Rule::in([User::TWO_FACTOR_OFF, User::TWO_FACTOR_EMAIL])],
            'otp_recovery_allowed' => 'nullable|boolean',
            'allowed_lead_sources' => ['nullable', 'array'],
            'allowed_lead_sources.*' => ['string', Rule::in(array_keys(Lead::sourceOptions()))],
            'can_raise_po_request' => ['nullable', 'boolean'],
            'team_notifications_enabled' => ['nullable', 'boolean'],
            'team_notification_events' => ['nullable', 'array'],
            'team_notification_events.*' => ['string', Rule::in(array_keys($this->teamNotificationEventOptions()))],
        ]);

        // Handle password change with current password verification
        if (isset($validated['password']) && !empty($validated['password'])) {
            if (isset($validated['current_password'])) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json(['message' => 'Current password is incorrect'], 422);
                    }
                    return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
                }
            }
            $validated['password'] = Hash::make($validated['password']);
            $passwordWasChanged = true;
            unset($validated['current_password']);
        } else {
            $passwordWasChanged = false;
            unset($validated['password'], $validated['current_password']);
        }
        
        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            // Delete old profile picture if exists
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            
            // Store new profile picture
            $path = $request->file('profile_picture')->store('profile-pictures', 'public');
            $validated['profile_picture'] = $path;
        } else {
            unset($validated['profile_picture']);
        }

        // Only Admin or CRM can change roles
        if (isset($validated['role_id'])) {
            if (!$currentUser->canManageUsers()) {
                unset($validated['role_id']);
            } else {
                // CRM users cannot assign Admin or CRM roles
                if ($currentUser->isCrm() && !$currentUser->isAdmin()) {
                    $role = Role::find($validated['role_id']);
                    if ($role && !in_array($role->slug, [Role::SALES_EXECUTIVE, Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER], true)) {
                        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
                            return response()->json(['message' => 'CRM users cannot assign this role'], 403);
                        }
                        return redirect()->back()
                            ->withErrors(['role_id' => 'CRM users cannot assign this role.'])
                            ->withInput();
                    }
                }
                // Log role change for audit
                if ($user->role_id != $validated['role_id']) {
                    $oldRole = $user->role->name ?? 'Unknown';
                    $newRole = Role::find($validated['role_id'])->name ?? 'Unknown';
                    Log::info("Role changed for user {$user->id} ({$user->name}): {$oldRole} -> {$newRole}", [
                        'changed_by' => $currentUser->id,
                        'changed_by_name' => $currentUser->name,
                    ]);
                }
            }
        }

        $this->validateManagerSelection($validated, $user);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = $request->has('is_active') ? true : false;
        }

        $oldTwoFactor = [
            'two_factor_mode' => $user->resolveTwoFactorMode(),
            'two_factor_enforced_by_admin' => (bool) $user->two_factor_enforced_by_admin,
            'otp_recovery_allowed' => (bool) $user->otp_recovery_allowed,
        ];

        $validated = $this->applyTwoFactorSettings($validated, $request, $currentUser, $user);

        $selectedRole = isset($validated['role_id'])
            ? Role::find($validated['role_id'])
            : $user->role;
        if ($currentUser->isAdmin()) {
            $validated['ui_preferences'] = $this->adManagerPreferencesPayload($request, $selectedRole?->slug, $user);
        }
        unset($validated['allowed_lead_sources'], $validated['can_raise_po_request'], $validated['team_notifications_enabled'], $validated['team_notification_events']);

        $access = app(\App\Services\LeadAuditorAccessService::class);
        $auditorIds = $access->validateSelection($request, $user, $selectedRole?->slug);
        DB::transaction(function () use ($user, $validated, $access, $auditorIds, $request) {
            $user->update($validated);
            $access->sync($user, $auditorIds, $request);
        });
        if ($passwordWasChanged && $currentUser->id === $user->id) {
            $user->clearPasswordChangeRequirement();
        } elseif ($passwordWasChanged && $currentUser->canManageUsers()) {
            $user->markPasswordChangeRequired($currentUser->id);
        }
        $user->refresh();
        if ($currentUser->isAdmin()) {
            $this->syncAdManagerPurchaseOrderAccess($user, $request, $selectedRole?->slug, $currentUser);
        }
        $this->logTwoFactorSettingsChange($currentUser, $user, false, $oldTwoFactor);

        // Return JSON for AJAX requests
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                ]
            ]);
        }

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function toggleLoginAccess(Request $request, User $user)
    {
        $actor = $request->user();

        if (!$actor->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        if ($actor->is($user) && $user->is_active) {
            return redirect()->route('users.index')
                ->with('error', 'Apna login yahan se disable nahi kar sakte.');
        }

        $shouldEnable = !$user->is_active;
        $revokedSessionCount = 0;

        DB::transaction(function () use ($user, $actor, $shouldEnable, &$revokedSessionCount) {
            $user->forceFill([
                'is_active' => $shouldEnable,
            ])->save();

            if (!$shouldEnable) {
                $revokedSessionCount = $this->adminSessionService->revokeUserSessions($user, $actor);
                $user->tokens()->delete();
                $user->forceFill([
                    'remember_token' => Str::random(60),
                ])->save();
            }
        });

        $message = $shouldEnable
            ? "{$user->name} ka login enabled ho gaya."
            : "{$user->name} ka login disabled ho gaya. {$revokedSessionCount} active session(s) revoke kiye gaye.";

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $shouldEnable ? 'user_login_enabled' : 'user_login_disabled',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => $message,
            'old_values' => [
                'is_active' => !$shouldEnable,
            ],
            'new_values' => [
                'is_active' => $shouldEnable,
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'revoked_session_count' => $revokedSessionCount,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->route('users.index')->with('success', $message);
    }

    public function destroy(User $user)
    {
        $currentUser = request()->user();

        $guardRedirect = $this->guardUserDeletion($currentUser, $user);
        if ($guardRedirect) {
            return $guardRedirect;
        }

        $activeLeadCount = count($this->userDeletionTransferService->getActiveLeadIds($user));
        if ($activeLeadCount > 0) {
            return redirect()->route('users.transfer-delete', $user)
                ->with('error', 'Transfer active leads before deleting this user.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function showTransferDelete(User $user)
    {
        $currentUser = request()->user();

        $guardRedirect = $this->guardUserDeletion($currentUser, $user);
        if ($guardRedirect) {
            return $guardRedirect;
        }

        $transferPreview = $this->userDeletionTransferService->getTransferPreview($user);
        $replacementUsers = $this->userDeletionTransferService->getEligibleReplacementUsers($user);

        if ($transferPreview['active_lead_count'] === 0) {
            return redirect()->route('users.index')
                ->with('info', 'This user has no active leads. You can delete the user directly.');
        }

        return view('users.transfer-delete', [
            'userToDelete' => $user->load(['role', 'manager']),
            'replacementUsers' => $replacementUsers,
            'transferPreview' => $transferPreview,
        ]);
    }

    public function transferDelete(Request $request, User $user)
    {
        $currentUser = $request->user();

        $guardRedirect = $this->guardUserDeletion($currentUser, $user);
        if ($guardRedirect) {
            return $guardRedirect;
        }

        $activeLeadIds = $this->userDeletionTransferService->getActiveLeadIds($user);
        if (empty($activeLeadIds)) {
            return redirect()->route('users.index')
                ->with('info', 'This user no longer has active leads. Delete the user directly if needed.');
        }

        $eligibleReplacementUserIds = $this->userDeletionTransferService
            ->getEligibleReplacementUsers($user)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'replacement_user_id' => ['required', Rule::in($eligibleReplacementUserIds)],
        ]);

        $replacementUser = User::findOrFail($validated['replacement_user_id']);

        try {
            $results = $this->userDeletionTransferService->transferAndDelete(
                $user,
                $replacementUser,
                $currentUser->id
            );
        } catch (\Throwable $e) {
            Log::error('User delete transfer failed: ' . $e->getMessage(), [
                'user_to_delete' => $user->id,
                'replacement_user_id' => $replacementUser->id,
                'performed_by' => $currentUser->id,
            ]);

            return redirect()->route('users.transfer-delete', $user)
                ->with('error', 'Transfer failed. User was not deleted. ' . $e->getMessage());
        }

        return redirect()->route('users.index')->with(
            'success',
            "Transferred {$results['transferred_leads']} active leads to {$replacementUser->name} and deleted {$user->name}."
        );
    }

    protected function guardUserDeletion(User $currentUser, User $user)
    {
        if (!$currentUser->isAdmin()) {
            abort(403, 'Only administrators can delete users.');
        }

        if ($currentUser->id === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete your own account.');
        }

        return null;
    }

    protected function getManagerCandidates(?User $excludeUser = null)
    {
        return User::where('is_active', true)
            ->when($excludeUser, fn ($query) => $query->where('id', '!=', $excludeUser->id))
            ->whereHas('role', function($q) {
                $q->whereIn('slug', [
                    Role::ADMIN,
                    Role::CRM,
                    Role::MARKETING_MANAGER,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                ]);
            })
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    protected function getAssignableTeamMemberCandidates(User $manager)
    {
        $manager->loadMissing('role');

        return User::query()
            ->with(['role', 'manager'])
            ->where('is_active', true)
            ->where('id', '!=', $manager->id)
            ->where(function ($query) use ($manager) {
                $query->whereNull('manager_id')
                    ->orWhere('manager_id', '!=', $manager->id);
            })
            ->whereHas('role')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate) => $this->isTeamMemberRoleAllowedForManager($manager, $candidate))
            ->reject(fn (User $candidate) => $candidate->isSeniorOf($manager))
            ->values();
    }

    protected function isTeamMemberRoleAllowedForManager(User $manager, User $member): bool
    {
        $managerSlug = $manager->role?->slug;
        $memberSlug = $member->role?->slug;

        if (!$managerSlug || !$memberSlug) {
            return false;
        }

        $allowedRoleMap = [
            Role::ADMIN => [
                Role::CRM,
                Role::HR_MANAGER,
                Role::JUNIOR_HR,
                Role::FINANCE_MANAGER,
                Role::LEAD_MANAGER,
                Role::MARKETING_MANAGER,
                Role::MARKETING_EXECUTIVE,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ],
            Role::CRM => [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ],
            Role::MARKETING_MANAGER => [
                Role::MARKETING_EXECUTIVE,
            ],
            Role::SALES_MANAGER => [
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
            ],
            Role::SENIOR_MANAGER => [
                Role::ASSISTANT_SALES_MANAGER,
            ],
            Role::ASSISTANT_SALES_MANAGER => [
                Role::SALES_EXECUTIVE,
            ],
        ];

        return in_array($memberSlug, $allowedRoleMap[$managerSlug] ?? [], true);
    }

    protected function validateManagerSelection(array $validated, ?User $user = null): void
    {
        $selectedRoleId = $validated['role_id'] ?? $user?->role_id;
        $selectedManagerId = $validated['manager_id'] ?? $user?->manager_id;

        if (!$selectedRoleId || !$selectedManagerId) {
            return;
        }

        $selectedRole = Role::find($selectedRoleId);
        if (!$selectedRole || $selectedRole->slug !== Role::ASSISTANT_SALES_MANAGER) {
            return;
        }

        $manager = User::with('role')->find($selectedManagerId);
        $managerSlug = $manager?->role?->slug;

        if (!$managerSlug || !in_array($managerSlug, self::ASM_ALLOWED_MANAGER_SLUGS, true)) {
            throw ValidationException::withMessages([
                'manager_id' => 'Assistant Sales Manager can only report to Senior Manager, Sales Manager, Sales Head, CRM, or Admin.',
            ]);
        }
    }

    protected function applyTwoFactorSettings(array $validated, Request $request, User $currentUser, ?User $user = null): array
    {
        $selectedRoleId = $validated['role_id'] ?? $user?->role_id;
        $selectedRole = $selectedRoleId ? Role::find($selectedRoleId) : $user?->role;
        $defaultMode = User::defaultTwoFactorModeForRoleSlug($selectedRole?->slug);

        if ($selectedRole && $selectedRole->slug === Role::SALES_MANAGER && !($validated['manager_id'] ?? $user?->manager_id)) {
            $defaultMode = User::TWO_FACTOR_EMAIL;
        }

        $canManageOtp = $currentUser->canManageUsers();

        if (!$canManageOtp) {
            unset($validated['two_factor_mode'], $validated['otp_recovery_allowed']);

            return $validated;
        }

        $validated['two_factor_mode'] = $validated['two_factor_mode'] ?? $defaultMode;
        $validated['two_factor_enforced_by_admin'] = true;
        $validated['otp_recovery_allowed'] = $request->boolean('otp_recovery_allowed', false);

        return $validated;
    }

    protected function adManagerPreferencesPayload(Request $request, ?string $roleSlug, ?User $user = null): array
    {
        $preferences = is_array($user?->ui_preferences) ? $user->ui_preferences : [];

        $preferences['team_notifications'] = $this->teamNotificationPreferencesPayload($request);

        if ($roleSlug !== Role::AD_MANAGER) {
            unset($preferences['allowed_lead_sources']);

            return $preferences;
        }

        $validSources = array_keys(Lead::sourceOptions());
        $preferences['allowed_lead_sources'] = collect($request->input('allowed_lead_sources', []))
            ->map(fn ($source) => Lead::normalizeSource((string) $source))
            ->filter(fn ($source) => in_array($source, $validSources, true))
            ->unique()
            ->values()
            ->all();

        return $preferences;
    }

    protected function teamNotificationPreferencesPayload(Request $request): array
    {
        $validEvents = array_keys($this->teamNotificationEventOptions());
        $enabled = $request->boolean('team_notifications_enabled', false);

        return [
            'enabled' => $enabled,
            'events' => $enabled
                ? collect($request->input('team_notification_events', []))
                    ->map(fn ($event) => (string) $event)
                    ->filter(fn ($event) => in_array($event, $validEvents, true))
                    ->unique()
                    ->values()
                    ->all()
                : [],
        ];
    }

    protected function teamNotificationEventOptions(): array
    {
        return [
            'lead_created' => 'New lead created/assigned in team',
            'followup_created' => 'Team follow-up created',
            'followup_overdue' => 'Team follow-up missed/overdue',
            'site_visit_created' => 'Team site visit created',
            'site_visit_overdue' => 'Team site visit missed/outcome pending',
            'meeting_created' => 'Team meeting created',
            'meeting_overdue' => 'Team meeting missed/outcome pending',
            'task_overdue' => 'Team call/task missed/overdue',
        ];
    }

    protected function syncAdManagerPurchaseOrderAccess(User $user, Request $request, ?string $roleSlug, User $actor): void
    {
        if ($roleSlug !== Role::AD_MANAGER) {
            return;
        }

        if ($request->boolean('can_raise_po_request')) {
            PurchaseOrderAccess::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'can_raise_need_purchase' => true,
                    'can_raise_reimbursement' => false,
                    'can_mark_payment_done' => false,
                    'payment_limit' => null,
                    'is_active' => true,
                    'updated_by' => $actor->id,
                ]
            );

            return;
        }

        PurchaseOrderAccess::query()
            ->where('user_id', $user->id)
            ->update([
                'can_raise_need_purchase' => false,
                'can_raise_reimbursement' => false,
                'can_mark_payment_done' => false,
                'is_active' => false,
                'updated_by' => $actor->id,
            ]);
    }

    protected function logTwoFactorSettingsChange(User $actor, User $target, bool $created, ?array $oldValues = null): void
    {
        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $created ? 'user_otp_login_created' : 'user_otp_login_updated',
            'model_type' => User::class,
            'model_id' => $target->id,
            'description' => ($created ? 'Created' : 'Updated') . ' user login method settings.',
            'old_values' => $oldValues,
            'new_values' => [
                'two_factor_mode' => $target->resolveTwoFactorMode(),
                'two_factor_enforced_by_admin' => (bool) $target->two_factor_enforced_by_admin,
                'otp_recovery_allowed' => (bool) $target->otp_recovery_allowed,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
