@extends('layouts.app')

@section('title', isset($rule) ? 'Edit Automation' : 'Create Automation')

@php
    $rule = $rule ?? null;
    $prefill = $prefill ?? [];
    $ruleSource = $rule?->source;
    $selectedSource = old('source_type', $rule?->source_type ?? ($ruleSource === 'mcube' ? 'ivr' : ($ruleSource ?? ($prefill['source_type'] ?? 'facebook_lead_ads'))));
    $selectedSourceId = old('source_id', $rule?->source_id ?? ($rule?->fb_form_id ?? $rule?->google_sheet_config_id ?? ($prefill['source_id'] ?? '')));
    $selectedFbFormIds = collect(old('fb_form_ids', []))
        ->merge($rule && $rule->relationLoaded('fbForms') ? $rule->fbForms->pluck('id')->all() : [])
        ->merge($rule?->fb_form_id ? [$rule->fb_form_id] : [])
        ->merge($selectedSource === 'facebook_lead_ads' && $rule?->source_id && is_numeric($rule->source_id) ? [$rule->source_id] : [])
        ->merge($selectedSource === 'facebook_lead_ads' && !empty($prefill['source_id']) ? [$prefill['source_id']] : [])
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $selectedSourceLabel = old('source_label', $rule?->source_label ?? ($prefill['source_label'] ?? ''));
    $selectedMethod = old('distribution_method', $rule?->distribution_method ?? $rule?->assignment_method ?? 'round_robin');
    $selectedUsers = collect(old('users', isset($rule) ? $rule->users->map(fn ($user) => [
        'user_id' => $user->user_id,
        'percentage' => $user->percentage,
        'daily_limit' => $user->daily_limit,
    ])->values()->all() : []));
    if ($selectedUsers->isEmpty()) {
        $selectedUsers = collect([['user_id' => '', 'percentage' => '', 'daily_limit' => '']]);
    }
    $selectedUserSettings = $selectedUsers->filter(fn ($user) => !empty($user['user_id']))->keyBy('user_id');
    $taskEnabled = old('task_enabled', $rule?->task_enabled ?? $rule?->auto_create_task ?? true);
    $notifyEnabled = old('notification_enabled', $rule?->notification_enabled ?? true);
    $skipLeadOff = old('skip_lead_off_users', $rule?->skip_lead_off_users ?? true);
    $duplicateHandling = old('duplicate_handling', $rule?->duplicate_handling ?? 'keep_existing_owner_mark_reenquiry');
    $assignableUserOptions = $assignableUsers->map(function ($user) {
        $todayAssigned = \App\Models\LeadAssignment::where('assigned_to', $user->id)->whereDate('assigned_at', today())->count();
        $pendingTasks = \App\Models\Task::where('assigned_to', $user->id)->where('status', 'pending')->count() + \App\Models\TelecallerTask::where('assigned_to', $user->id)->where('status', 'pending')->count();
        $leadOn = ! app(\App\Services\UserStatusService::class)->isUserAbsent($user->id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role?->name,
            'lead_on' => $leadOn,
            'today_assigned' => $todayAssigned,
            'pending_tasks' => $pendingTasks,
        ];
    })->values();
    $sourceOptions = [
        'facebook_lead_ads' => ['label' => 'Facebook Lead Ads', 'hint' => 'Page / Form / Campaign', 'icon' => 'fab fa-facebook'],
        'website' => ['label' => 'Website', 'hint' => 'Webhook / landing page', 'icon' => 'fas fa-globe'],
        '99acres' => ['label' => '99acres', 'hint' => 'Portal webhook', 'icon' => 'fas fa-building'],
        'ivr' => ['label' => 'IVR', 'hint' => 'MCube / BulkSMSPlans', 'icon' => 'fas fa-phone-volume'],
        'whatsapp' => ['label' => 'WhatsApp', 'hint' => 'WhatsApp source leads', 'icon' => 'fab fa-whatsapp'],
        'manual_import' => ['label' => 'Manual Import', 'hint' => 'CSV / manual uploads', 'icon' => 'fas fa-file-import'],
    ];
    $methodOptions = [
        'round_robin' => ['title' => 'Round Robin', 'body' => 'Leads users me one-by-one distribute honge. Best for normal sales teams.', 'icon' => 'fas fa-rotate'],
        'single_user' => ['title' => 'Single User', 'body' => 'Saare leads ek fixed user ko jayenge. Best for special campaign/direct owner.', 'icon' => 'fas fa-user'],
        'percentage' => ['title' => 'Percentage Split', 'body' => 'User-wise percentage set karo, jaise Ayushi 40%, Omkar 30%, Naveen 30%.', 'icon' => 'fas fa-percent'],
        'first_available' => ['title' => 'First Available / Capacity Based', 'body' => 'Lead ON user with lowest pending load gets the lead.', 'icon' => 'fas fa-user-check'],
    ];
@endphp

@push('styles')
<style>
    .wizard-shell { max-width: 1120px; margin: 0 auto; }
    .wizard-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .wizard-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-bottom:16px; }
    .wizard-step-tab { min-height:44px; border:1px solid #d7dee4; background:#fff; border-radius:8px; padding:9px 12px; text-align:left; font-size:12px; font-weight:800; color:#64748b; transition:border-color .15s,background-color .15s,color .15s; }
    .wizard-step-tab.active { border-color:#205A44; background:#ecfdf5; color:#063A1C; }
    .wizard-step-tab.complete { color:#205A44; }
    .wizard-step-tab:disabled { cursor:default; opacity:.68; }
    .wizard-panel { display:none; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .wizard-panel.active { display:block; }
    .section-title { font-size:16px; font-weight:800; color:#0f172a; }
    .section-copy { margin-top:3px; margin-bottom:16px; font-size:13px; line-height:1.5; color:#64748b; }
    .option-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
    .method-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
    .pick-card { position:relative; display:block; height:100%; cursor:pointer; }
    .pick-card > input { position:absolute; opacity:0; pointer-events:none; }
    .pick-card-body { height:100%; border:1px solid #dbe2e8; border-radius:8px; background:#fff; padding:13px; transition:border-color .15s,background-color .15s,box-shadow .15s; }
    .pick-card:hover .pick-card-body { border-color:#8fb7a2; }
    .pick-card > input:focus-visible + .pick-card-body { outline:3px solid rgba(32,90,68,.2); outline-offset:2px; }
    .pick-card > input:checked + .pick-card-body { border-color:#205A44; background:#f0fdf4; box-shadow:0 0 0 1px #205A44; }
    .pick-icon { width:34px; height:34px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; background:#eef2f7; color:#334155; margin-bottom:8px; }
    .field-label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
    .field-input { width:100%; min-height:42px; border:1px solid #cbd5e1; border-radius:8px; padding:9px 11px; font-size:13px; background:#fff; }
    .field-input:focus { border-color:#205A44; outline:3px solid rgba(32,90,68,.12); }
    .source-details { margin-top:16px; padding-top:16px; border-top:1px solid #e5e7eb; }
    .form-search { position:relative; margin-bottom:8px; }
    .form-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b; pointer-events:none; }
    .form-search .field-input { padding-left:36px; }
    .form-check-list { border:1px solid #d7dee4; border-radius:8px; background:#fff; max-height:240px; overflow:auto; padding:6px; }
    .form-check-row { display:flex; align-items:flex-start; gap:10px; border-radius:7px; padding:9px 10px; cursor:pointer; }
    .form-check-row:hover { background:#f8fafc; }
    .form-check-row input { margin-top:3px; }
    .form-check-title { font-size:13px; font-weight:700; color:#0f172a; line-height:1.35; }
    .selected-source-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .selected-source-chip { border-radius:999px; background:#eef2f7; color:#334155; padding:5px 9px; font-size:11px; font-weight:700; }
    .assignment-tools { display:flex; align-items:end; justify-content:space-between; gap:12px; margin:16px 0 10px; }
    .bulk-user-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; max-height:340px; overflow:auto; padding:2px; }
    .bulk-user-card { display:block; border:1px solid #dbe2e8; border-radius:8px; background:#fff; padding:11px; cursor:pointer; transition:border-color .15s,background-color .15s; }
    .bulk-user-card:hover { border-color:#8fb7a2; }
    .bulk-user-card.selected { border-color:#205A44; background:#f0fdf4; }
    .bulk-user-main { display:flex; gap:9px; align-items:flex-start; }
    .bulk-user-main input { margin-top:3px; }
    .bulk-user-name { display:block; font-size:13px; font-weight:800; color:#0f172a; line-height:1.25; }
    .bulk-user-meta { display:block; font-size:11px; color:#64748b; line-height:1.45; margin-top:3px; }
    .user-settings { display:none; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; padding-top:10px; border-top:1px solid #dbe2e8; }
    .bulk-user-card.selected .user-settings { display:grid; }
    .percentage-setting { display:none; }
    .percentage-mode .percentage-setting { display:block; }
    .bulk-summary { font-size:12px; font-weight:800; color:#205A44; }
    .inline-error { display:none; margin-top:12px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; padding:10px 12px; color:#b91c1c; font-size:13px; font-weight:700; }
    .inline-error.visible { display:block; }
    .advanced-panel { margin-top:18px; border:1px solid #dbe2e8; border-radius:8px; background:#f8fafc; }
    .advanced-panel summary { min-height:44px; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 13px; font-size:13px; font-weight:800; color:#334155; }
    .advanced-content { border-top:1px solid #dbe2e8; padding:14px; }
    .rule-toggle { display:flex; align-items:flex-start; gap:10px; border:1px solid #e2e8f0; border-radius:8px; padding:11px; background:#fff; }
    .review-summary { border-left:4px solid #205A44; border-radius:0 8px 8px 0; background:#f0fdf4; padding:14px 16px; color:#153d2e; font-size:14px; font-weight:700; line-height:1.55; }
    .review-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:14px; }
    .review-item { border:1px solid #e2e8f0; border-radius:8px; padding:11px 12px; background:#f8fafc; }
    .wizard-actions { display:flex; justify-content:space-between; gap:12px; margin-top:14px; }
    .btn-wizard { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:8px; padding:9px 15px; font-size:13px; font-weight:800; border:1px solid #cbd5e1; background:#fff; color:#334155; }
    .btn-wizard:hover { background:#f8fafc; }
    .btn-wizard.primary { border-color:#0b4a2d; background:#0b4a2d; color:#fff; }
    .btn-wizard.primary:hover { background:#063a1c; }
    .btn-wizard:disabled { cursor:not-allowed; opacity:.55; }
    @media (max-width:900px) {
        .method-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .option-grid,.bulk-user-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:640px) {
        .wizard-header { align-items:center; }
        .wizard-panel { padding:15px; }
        .wizard-step-tab { text-align:center; padding:8px 5px; }
        .option-grid,.method-grid,.bulk-user-grid,.review-grid { grid-template-columns:1fr; }
        .assignment-tools { align-items:stretch; flex-direction:column; }
        .wizard-actions .btn-wizard { flex:1; }
    }
</style>
@endpush

@section('content')
<div class="wizard-shell">
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" id="serverErrorSummary">
            <strong>Please review:</strong> {{ $errors->first() }}
        </div>
    @endif

    <div class="wizard-header">
        <div>
            <h1 class="text-xl font-black text-slate-900">{{ isset($rule) ? 'Edit Lead Routing' : 'New Lead Routing' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Choose where leads come from and who should receive them.</p>
        </div>
        <a href="{{ route('admin.automation.index') }}" class="btn-wizard"><i class="fas fa-times" aria-hidden="true"></i> Cancel</a>
    </div>

    <div class="wizard-steps" aria-label="Automation steps">
        @foreach(['Source', 'Assignment', 'Review'] as $index => $label)
            <button type="button" class="wizard-step-tab {{ $index === 0 ? 'active' : '' }}" data-step-tab="{{ $index + 1 }}">Step {{ $index + 1 }}: {{ $label }}</button>
        @endforeach
    </div>

    <form method="POST" action="{{ isset($rule) ? route('admin.automation.update', $rule) : route('admin.automation.store') }}" id="automationWizardForm">
        @csrf
        @if(isset($rule)) @method('PUT') @endif
        <input type="hidden" name="source_label" id="sourceLabelInput" value="{{ $selectedSourceLabel }}">

        <section class="wizard-panel active" data-step="1">
            <h2 class="section-title">Where will these leads come from?</h2>
            <p class="section-copy">Select one lead source. Related source details will appear below.</p>
            <div class="option-grid">
                @foreach($sourceOptions as $value => $source)
                    <label class="pick-card">
                        <input type="radio" name="source_type" value="{{ $value }}" @checked($selectedSource === $value)>
                        <div class="pick-card-body">
                            <span class="pick-icon"><i class="{{ $source['icon'] }}"></i></span>
                            <div class="font-black text-slate-900">{{ $source['label'] }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $source['hint'] }}</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="source-details">
                <div data-specific-source="facebook_lead_ads">
                    <label class="field-label">Facebook Forms</label>
                    <div class="form-search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="facebookFormSearch" class="field-input" placeholder="Search page or form name" aria-label="Search Facebook forms" aria-controls="adminFbFormList" autocomplete="off">
                    </div>
                    <div class="form-check-list" id="adminFbFormList">
                        @foreach($fbForms as $form)
                            @php($formLabel = ($form->page?->page_name ? $form->page->page_name . ' / ' : '') . ($form->form_name ?: $form->form_id))
                            <label class="form-check-row">
                                <input type="checkbox" name="fb_form_ids[]" value="{{ $form->id }}" class="admin-fb-form-checkbox js-specific-select" data-label="{{ $formLabel }}" @checked(in_array((string) $form->id, $selectedFbFormIds, true))>
                                <span class="form-check-title">{{ $formLabel }}</span>
                            </label>
                        @endforeach
                        <div id="facebookFormNoResults" class="hidden px-3 py-6 text-center text-sm font-semibold text-slate-500">No matching forms found.</div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Select forms, or leave all unchecked for a source-wide Facebook rule.</p>
                    <div id="selectedFbFormsPreview" class="selected-source-chips"></div>
                </div>
                <div data-specific-source="google_sheets">
                    <label class="field-label">Google Sheet</label>
                    <select name="google_sheet_config_id" class="field-input js-specific-select">
                        <option value="">Source-wide sheet rule</option>
                        @foreach($googleSheets as $sheet)
                            <option value="{{ $sheet->id }}" data-label="{{ $sheet->sheet_name }}" @selected((string) $selectedSourceId === (string) $sheet->id)>{{ $sheet->sheet_name }}</option>
                        @endforeach
                    </select>
                </div>
                <details class="advanced-panel" id="sourceAdvancedPanel">
                    <summary><i class="fas fa-sliders-h" aria-hidden="true"></i> Optional source details</summary>
                    <div class="advanced-content grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="field-label" for="ruleNameInput">Custom Rule Name</label>
                            <input type="text" name="name" id="ruleNameInput" class="field-input" value="{{ old('name', $rule->name ?? '') }}" placeholder="Auto-generated if blank">
                        </div>
                        <div>
                            <label class="field-label" for="sourceIdInput">Specific Source ID</label>
                            <input type="text" name="source_id" id="sourceIdInput" class="field-input" value="{{ $selectedSourceId }}" placeholder="Optional campaign or webhook ID">
                        </div>
                        <div>
                            <label class="field-label" for="sourceLabelDisplay">Source Label</label>
                            <input type="text" id="sourceLabelDisplay" class="field-input" value="{{ $selectedSourceLabel }}" placeholder="Campaign or form label">
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <section class="wizard-panel" data-step="2">
            <h2 class="section-title">How should leads be assigned?</h2>
            <p class="section-copy">Choose a distribution method, then select the receiving user or team.</p>
            <div class="method-grid">
                @foreach($methodOptions as $value => $method)
                    <label class="pick-card">
                        <input type="radio" name="distribution_method" value="{{ $value }}" @checked($selectedMethod === $value)>
                        <div class="pick-card-body">
                            <span class="pick-icon"><i class="{{ $method['icon'] }}" aria-hidden="true"></i></span>
                            <div class="font-black text-slate-900">{{ $method['title'] }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-500">{{ $method['body'] }}</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div id="singleUserWrap" class="mt-4">
                <label class="field-label" for="singleUserSelect">Lead Owner</label>
                <select name="single_user_id" id="singleUserSelect" class="field-input">
                    <option value="">Select user</option>
                    @foreach($assignableUsers as $user)
                        <option value="{{ $user->id }}" @selected((int) old('single_user_id', $rule->single_user_id ?? 0) === (int) $user->id)>{{ $user->name }} - {{ $user->role?->name }}</option>
                    @endforeach
                </select>
            </div>

            <div id="multiUserWrap">
                <div class="assignment-tools">
                    <div class="w-full md:max-w-xs">
                        <label class="field-label" for="roleFilter">Filter Users by Role</label>
                        <select id="roleFilter" class="field-input">
                            <option value="">All roles</option>
                            @foreach($assignableUsers->pluck('role.name')->filter()->unique()->sort() as $roleName)
                                <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="bulkUserSummary" class="bulk-summary">0 users selected</span>
                        <button type="button" class="btn-wizard" id="selectAllVisibleUsers"><i class="fas fa-check-double" aria-hidden="true"></i> Select visible</button>
                        <button type="button" class="btn-wizard" id="clearBulkUsers">Clear</button>
                    </div>
                </div>
                <div id="bulkUserGrid" class="bulk-user-grid">
                    @foreach($assignableUserOptions as $user)
                        @php($userSettings = $selectedUserSettings->get($user['id'], []))
                        @php($userSelected = !empty($userSettings))
                        <div class="bulk-user-card {{ $userSelected ? 'selected' : '' }}" data-role="{{ $user['role'] }}">
                            <label class="bulk-user-main">
                                <input type="checkbox" class="bulk-user-checkbox" name="users[{{ $user['id'] }}][user_id]" value="{{ $user['id'] }}" @checked($userSelected)>
                                <span>
                                    <span class="bulk-user-name">{{ $user['name'] }}</span>
                                    <span class="bulk-user-meta">{{ $user['role'] ?: 'User' }} | Lead {{ $user['lead_on'] ? 'ON' : 'OFF' }} | Today {{ $user['today_assigned'] }} | Pending {{ $user['pending_tasks'] }}</span>
                                </span>
                            </label>
                            <span class="user-settings">
                                <span class="percentage-setting">
                                    <span class="field-label">Share %</span>
                                    <input type="number" name="users[{{ $user['id'] }}][percentage]" class="field-input js-percentage-field" min="0" max="100" step="0.1" value="{{ $userSettings['percentage'] ?? '' }}" placeholder="0" @disabled(!$userSelected)>
                                </span>
                                <span>
                                    <span class="field-label">Daily Limit</span>
                                    <input type="number" name="users[{{ $user['id'] }}][daily_limit]" class="field-input js-user-setting" min="1" value="{{ $userSettings['daily_limit'] ?? '' }}" placeholder="Unlimited" @disabled(!$userSelected)>
                                </span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="assignmentError" class="inline-error" role="alert"></div>

            <details class="advanced-panel">
                <summary><i class="fas fa-cog" aria-hidden="true"></i> Advanced assignment settings</summary>
                <div class="advanced-content">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="rule-toggle"><input type="hidden" name="task_enabled" value="0"><input type="checkbox" name="task_enabled" value="1" @checked($taskEnabled)> <span><strong>Auto-create calling task</strong><br><small>Create a calling task for the assigned user.</small></span></label>
                        <label class="rule-toggle"><input type="hidden" name="notification_enabled" value="0"><input type="checkbox" name="notification_enabled" value="1" @checked($notifyEnabled)> <span><strong>Notify assigned user</strong><br><small>Send an in-app notification after assignment.</small></span></label>
                        <label class="rule-toggle"><input type="hidden" name="skip_lead_off_users" value="0"><input type="checkbox" name="skip_lead_off_users" value="1" @checked($skipLeadOff)> <span><strong>Skip lead-off users</strong><br><small>Do not assign leads to unavailable users.</small></span></label>
                        <label class="rule-toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active ?? true))> <span><strong>Activate immediately</strong><br><small>Start routing matching leads after saving.</small></span></label>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <label class="field-label">Daily Limit Per Rule</label>
                    <input type="number" name="daily_limit" class="field-input" min="1" value="{{ old('daily_limit', $rule->daily_limit ?? '') }}" placeholder="Unlimited">
                </div>
                <div>
                    <label class="field-label">Fallback User</label>
                    <select name="fallback_user_id" class="field-input">
                        <option value="">None</option>
                        @foreach($assignableUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) old('fallback_user_id', $rule->fallback_user_id ?? 0) === (int) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Duplicate Lead Handling</label>
                    <select name="duplicate_handling" class="field-input">
                        <option value="keep_existing_owner_mark_reenquiry" @selected($duplicateHandling === 'keep_existing_owner_mark_reenquiry')>Keep existing owner + mark re-enquiry</option>
                        <option value="keep_existing_owner" @selected($duplicateHandling === 'keep_existing_owner')>Keep existing owner</option>
                        <option value="reassign" @selected($duplicateHandling === 'reassign')>Reassign option</option>
                    </select>
                </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="wizard-panel" data-step="3">
            <h2 class="section-title">Review and activate</h2>
            <p class="section-copy">Confirm the routing summary before saving this automation.</p>
            <div class="review-summary" id="reviewSentence"></div>
            <div class="review-grid" id="reviewGrid"></div>
        </section>

        <div class="wizard-actions">
            <button type="button" class="btn-wizard hidden" id="prevStep"><i class="fas fa-arrow-left" aria-hidden="true"></i> Previous</button>
            <span id="firstStepSpacer"></span>
            <div class="flex gap-2">
                <button type="button" class="btn-wizard primary" id="nextStep">Continue <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                <button type="submit" class="btn-wizard primary hidden" id="submitWizard"><i class="fas fa-check" aria-hidden="true"></i> {{ isset($rule) ? 'Save Changes' : 'Create Automation' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const serverErrors = @json($errors->keys());
    let step = serverErrors.some((key) => key === 'single_user_id' || key === 'assignment_method' || key === 'distribution_method' || key.startsWith('users')) ? 2 : 1;
    let highestStep = step;
    const maxStep = 3;
    const panels = Array.from(document.querySelectorAll('[data-step]'));
    const tabs = Array.from(document.querySelectorAll('[data-step-tab]'));
    const prev = document.getElementById('prevStep');
    const next = document.getElementById('nextStep');
    const submit = document.getElementById('submitWizard');
    const sourceIdInput = document.getElementById('sourceIdInput');
    const sourceLabelInput = document.getElementById('sourceLabelInput');
    const sourceLabelDisplay = document.getElementById('sourceLabelDisplay');
    const ruleNameInput = document.getElementById('ruleNameInput');
    const adminFbFormList = document.getElementById('adminFbFormList');
    const facebookFormSearch = document.getElementById('facebookFormSearch');
    const facebookFormNoResults = document.getElementById('facebookFormNoResults');
    const selectedFbFormsPreview = document.getElementById('selectedFbFormsPreview');
    const roleFilter = document.getElementById('roleFilter');
    const bulkUserSummary = document.getElementById('bulkUserSummary');
    const assignmentError = document.getElementById('assignmentError');
    const singleUserSelect = document.getElementById('singleUserSelect');
    const form = document.getElementById('automationWizardForm');

    function sourceLabel(value) {
        const options = @json(collect($sourceOptions)->map(fn ($item) => $item['label']));
        return options[value] || value;
    }

    function selectedSource() {
        return document.querySelector('input[name="source_type"]:checked')?.value || 'facebook_lead_ads';
    }

    function selectedMethod() {
        return document.querySelector('input[name="distribution_method"]:checked')?.value || 'round_robin';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function selectedFbFormInputs() {
        return Array.from(document.querySelectorAll('.admin-fb-form-checkbox:checked'));
    }

    function filterFacebookForms() {
        const query = facebookFormSearch.value.trim().toLowerCase();
        let visibleCount = 0;
        adminFbFormList.querySelectorAll('.form-check-row').forEach((row) => {
            const visible = row.textContent.toLowerCase().includes(query);
            row.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        facebookFormNoResults.classList.toggle('hidden', visibleCount > 0);
    }

    function renderSelectedFbFormsPreview() {
        if (!selectedFbFormsPreview || !adminFbFormList) return;
        const selected = selectedFbFormInputs();
        selectedFbFormsPreview.innerHTML = selected.length
            ? selected.map((input) => `<span class="selected-source-chip">${escapeHtml(input.dataset.label || '')}</span>`).join('')
            : '<span class="selected-source-chip">Source-wide Facebook rule</span>';
    }

    function syncSpecificSource(resetSource = false) {
        const source = selectedSource();
        document.querySelectorAll('[data-specific-source]').forEach((element) => {
            element.style.display = element.dataset.specificSource === source ? '' : 'none';
        });

        const selectedForms = selectedFbFormInputs();
        const activeSpecific = document.querySelector(`[data-specific-source="${source}"] select`);
        if (source === 'facebook_lead_ads' && selectedForms.length) {
            const first = selectedForms[0];
            sourceIdInput.value = first.value;
            sourceLabelInput.value = selectedForms.length > 1 ? `${first.dataset.label} +${selectedForms.length - 1} more` : (first.dataset.label || 'Facebook Lead Ads');
            sourceLabelDisplay.value = sourceLabelInput.value;
        } else if (activeSpecific?.value) {
            sourceIdInput.value = activeSpecific.value;
            sourceLabelInput.value = activeSpecific.selectedOptions[0]?.dataset.label || activeSpecific.selectedOptions[0]?.textContent.trim() || sourceLabel(source);
            sourceLabelDisplay.value = sourceLabelInput.value;
        } else if (resetSource || !sourceLabelInput.value) {
            sourceIdInput.value = '';
            sourceLabelInput.value = sourceLabel(source);
            sourceLabelDisplay.value = sourceLabelInput.value;
        }

        if (!ruleNameInput.value) ruleNameInput.placeholder = `${sourceLabelInput.value || sourceLabel(source)} Automation`;
        renderSelectedFbFormsPreview();
    }

    function updateBulkSummary() {
        const count = document.querySelectorAll('.bulk-user-checkbox:checked:not(:disabled)').length;
        bulkUserSummary.textContent = `${count} user${count === 1 ? '' : 's'} selected`;
    }

    function syncUserCard(checkbox) {
        const card = checkbox.closest('.bulk-user-card');
        const selected = checkbox.checked && !checkbox.disabled;
        card.classList.toggle('selected', selected);
        card.querySelectorAll('.js-user-setting, .js-percentage-field').forEach((input) => {
            input.disabled = !selected;
        });
        updateBulkSummary();
    }

    function clearAssignmentError() {
        assignmentError.textContent = '';
        assignmentError.classList.remove('visible');
    }

    function syncMethodUi() {
        const method = selectedMethod();
        const single = method === 'single_user';
        const multiUserWrap = document.getElementById('multiUserWrap');
        document.getElementById('singleUserWrap').style.display = single ? '' : 'none';
        multiUserWrap.style.display = single ? 'none' : '';
        multiUserWrap.classList.toggle('percentage-mode', method === 'percentage');
        singleUserSelect.disabled = !single;
        document.querySelectorAll('.bulk-user-checkbox').forEach((checkbox) => {
            checkbox.disabled = single;
            syncUserCard(checkbox);
        });
        clearAssignmentError();
    }

    function filterUsers() {
        const role = roleFilter.value;
        document.querySelectorAll('.bulk-user-card').forEach((card) => {
            card.style.display = !role || card.dataset.role === role ? '' : 'none';
        });
    }

    function showAssignmentError(message, focusTarget) {
        assignmentError.textContent = message;
        assignmentError.classList.add('visible');
        focusTarget?.focus();
        assignmentError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function validateStep(currentStep) {
        if (currentStep !== 2) return true;
        clearAssignmentError();

        if (selectedMethod() === 'single_user') {
            if (!singleUserSelect.value) {
                showAssignmentError('Select the user who should receive these leads.', singleUserSelect);
                return false;
            }
            return true;
        }

        const selectedUsers = Array.from(document.querySelectorAll('.bulk-user-checkbox:checked:not(:disabled)'));
        if (!selectedUsers.length) {
            showAssignmentError('Select at least one user for this assignment method.', roleFilter);
            return false;
        }

        if (selectedMethod() === 'percentage') {
            const total = selectedUsers.reduce((sum, checkbox) => {
                const value = checkbox.closest('.bulk-user-card').querySelector('.js-percentage-field').value;
                return sum + (Number(value) || 0);
            }, 0);
            if (Math.abs(total - 100) > 0.01) {
                showAssignmentError(`Percentage split must total 100%. Current total is ${total}%.`);
                return false;
            }
        }
        return true;
    }

    function showStep(nextStep) {
        step = Math.max(1, Math.min(maxStep, nextStep));
        highestStep = Math.max(highestStep, step);
        panels.forEach((panel) => panel.classList.toggle('active', Number(panel.dataset.step) === step));
        tabs.forEach((tab) => {
            const tabStep = Number(tab.dataset.stepTab);
            tab.classList.toggle('active', tabStep === step);
            tab.classList.toggle('complete', tabStep < step);
            tab.disabled = tabStep > highestStep;
            tab.setAttribute('aria-current', tabStep === step ? 'step' : 'false');
        });
        prev.classList.toggle('hidden', step === 1);
        document.getElementById('firstStepSpacer').classList.toggle('hidden', step !== 1);
        next.classList.toggle('hidden', step === maxStep);
        submit.classList.toggle('hidden', step !== maxStep);
        if (step === maxStep) renderReview();
    }

    function renderReview() {
        const methodText = document.querySelector('input[name="distribution_method"]:checked')?.closest('.pick-card')?.querySelector('.font-black')?.textContent || selectedMethod();
        const selectedUserCount = selectedMethod() === 'single_user' ? (singleUserSelect.value ? 1 : 0) : document.querySelectorAll('.bulk-user-checkbox:checked:not(:disabled)').length;
        const selectedForms = selectedFbFormInputs();
        const scope = selectedSource() === 'facebook_lead_ads' && selectedForms.length
            ? `${selectedForms.length} Facebook form${selectedForms.length === 1 ? '' : 's'}`
            : (sourceLabelInput.value || 'Source-wide leads');

        document.getElementById('reviewSentence').textContent = `${scope} will be assigned using ${methodText} to ${selectedUserCount} user${selectedUserCount === 1 ? '' : 's'}.`;
        const rows = [
            ['Source', sourceLabel(selectedSource())],
            ['Scope', scope],
            ['Assignment', methodText],
            ['Users', selectedUserCount],
            ['Calling Task', document.querySelector('[name="task_enabled"][type="checkbox"]').checked ? 'On' : 'Off'],
            ['Status', document.querySelector('[name="is_active"][type="checkbox"]').checked ? 'Active after save' : 'Saved as inactive'],
        ];
        document.getElementById('reviewGrid').innerHTML = rows.map(([label, value]) => `
            <div class="review-item">
                <div class="text-xs font-black uppercase text-slate-500">${escapeHtml(label)}</div>
                <div class="mt-1 text-sm font-bold text-slate-900">${escapeHtml(value)}</div>
            </div>
        `).join('');
    }

    document.querySelectorAll('input[name="source_type"]').forEach((input) => input.addEventListener('change', () => syncSpecificSource(true)));
    document.querySelectorAll('input[name="distribution_method"]').forEach((input) => input.addEventListener('change', syncMethodUi));
    document.querySelectorAll('.js-specific-select, .admin-fb-form-checkbox').forEach((input) => input.addEventListener('change', () => syncSpecificSource()));
    facebookFormSearch.addEventListener('input', filterFacebookForms);
    sourceLabelDisplay.addEventListener('input', () => { sourceLabelInput.value = sourceLabelDisplay.value; });
    next.addEventListener('click', () => { if (validateStep(step)) showStep(step + 1); });
    prev.addEventListener('click', () => showStep(step - 1));
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        const target = Number(tab.dataset.stepTab);
        if (target <= highestStep && (target < step || validateStep(step))) showStep(target);
    }));

    roleFilter.addEventListener('change', filterUsers);
    document.querySelectorAll('.bulk-user-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            syncUserCard(checkbox);
            clearAssignmentError();
        });
    });
    document.getElementById('selectAllVisibleUsers').addEventListener('click', () => {
        document.querySelectorAll('.bulk-user-card').forEach((card) => {
            if (card.style.display === 'none') return;
            const checkbox = card.querySelector('.bulk-user-checkbox');
            checkbox.checked = true;
            syncUserCard(checkbox);
        });
    });
    document.getElementById('clearBulkUsers').addEventListener('click', () => {
        document.querySelectorAll('.bulk-user-checkbox').forEach((checkbox) => {
            checkbox.checked = false;
            syncUserCard(checkbox);
        });
    });

    form.addEventListener('submit', (event) => {
        if (!validateStep(2)) {
            event.preventDefault();
            showStep(2);
            return;
        }
        syncSpecificSource();
        if (sourceLabelDisplay.value) sourceLabelInput.value = sourceLabelDisplay.value;
        if (!ruleNameInput.value) ruleNameInput.value = `${sourceLabelInput.value || sourceLabel(selectedSource())} Automation`;
        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Saving...';
    });

    syncSpecificSource();
    syncMethodUi();
    filterUsers();
    showStep(step);
})();
</script>
@endpush
