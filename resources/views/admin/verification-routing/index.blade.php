@extends('layouts.app')

@section('title', 'Verification Routing')
@section('page-title', 'Verification Routing')

@section('content')
<style>
    .vr-page { max-width: 1680px; }
    .vr-page form.rounded-2xl { box-shadow: 0 10px 26px rgba(15, 23, 42, .05); }
    .vr-page select,
    .vr-page input:not([type="checkbox"]) {
        border: 1px solid #d8e1ec !important;
        border-radius: 12px !important;
        background-color: #fff !important;
        min-height: 42px;
        padding: 9px 12px;
        color: #0f172a;
    }
    .vr-page select[multiple] {
        display: none !important;
    }
    .vr-choice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
        gap: 8px;
        border: 1px solid #d8e1ec;
        border-radius: 14px;
        background: #f8fafc;
        padding: 10px;
        min-height: 58px;
    }
    .vr-choice-chip {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 9px 11px;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.1;
        cursor: pointer;
        user-select: none;
    }
    .vr-choice-chip input {
        width: 14px;
        height: 14px;
        accent-color: #047857;
        flex: 0 0 auto;
    }
    .vr-choice-chip:has(input:checked) {
        color: #065f46;
        background: #dcfce7;
        border-color: #86efac;
    }
    .vr-page .rounded-2xl.border.border-slate-200.bg-slate-50\/60 {
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border-color: #dbe5ef;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .04);
    }
    .vr-page label.text-xs {
        display: block;
    }
    .vr-page .grid.gap-3.md\:grid-cols-5 {
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 16px;
    }
    .vr-page .grid.gap-3.md\:grid-cols-5 > label:nth-child(1) { grid-column: span 3 / span 3; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > label:nth-child(2) { grid-column: span 4 / span 4; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > label:nth-child(3) { grid-column: span 5 / span 5; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > label:nth-child(4) { grid-column: span 7 / span 7; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > label:nth-child(5) { grid-column: span 5 / span 5; }
    .vr-page form.rounded-2xl > .flex.justify-end.border-t {
        position: sticky;
        bottom: 0;
        z-index: 5;
        background: rgba(255, 255, 255, .94);
        backdrop-filter: blur(10px);
    }
    .vr-mode-note {
        margin-top: 8px;
        border-radius: 12px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
        padding: 9px 11px;
        font-size: 12px;
        font-weight: 700;
    }
    .vr-hidden-field {
        display: none !important;
    }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-field-mode { grid-column: span 3 / span 3 !important; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-field-fixed-role { grid-column: span 5 / span 5 !important; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-field-fixed-user { grid-column: span 4 / span 4 !important; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-field-fallback-role { grid-column: span 7 / span 7 !important; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-field-fallback-user { grid-column: span 5 / span 5 !important; }
    .vr-page .grid.gap-3.md\:grid-cols-5 > .vr-mode-note {
        grid-column: 1 / -1 !important;
        max-width: none;
        white-space: normal;
        line-height: 1.45;
    }
    @media (max-width: 900px) {
        .vr-page .grid.gap-3.md\:grid-cols-5 { grid-template-columns: 1fr; }
        .vr-page .grid.gap-3.md\:grid-cols-5 > label { grid-column: auto !important; }
    }
</style>

<div class="vr-page space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Verification Routing</h1>
            <p class="text-sm text-slate-500">Decide who can verify meetings, site visits, closers, and closing/KYC items.</p>
        </div>
        <a href="{{ route('admin.verifications') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
            <i class="fas fa-check-circle mr-2 text-emerald-700"></i> Open Verifications
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="font-semibold">Please fix these errors:</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.verification-routing.settings.update') }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        @csrf
        @method('PUT')
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Workflow default rules</h2>
            <p class="text-sm text-slate-500">Custom mappings are checked first; these defaults apply when no mapping matches.</p>
        </div>
        <div class="grid gap-4 p-5 xl:grid-cols-2">
            @foreach($workflows as $workflowType => $workflowLabel)
                @php($setting = $settings[$workflowType])
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="mb-4 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-bold text-slate-900">{{ $workflowLabel }}</div>
                            <div class="text-xs text-slate-500">Workflow key: {{ $workflowType }}</div>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Mode
                            <select name="settings[{{ $workflowType }}][mode]" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                @foreach($modes as $mode => $modeLabel)
                                    <option value="{{ $mode }}" @selected($setting->mode === $mode)>{{ $modeLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 md:col-span-2">
                            Fixed role(s)
                            <select name="settings[{{ $workflowType }}][fixed_role_ids][]" multiple class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected(in_array($role->id, $setting->fixed_role_ids ?? []))>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 md:col-span-2">
                            Fixed user
                            <select name="settings[{{ $workflowType }}][fixed_user_id]" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                <option value="">None</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((int) $setting->fixed_user_id === (int) $user->id)>{{ $user->name }} — {{ $user->role?->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 md:col-span-3">
                            Fallback role(s) <span class="text-red-500">*</span>
                            <select name="settings[{{ $workflowType }}][fallback_role_ids][]" multiple required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected(in_array($role->id, $setting->fallback_role_ids ?? []))>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 md:col-span-2">
                            Fallback user
                            <select name="settings[{{ $workflowType }}][fallback_user_id]" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                                <option value="">None</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((int) $setting->fallback_user_id === (int) $user->id)>{{ $user->name }} — {{ $user->role?->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                        <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">
                            Save {{ $workflowLabel }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </form>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
            <h2 class="text-lg font-bold text-slate-900">Create custom mapping</h2>
            <p class="mb-4 text-sm text-slate-500">Higher priority mappings override workflow defaults.</p>
            <form method="POST" action="{{ route('admin.verification-routing.mappings.store') }}" class="grid gap-3">
                @csrf
                <div class="flex justify-end">
                    <button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">
                        Save custom mapping
                    </button>
                </div>
                @include('admin.verification-routing.partials.mapping-fields', ['mapping' => null])
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Save custom mapping</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-900">Custom mappings</h2>
                <p class="text-sm text-slate-500">Priority order: user mapping, team mapping, role mapping, then lower priority number.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($mappings as $mapping)
                    <div class="p-5">
                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $workflows[$mapping->workflow_type] ?? $mapping->workflow_type }}</span>
                                <span class="ml-2 rounded-full {{ $mapping->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} px-3 py-1 text-xs font-bold">{{ $mapping->is_active ? 'Active' : 'Disabled' }}</span>
                            </div>
                            @if($mapping->is_active)
                                <form method="POST" action="{{ route('admin.verification-routing.mappings.disable', $mapping) }}" onsubmit="return confirm('Disable this mapping? Existing records stay safe.');">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-sm font-semibold text-red-600 hover:text-red-700">Disable</button>
                                </form>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.verification-routing.mappings.update', $mapping) }}" class="grid gap-3">
                            @csrf
                            @method('PUT')
                            @include('admin.verification-routing.partials.mapping-fields', ['mapping' => $mapping])
                            <div class="flex justify-end">
                                <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Update mapping</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">No custom mappings yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Test routing preview</h2>
        <p class="mb-4 text-sm text-slate-500">Enter a Meeting/Site Visit/Closer item ID and optional actor to see matched rule and eligible verifiers.</p>
        <form method="GET" action="{{ route('admin.verification-routing.index') }}" class="grid gap-3 md:grid-cols-4">
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Workflow
                <select name="preview_workflow_type" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach($workflows as $workflowType => $workflowLabel)
                        <option value="{{ $workflowType }}" @selected(request('preview_workflow_type') === $workflowType)>{{ $workflowLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Item ID
                <input name="preview_item_id" value="{{ request('preview_item_id') }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Meeting or Site Visit ID">
            </label>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Actor user
                <select name="preview_actor_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="">No actor check</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) request('preview_actor_id') === (int) $user->id)>{{ $user->name }} — {{ $user->role?->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button class="w-full rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Preview routing</button>
            </div>
        </form>

        @if($preview)
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                @if(isset($preview['error']))
                    <div class="font-semibold text-red-600">{{ $preview['error'] }}</div>
                @else
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><div class="text-xs uppercase text-slate-500">Workflow</div><div class="font-bold">{{ $preview['workflow_label'] }}</div></div>
                        <div><div class="text-xs uppercase text-slate-500">Matched rule</div><div class="font-bold">{{ $preview['matched_rule_label'] }}</div></div>
                        <div><div class="text-xs uppercase text-slate-500">Fallback</div><div class="font-bold">{{ $preview['fallback_applied'] ? 'Applied' : 'No' }}</div></div>
                        <div><div class="text-xs uppercase text-slate-500">Actor access</div><div class="font-bold">{{ is_null($preview['actor_can_verify']) ? 'Not checked' : ($preview['actor_can_verify'] ? 'Allowed' : 'Denied') }}</div></div>
                    </div>
                    <div class="mt-3">
                        <div class="text-xs uppercase text-slate-500">Eligible verifiers</div>
                        <div class="mt-1 font-semibold text-slate-800">{{ implode(', ', $preview['eligible_verifier_names'] ?? []) ?: 'None' }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vr-page select[multiple]').forEach(function (select) {
        if (select.dataset.vrEnhanced === '1') return;
        select.dataset.vrEnhanced = '1';

        const grid = document.createElement('div');
        grid.className = 'vr-choice-grid';

        Array.from(select.options).forEach(function (option) {
            const label = document.createElement('label');
            label.className = 'vr-choice-chip';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = option.selected;

            const text = document.createElement('span');
            text.textContent = option.textContent.trim();

            checkbox.addEventListener('change', function () {
                option.selected = checkbox.checked;
            });

            label.appendChild(checkbox);
            label.appendChild(text);
            grid.appendChild(label);
        });

        select.insertAdjacentElement('afterend', grid);
    });

    const modeHelp = {
        reporting_senior: 'Reporting Senior mode: creator ke manager-chain + fallback users verify kar sakte hain.',
        fixed_role: 'Fixed Role mode: selected role users verify karenge. Fallback backup rahega.',
        fixed_user: 'Fixed User mode: selected user verify karega. Fallback backup rahega.',
        custom_mapping: 'Custom Mapping mode: neeche custom mapping rules apply honge. Fallback backup rahega.'
    };

    document.querySelectorAll('.vr-page form[action*="verification-routing/settings"] .rounded-2xl.border.border-slate-200').forEach(function (card) {
        const fieldGrid = card.querySelector('.grid.gap-3.md\\:grid-cols-5');
        const labels = fieldGrid ? Array.from(fieldGrid.children).filter(function (child) {
            return child.tagName === 'LABEL';
        }) : [];
        const modeSelect = labels[0]?.querySelector('select');
        if (!modeSelect) return;

        const fields = {
            mode: labels[0],
            fixedRole: labels[1],
            fixedUser: labels[2],
            fallbackRole: labels[3],
            fallbackUser: labels[4],
        };

        fields.mode?.classList.add('vr-field-mode');
        fields.fixedRole?.classList.add('vr-field-fixed-role');
        fields.fixedUser?.classList.add('vr-field-fixed-user');
        fields.fallbackRole?.classList.add('vr-field-fallback-role');
        fields.fallbackUser?.classList.add('vr-field-fallback-user');

        let note = card.querySelector('.vr-mode-note');
        if (!note) {
            note = document.createElement('div');
            note.className = 'vr-mode-note';
            modeSelect.closest('label').insertAdjacentElement('afterend', note);
        }

        function syncModeFields() {
            const mode = modeSelect.value;
            [fields.fixedRole, fields.fixedUser, fields.fallbackRole, fields.fallbackUser].forEach(function (field) {
                field?.classList.add('vr-hidden-field');
            });

            if (mode === 'fixed_role') {
                fields.fixedRole?.classList.remove('vr-hidden-field');
            }

            if (mode === 'fixed_user') {
                fields.fixedUser?.classList.remove('vr-hidden-field');
            }

            fields.fallbackRole?.classList.remove('vr-hidden-field');
            fields.fallbackUser?.classList.remove('vr-hidden-field');
            note.textContent = modeHelp[mode] || '';
        }

        modeSelect.addEventListener('change', syncModeFields);
        syncModeFields();
    });

    document.querySelectorAll('.vr-page form[action*="verification-routing/mappings"]').forEach(function (form) {
        const sourceType = form.querySelector('select[name="source_type"]');
        const verifierType = form.querySelector('select[name="verifier_type"]');
        if (!sourceType || !verifierType) return;

        const sourceFields = {
            user: form.querySelector('select[name="source_user_id"]')?.closest('label'),
            role: form.querySelector('select[name="source_role_id"]')?.closest('label'),
            team: form.querySelector('select[name="source_team_user_id"]')?.closest('label'),
        };
        const verifierFields = {
            user: form.querySelector('select[name="verifier_user_id"]')?.closest('label'),
            role: form.querySelector('select[name="verifier_role_ids[]"]')?.closest('label'),
        };

        function syncMappingFields() {
            Object.values(sourceFields).forEach(field => field?.classList.add('vr-hidden-field'));
            Object.values(verifierFields).forEach(field => field?.classList.add('vr-hidden-field'));
            sourceFields[sourceType.value]?.classList.remove('vr-hidden-field');
            verifierFields[verifierType.value]?.classList.remove('vr-hidden-field');
        }

        sourceType.addEventListener('change', syncMappingFields);
        verifierType.addEventListener('change', syncMappingFields);
        syncMappingFields();
    });
});
</script>
@endsection
