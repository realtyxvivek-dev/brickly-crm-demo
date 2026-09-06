<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const TWO_FACTOR_OFF = 'off';
    public const TWO_FACTOR_EMAIL = 'otp_email';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_picture',
        'role_id',
        'manager_id',
        'is_active',
        'two_factor_mode',
        'two_factor_enforced_by_admin',
        'otp_recovery_allowed',
        'ui_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'two_factor_enforced_by_admin' => 'boolean',
        'otp_recovery_allowed' => 'boolean',
        'ui_preferences' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function mobileAppInstallation(): HasOne
    {
        return $this->hasOne(MobileAppInstallation::class)->where('platform', 'android');
    }

    public function phonePrivacySetting(): HasOne
    {
        return $this->hasOne(UserPhonePrivacySetting::class);
    }

    public function latestMobileAppDiagnostic(): HasOne
    {
        return $this->hasOne(MobileAppDiagnostic::class)->latestOfMany('reported_at');
    }

    public function purchaseOrderAccess(): HasOne
    {
        return $this->hasOne(PurchaseOrderAccess::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'assigned_to');
    }

    public function activeAssignedLeads(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'assigned_to')
            ->activeWithLiveLead();
    }

    public function leadFavorites(): HasMany
    {
        return $this->hasMany(LeadFavorite::class);
    }

    public function favoriteLeads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_favorites')
            ->withTimestamps();
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class, 'assigned_to');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function otpLoginRequests(): HasMany
    {
        return $this->hasMany(OtpLoginRequest::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'user_id');
    }

    public function knowledgeBaseAssignments(): HasMany
    {
        return $this->hasMany(KnowledgeBaseAssignment::class);
    }

    public function knowledgeBaseProgress(): HasMany
    {
        return $this->hasMany(KnowledgeBaseProgress::class);
    }

    public function knowledgeBasePathAssignments(): HasMany
    {
        return $this->hasMany(KnowledgeBasePathAssignment::class);
    }

    // Permission checks
    public function isAdmin(): bool
    {
        // Ensure role relationship is loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        return $this->role && $this->role->slug === Role::ADMIN;
    }

    public function isCrm(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::CRM;
    }

    public function isSalesManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::SALES_MANAGER;
    }

    /**
     * Check if user is Sales Head (Senior Manager with no manager or top-level manager)
     */
    public function isSalesHead(): bool
    {
        // Ensure role is loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        if (!$this->isSalesManager()) {
            return false;
        }
        
        // Sales Head is a Senior Manager with no manager (manager_id is null)
        return $this->manager_id === null;
    }

    /**
     * Get all team members including nested teams (for Sales Head)
     */
    public function getAllTeamMembers(): \Illuminate\Support\Collection
    {
        $allMembers = collect();
        
        // Get direct team members
        $directMembers = $this->teamMembers()->with('role')->get();
        $allMembers = $allMembers->merge($directMembers);
        
        // Recursively get nested team members
        foreach ($directMembers as $member) {
            if ($member->isSalesManager() || $member->isAssistantSalesManager() || $member->isSeniorManager()) {
                $nestedMembers = $member->getAllTeamMembers();
                $allMembers = $allMembers->merge($nestedMembers);
            }
        }
        
        return $allMembers->unique('id');
    }

    /**
     * Get all team member IDs including nested teams (for Sales Head)
     */
    public function getAllTeamMemberIds(): array
    {
        return $this->getAllTeamMembers()->pluck('id')->toArray();
    }

    /**
     * Check if this user is a senior (in the hierarchy chain) of another user
     * A user is considered senior if they are:
     * - The direct manager of the user
     * - A Sales Head and the user is in their team (directly or indirectly)
     * - In the manager chain above the user
     */
    public function isSeniorOf(User $otherUser): bool
    {
        // If other user has no manager, they are Sales Head - only Sales Head can verify Sales Head's meetings
        if (!$otherUser->manager_id) {
            return $this->isSalesHead() && $this->id === $otherUser->id;
        }
        
        // Check if current user is the direct manager
        if ($otherUser->manager_id === $this->id) {
            return true;
        }
        
        // Check if current user is Sales Head and other user is in their team
        if ($this->isSalesHead()) {
            $teamMemberIds = $this->getAllTeamMemberIds();
            return in_array($otherUser->id, $teamMemberIds);
        }
        
        // Recursively check manager chain
        $manager = $otherUser->manager;
        while ($manager) {
            if ($manager->id === $this->id) {
                return true;
            }
            $manager = $manager->manager;
        }
        
        return false;
    }

    /**
     * Check if user is Sales Executive (previously Telecaller)
     */
    public function isSalesExecutive(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::SALES_EXECUTIVE;
    }

    /**
     * Check if user is Assistant Sales Manager (previously Sales Executive)
     */
    public function isAssistantSalesManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::ASSISTANT_SALES_MANAGER;
    }

    public function canManageKnowledgeBase(): bool
    {
        return $this->isAdmin() || $this->isCrm();
    }

    public function canViewKnowledgeBaseReports(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return in_array(optional($this->role)->slug, [
            Role::ADMIN,
            Role::CRM,
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
        ], true);
    }

    /**
     * Check if user is Manager (role slug senior_manager)
     */
    public function isSeniorManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::SENIOR_MANAGER;
    }

    /**
     * Check if user is HR Manager
     */
    public function isHrManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::HR_MANAGER;
    }

    public function isJuniorHr(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::JUNIOR_HR;
    }

    public function isHrUser(): bool
    {
        return $this->isHrManager() || $this->isJuniorHr();
    }

    /**
     * Check if user is Finance Manager
     */
    public function isFinanceManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && $this->role->slug === Role::FINANCE_MANAGER;
    }

    public function isAdManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::AD_MANAGER;
    }

    public function allowedLeadSources(): array
    {
        $preferences = is_array($this->ui_preferences) ? $this->ui_preferences : [];
        $sources = $preferences['allowed_lead_sources'] ?? [];

        if (!is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->map(fn ($source) => \App\Models\Lead::normalizeSource((string) $source))
            ->filter(fn ($source) => is_string($source) && $source !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function teamNotificationPreferences(): array
    {
        $preferences = is_array($this->ui_preferences) ? $this->ui_preferences : [];
        $teamPreferences = $preferences['team_notifications'] ?? [];

        if (!is_array($teamPreferences)) {
            return [
                'enabled' => false,
                'events' => [],
            ];
        }

        $events = $teamPreferences['events'] ?? [];

        return [
            'enabled' => ($teamPreferences['enabled'] ?? false) === true,
            'events' => is_array($events)
                ? collect($events)->map(fn ($event) => (string) $event)->unique()->values()->all()
                : [],
        ];
    }

    public function wantsTeamNotification(string $event): bool
    {
        $preferences = $this->teamNotificationPreferences();

        return $preferences['enabled'] === true
            && in_array($event, $preferences['events'], true);
    }

    public function canAccessLeadSource(?string $source): bool
    {
        if (!$this->isAdManager()) {
            return true;
        }

        $source = \App\Models\Lead::normalizeSource((string) $source);

        return $source !== '' && in_array($source, $this->allowedLeadSources(), true);
    }

    public function canManageMetaOps(): bool
    {
        return $this->isAdmin() || $this->isCrm() || $this->isAdManager();
    }

    public function canManageMetaLeadAds(): bool
    {
        return $this->canManageMetaOps();
    }

    public function canManageMetaAutomation(): bool
    {
        return $this->canManageMetaOps();
    }

    public function mustChangePassword(): bool
    {
        $preferences = is_array($this->ui_preferences) ? $this->ui_preferences : [];

        return ($preferences['must_change_password'] ?? false) === true;
    }

    public function markPasswordChangeRequired(?int $requestedBy = null): void
    {
        $preferences = is_array($this->ui_preferences) ? $this->ui_preferences : [];
        $preferences['must_change_password'] = true;
        $preferences['password_change_requested_at'] = now()->toDateTimeString();
        $preferences['password_change_requested_by'] = $requestedBy;
        unset($preferences['password_changed_at']);

        $this->forceFill(['ui_preferences' => $preferences])->save();
    }

    public function clearPasswordChangeRequirement(): void
    {
        $preferences = is_array($this->ui_preferences) ? $this->ui_preferences : [];
        $preferences['must_change_password'] = false;
        $preferences['password_changed_at'] = now()->toDateTimeString();

        $this->forceFill(['ui_preferences' => $preferences])->save();
    }

    public function isLeadManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::LEAD_MANAGER;
    }

    public function isLeadQualityAuditor(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::LEAD_QUALITY_AUDITOR;
    }

    public function isMarketingManager(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::MARKETING_MANAGER;
    }

    public function isMarketingExecutive(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::MARKETING_EXECUTIVE;
    }

    public function isMarketingUser(): bool
    {
        return $this->isMarketingManager() || $this->isMarketingExecutive();
    }

    /**
     * Check if user is Telecaller (either Telecaller or Sales Executive role).
     * Both roles get telecaller token and same behaviour for tasks/leads/verification/profile.
     */
    public function isTelecaller(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && in_array($this->role->slug, [Role::TELECALLER, Role::SALES_EXECUTIVE], true);
    }

    public function isDedicatedTelecaller(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->slug === Role::TELECALLER;
    }

    public function hasRolePermission(string $permission): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        if ($this->isAdmin()) {
            return true;
        }

        $permissions = $this->role?->permissions ?? [];

        return in_array($permission, $permissions, true);
    }

    public function canUseCallingCenter(string $permission = 'calling_center.view'): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->hasRolePermission($permission)) {
            return true;
        }

        if ($this->isDedicatedTelecaller()) {
            return in_array($permission, [
                'calling_center.view',
                'calling_center.agent_queue',
                'calling_center.submit_outcome',
                'calling_center.pause_own_queue',
                'calling_center.view_own_report',
                'calling_center.view_recordings',
            ], true);
        }

        return false;
    }

    /**
     * Get display role name for the user
     * Returns "Associate Director" for Sales Head, otherwise returns role name
     */
    public function getDisplayRoleName(): string
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        // Sales Head (Senior Manager with no manager) displays as "Associate Director"
        if ($this->isSalesHead()) {
            return 'Associate Director';
        }
        
        return $this->role ? $this->role->name : 'Unknown';
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin() || $this->isCrm() || $this->isSalesHead();
    }

    public function canUseAdvisorPublicProfile(): bool
    {
        if (strcasecmp((string) $this->email, 'test@gmail.com') === 0) {
            return true;
        }

        return $this->isAssistantSalesManager() || $this->isSeniorManager();
    }

    public function canUseSelfTodoPilot(): bool
    {
        return true;
    }

    public function canViewAllLeads(): bool
    {
        return in_array($this->role->slug, [Role::ADMIN, Role::CRM, Role::SALES_MANAGER]) || $this->isSalesHead();
    }

    public function canAssignLeads(): bool
    {
        return in_array($this->role->slug, [
            Role::ADMIN,
            Role::CRM,
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
        ]) || $this->isSalesHead();
    }

    public function canViewExecutionDeskTeamAll(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return in_array($this->role->slug ?? null, config('execution-desk.elevated_roles', []), true);
    }

    public function canManageLeadBankQueue(): bool
    {
        return $this->isAdmin() || $this->isCrm() || $this->isLeadManager();
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function telecallerProfile(): HasOne
    {
        return $this->hasOne(TelecallerProfile::class);
    }

    public function advisorPublicProfile(): HasOne
    {
        return $this->hasOne(AdvisorPublicProfile::class);
    }

    public function telecallerDailyLimit(): HasOne
    {
        return $this->hasOne(TelecallerDailyLimit::class);
    }

    public function salesManagerProfile(): HasOne
    {
        return $this->hasOne(SalesManagerProfile::class);
    }

    public function attendanceProfile(): HasOne
    {
        return $this->hasOne(UserAttendanceProfile::class);
    }

    public function salaryProfile(): HasOne
    {
        return $this->hasOne(UserSalaryProfile::class)->latestOfMany('effective_from');
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function incentives(): HasMany
    {
        return $this->hasMany(Incentive::class);
    }

    public function hasAttendanceRolloutEnabled(?CarbonInterface $date = null): bool
    {
        $date ??= now();

        return $this->attendanceProfile()
            ->where('attendance_enabled', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->exists();
    }

    public function crmAssignments(): HasMany
    {
        return $this->hasMany(CrmAssignment::class, 'assigned_to');
    }

    public function createdProspects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'created_by');
    }

    public function managedProspects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'assigned_manager');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    /**
     * Get the full URL for the profile picture
     */
    public function getProfilePictureUrlAttribute(): ?string
    {
        if (!$this->profile_picture) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->profile_picture, FILTER_VALIDATE_URL)) {
            return $this->profile_picture;
        }

        $path = str_replace('\\', '/', (string) $this->profile_picture);
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            return '/' . $path;
        }

        // Root-relative so it works with any host/port (e.g. artisan serve --port=8032)
        return '/storage/' . $path;
    }

    public static function defaultTwoFactorModeForRoleSlug(?string $roleSlug): string
    {
        return in_array($roleSlug, [
            Role::ADMIN,
            Role::CRM,
            Role::HR_MANAGER,
            Role::JUNIOR_HR,
            Role::FINANCE_MANAGER,
            Role::AD_MANAGER,
            Role::LEAD_MANAGER,
        ], true) ? self::TWO_FACTOR_EMAIL : self::TWO_FACTOR_OFF;
    }

    public function resolveTwoFactorMode(): string
    {
        if (in_array($this->two_factor_mode, [self::TWO_FACTOR_OFF, self::TWO_FACTOR_EMAIL], true)) {
            return $this->two_factor_mode;
        }

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        $roleSlug = $this->role->slug ?? null;

        if ($this->isSalesHead()) {
            return self::TWO_FACTOR_EMAIL;
        }

        return self::defaultTwoFactorModeForRoleSlug($roleSlug);
    }

    public function usesEmailOtpLogin(): bool
    {
        return $this->resolveTwoFactorMode() === self::TWO_FACTOR_EMAIL;
    }
}
