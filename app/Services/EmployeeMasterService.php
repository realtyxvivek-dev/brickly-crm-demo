<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\EmployeeAsset;
use App\Models\EmployeeAssetLog;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTimelineEvent;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeMasterService
{
    private const EMPLOYEE_CODE_PREFIXES = [
        Role::ADMIN => 'ADM',
        Role::CRM => 'CRM',
        Role::HR_MANAGER => 'HR',
        Role::FINANCE_MANAGER => 'FIN',
        Role::SALES_MANAGER => 'SM',
        Role::SENIOR_MANAGER => 'SRM',
        Role::ASSISTANT_SALES_MANAGER => 'ASM',
        Role::SALES_EXECUTIVE => 'EXE',
    ];

    public function upsertEmployee(array $validated, User $actor, ?User $existingUser = null): User
    {
        $user = $this->resolveUser($validated, $existingUser);
        $department = $this->resolveDepartment($validated);
        $designation = $this->resolveDesignation($validated);
        $employmentStatus = $validated['employment_status'];
        $loginEnabled = (bool) ($validated['login_enabled'] ?? false);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role_id' => $validated['role_id'],
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active' => $loginEnabled,
        ];

        if (!$user->exists) {
            $userData['password'] = Hash::make($validated['password'] ?? Str::password(16));
            $user = User::create($userData);
        } else {
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);
        }

        $profileData = [
            'employee_code' => $validated['employee_code'] ?: $this->resolveEmployeeCode($user),
            'department_id' => $department?->id,
            'designation_id' => $designation?->id,
            'joining_date' => $validated['joining_date'],
            'employment_status' => $employmentStatus,
            'employment_status_changed_at' => now(),
            'probation_end_date' => $validated['probation_end_date'] ?? null,
            'salary_day_of_month' => $validated['salary_day_of_month'] ?? 1,
            'account_holder_name' => $validated['account_holder_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'ifsc_code' => $validated['ifsc_code'],
            'pan_number' => $validated['pan_number'],
            'aadhaar_number' => $validated['aadhaar_number'],
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'current_address' => $validated['current_address'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $profile = EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        $this->syncAttendanceEmployeeCode($user, $profile->employee_code);
        $this->recordProfileTimeline($profile->fresh(['department', 'designation', 'user.role']), $actor, $existingUser !== null);

        return $user->fresh([
            'role',
            'manager',
            'attendanceProfile',
            'salaryProfile.salaryStructure',
            'employeeProfile.department',
            'employeeProfile.designation',
        ]);
    }

    public function addDocument(User $employee, array $validated, ?UploadedFile $file, User $actor): EmployeeDocument
    {
        $profile = $employee->employeeProfile;
        if (!$profile) {
            throw new \RuntimeException('Employee profile is required before documents can be added.');
        }

        $path = $file?->store('employee-documents/' . $profile->employee_code, 'local');

        $document = $profile->documents()->create([
            'document_type' => $validated['document_type'],
            'document_label' => $validated['document_label'],
            'document_number' => $validated['document_number'] ?? null,
            'file_path' => $path,
            'notes' => $validated['notes'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'uploaded_by' => $actor->id,
        ]);

        $this->recordTimeline($profile, $actor, 'document_added', 'Document added', $document->document_label);

        return $document;
    }

    public function deleteDocument(EmployeeDocument $document, User $actor): void
    {
        $profile = $document->employeeProfile;

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $label = $document->document_label;
        $document->delete();
        $this->recordTimeline($profile, $actor, 'document_removed', 'Document removed', $label);
    }

    public function addAsset(User $employee, array $validated, ?UploadedFile $file, User $actor): EmployeeAsset
    {
        $profile = $employee->employeeProfile;
        if (!$profile) {
            throw new \RuntimeException('Employee profile is required before assets can be added.');
        }

        $path = $file?->store('employee-assets/' . $profile->employee_code, 'local');

        $asset = $profile->assets()->create([
            'asset_type' => $validated['asset_type'],
            'asset_name' => $validated['asset_name'],
            'serial_number' => $validated['serial_number'] ?? null,
            'vendor' => $validated['vendor'] ?? null,
            'asset_condition' => $validated['asset_condition'] ?? null,
            'status' => $validated['status'],
            'issued_at' => $validated['issued_at'] ?? null,
            'returned_at' => $validated['returned_at'] ?? null,
            'attachment_path' => $path,
            'notes' => $validated['notes'] ?? null,
            'issued_by' => $actor->id,
            'returned_by' => $validated['status'] === EmployeeAsset::STATUS_RETURNED ? $actor->id : null,
        ]);

        $this->recordAssetLog($asset, $actor, 'issued', $asset->status, $asset->notes, [
            'serial_number' => $asset->serial_number,
            'vendor' => $asset->vendor,
        ]);
        $this->recordTimeline($profile, $actor, 'asset_issued', 'Asset issued', $asset->asset_name . ' assigned');

        return $asset;
    }

    public function updateAssetStatus(EmployeeAsset $asset, array $validated, User $actor): EmployeeAsset
    {
        $asset->update([
            'status' => $validated['status'],
            'asset_condition' => $validated['asset_condition'] ?? $asset->asset_condition,
            'returned_at' => $validated['status'] === EmployeeAsset::STATUS_RETURNED ? ($validated['returned_at'] ?? now()->toDateString()) : null,
            'returned_by' => $validated['status'] === EmployeeAsset::STATUS_RETURNED ? $actor->id : null,
            'notes' => $validated['notes'] ?? $asset->notes,
        ]);

        $action = $validated['status'] === EmployeeAsset::STATUS_RETURNED ? 'returned' : 'status_updated';

        $this->recordAssetLog($asset, $actor, $action, $asset->status, $validated['notes'] ?? null);
        $this->recordTimeline(
            $asset->employeeProfile,
            $actor,
            'asset_status_changed',
            'Asset status updated',
            $asset->asset_name . ' marked as ' . str_replace('_', ' ', $asset->status)
        );

        return $asset->fresh();
    }

    public function createAutomationNotification(User $recipient, string $title, string $message, string $actionUrl, array $data = []): void
    {
        AppNotification::query()->create([
            'user_id' => $recipient->id,
            'type' => AppNotification::TYPE_NEW_USER,
            'title' => $title,
            'message' => $message,
            'action_type' => AppNotification::ACTION_USER,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    private function resolveUser(array $validated, ?User $existingUser = null): User
    {
        if ($existingUser) {
            return $existingUser;
        }

        if (!empty($validated['existing_user_id'])) {
            return User::query()->findOrFail($validated['existing_user_id']);
        }

        return new User();
    }

    private function resolveDepartment(array $validated): ?EmployeeDepartment
    {
        if (!empty($validated['new_department'])) {
            return EmployeeDepartment::firstOrCreate(
                ['name' => trim($validated['new_department'])],
                ['code' => Str::upper(Str::slug($validated['new_department'], '_')), 'is_active' => true]
            );
        }

        return !empty($validated['department_id'])
            ? EmployeeDepartment::find($validated['department_id'])
            : null;
    }

    private function resolveDesignation(array $validated): ?EmployeeDesignation
    {
        if (!empty($validated['new_designation'])) {
            return EmployeeDesignation::firstOrCreate(
                ['name' => trim($validated['new_designation'])],
                ['code' => Str::upper(Str::slug($validated['new_designation'], '_')), 'is_active' => true]
            );
        }

        return !empty($validated['designation_id'])
            ? EmployeeDesignation::find($validated['designation_id'])
            : null;
    }

    private function resolveEmployeeCode(User $user): string
    {
        $attendanceCode = $user->attendanceProfile?->employee_code;
        if ($attendanceCode) {
            return $attendanceCode;
        }

        $existing = $user->employeeProfile?->employee_code;
        if ($existing) {
            return $existing;
        }

        $roleSlug = $user->role->slug ?? null;
        $prefix = self::EMPLOYEE_CODE_PREFIXES[$roleSlug] ?? 'EMP';
        $maxNumber = EmployeeProfile::query()
            ->where('employee_code', 'like', $prefix . '%')
            ->get()
            ->map(function (EmployeeProfile $profile) use ($prefix) {
                return (int) str_replace($prefix, '', $profile->employee_code);
            })
            ->max() ?? 0;

        return sprintf('%s%04d', $prefix, $maxNumber + 1);
    }

    private function syncAttendanceEmployeeCode(User $user, string $employeeCode): void
    {
        $profile = $user->attendanceProfile;
        if ($profile) {
            $profile->update(['employee_code' => $employeeCode]);
            return;
        }

        UserAttendanceProfile::query()->where('user_id', $user->id)->update(['employee_code' => $employeeCode]);
    }

    private function recordProfileTimeline(EmployeeProfile $profile, User $actor, bool $isUpdate): void
    {
        $this->recordTimeline(
            $profile,
            $actor,
            $isUpdate ? 'profile_updated' : 'employee_created',
            $isUpdate ? 'Employee profile updated' : 'Employee profile created',
            $isUpdate
                ? 'Profile, lifecycle, or HR details updated.'
                : 'Employee master profile created and linked to user.'
        );

        if ($profile->joining_date) {
            $this->recordTimeline(
                $profile,
                $actor,
                'joined',
                'Joined company',
                'Joining date recorded as ' . $profile->joining_date->format('d M Y')
            );
        }

        $this->recordTimeline(
            $profile,
            $actor,
            'status_changed',
            'Employment status set',
            'Current lifecycle status: ' . str_replace('_', ' ', $profile->employment_status)
        );
    }

    public function recordTimeline(EmployeeProfile $profile, User $actor, string $type, string $title, ?string $summary = null, array $meta = []): EmployeeTimelineEvent
    {
        return EmployeeTimelineEvent::query()->create([
            'employee_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'event_type' => $type,
            'title' => $title,
            'summary' => $summary,
            'meta_json' => $meta ?: null,
            'actor_id' => $actor->id,
            'event_date' => now(),
        ]);
    }

    private function recordAssetLog(EmployeeAsset $asset, User $actor, string $action, ?string $status, ?string $notes = null, array $meta = []): EmployeeAssetLog
    {
        return EmployeeAssetLog::query()->create([
            'employee_asset_id' => $asset->id,
            'employee_profile_id' => $asset->employee_profile_id,
            'action' => $action,
            'status' => $status,
            'notes' => $notes,
            'meta_json' => $meta ?: null,
            'performed_by' => $actor->id,
            'performed_at' => now(),
        ]);
    }
}
