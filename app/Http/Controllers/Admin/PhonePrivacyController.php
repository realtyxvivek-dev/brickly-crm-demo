<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhonePrivacyAudit;
use App\Models\User;
use App\Models\UserPhonePrivacySetting;
use App\Services\PhonePrivacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PhonePrivacyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $role = trim((string) $request->input('role'));

        $users = User::query()
            ->with(['role', 'phonePrivacySetting.updatedBy'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereNotIn('slug', ['admin', 'crm']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', $role)))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $roles = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereNotIn('slug', ['admin', 'crm']))
            ->with('role')
            ->get()
            ->pluck('role')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $recentAudits = PhonePrivacyAudit::query()
            ->with(['actor:id,name', 'targetUser:id,name', 'lead:id,name'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('admin.phone-privacy.index', compact('users', 'roles', 'recentAudits', 'search', 'role'));
    }

    public function update(Request $request, User $user, PhonePrivacyService $privacy)
    {
        $this->ensureEligible($user);
        $validated = $this->validatedPolicy($request);

        DB::transaction(function () use ($request, $user, $validated, $privacy) {
            $setting = UserPhonePrivacySetting::query()->lockForUpdate()->firstOrNew(['user_id' => $user->id]);
            $old = $setting->exists ? $setting->only(['mask_enabled', 'call_mode', 'whatsapp_mode']) : [
                'mask_enabled' => false,
                'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
                'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
            ];

            $setting->fill($validated + ['updated_by' => $request->user()->id])->save();
            $new = $setting->only(['mask_enabled', 'call_mode', 'whatsapp_mode']);

            $privacy->audit('settings_updated', $request->user(), $user, null, $old, $new, null, $request);
        });

        $privacy->forget($user);

        return back()->with('success', "Phone privacy updated for {$user->name}.");
    }

    public function bulkUpdate(Request $request, PhonePrivacyService $privacy)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'mask_enabled' => ['required', 'boolean'],
            'call_mode' => ['required', Rule::in([
                UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
                UserPhonePrivacySetting::CALL_CLOUD_ONLY,
            ])],
            'whatsapp_mode' => ['required', Rule::in([
                UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
                UserPhonePrivacySetting::WHATSAPP_API_ONLY,
            ])],
        ]);

        $users = User::query()->with('role')->whereIn('id', $validated['user_ids'])->get();
        foreach ($users as $user) {
            $this->ensureEligible($user);
        }

        DB::transaction(function () use ($request, $users, $validated, $privacy) {
            foreach ($users as $user) {
                $setting = UserPhonePrivacySetting::query()->lockForUpdate()->firstOrNew(['user_id' => $user->id]);
                $old = $setting->exists ? $setting->only(['mask_enabled', 'call_mode', 'whatsapp_mode']) : [
                    'mask_enabled' => false,
                    'call_mode' => UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
                    'whatsapp_mode' => UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
                ];
                $setting->fill([
                    'mask_enabled' => (bool) $validated['mask_enabled'],
                    'call_mode' => $validated['call_mode'],
                    'whatsapp_mode' => $validated['whatsapp_mode'],
                    'updated_by' => $request->user()->id,
                ])->save();

                $privacy->audit(
                    'bulk_settings_updated',
                    $request->user(),
                    $user,
                    null,
                    $old,
                    $setting->only(['mask_enabled', 'call_mode', 'whatsapp_mode']),
                    ['bulk_count' => $users->count()],
                    $request
                );
                $privacy->forget($user);
            }
        });

        return back()->with('success', $users->count() . ' user phone privacy settings updated.');
    }

    private function validatedPolicy(Request $request): array
    {
        $validated = $request->validate([
            'mask_enabled' => ['required', 'boolean'],
            'call_mode' => ['required', Rule::in([
                UserPhonePrivacySetting::CALL_CLOUD_PREFERRED,
                UserPhonePrivacySetting::CALL_CLOUD_ONLY,
            ])],
            'whatsapp_mode' => ['required', Rule::in([
                UserPhonePrivacySetting::WHATSAPP_DIRECT_ALLOWED,
                UserPhonePrivacySetting::WHATSAPP_API_ONLY,
            ])],
        ]);
        $validated['mask_enabled'] = (bool) $validated['mask_enabled'];

        return $validated;
    }

    private function ensureEligible(User $user): void
    {
        $user->loadMissing('role');
        abort_if($user->isAdmin() || $user->isCrm(), 422, 'Admin and CRM users remain unmasked.');
    }
}
