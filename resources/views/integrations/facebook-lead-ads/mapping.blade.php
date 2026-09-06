@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $sourceAutomationRoutePrefix = request()->routeIs('ad-manager.*') ? 'ad-manager.automation.' : 'admin.automation.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Field Mapping - Facebook Lead Ads - ' . brand_name())
@section('page-title', 'Facebook Lead Ads – Mapping')

@section('header-actions')
    <a href="{{ route($fbLeadAdsRoutePrefix . 'forms') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to forms
    </a>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Map form fields</h2>
        <p class="text-gray-600 text-sm mb-4">Form: <strong>{{ $fbForm->form_name ?: $fbForm->form_id }}</strong>. Map each Facebook field to a CRM field (or "meta" for extra data).</p>

        <div class="mb-4 flex items-center justify-between flex-wrap gap-2">
            <span class="text-sm text-gray-600">Need a new CRM field?</span>
            <button type="button" id="btn-add-custom-field" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                <i class="fas fa-plus mr-1"></i> Add custom field
            </button>
        </div>

        <form id="mapping-form">
            @csrf
            <input type="hidden" name="fb_form_id" value="{{ $fbForm->id }}">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 font-medium text-gray-700">Facebook field</th>
                        <th class="text-left py-2 font-medium text-gray-700">CRM field</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fieldNames as $fbName)
                    @php $meta = $fieldMeta[$fbName] ?? ['pretty_label' => $fbName, 'normalized_key' => $fbName, 'can_auto_create' => false]; @endphp
                    <tr class="border-b border-gray-100">
                        <td class="py-2 text-gray-800">
                            <div class="font-medium">{{ $meta['pretty_label'] }}</div>
                            @if($meta['pretty_label'] !== $fbName)
                                <div class="text-xs text-gray-400 mt-0.5">{{ $fbName }}</div>
                            @endif
                        </td>
                        <td class="py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <select name="mapping[{{ $fbName }}]"
                                        class="crm-field-select w-full max-w-xs px-3 py-1.5 border border-gray-300 rounded-lg"
                                        data-field-name="{{ $fbName }}"
                                        data-field-label="{{ $meta['pretty_label'] }}"
                                        data-field-key="{{ $meta['normalized_key'] }}">
                                    @foreach($crmKeys as $key)
                                        <option value="{{ $key }}" {{ ($currentMapping[$fbName] ?? '') === $key ? 'selected' : '' }}>{{ $key }}</option>
                                    @endforeach
                                </select>
                                @if($meta['can_auto_create'])
                                    <button type="button"
                                            class="quick-create-field px-3 py-1.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition"
                                            data-field-name="{{ $fbName }}"
                                            data-field-key="{{ $meta['normalized_key'] }}"
                                            data-field-label="{{ $meta['pretty_label'] }}">
                                        <i class="fas fa-magic mr-1"></i> Create & map
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if(($automationRules ?? collect())->isNotEmpty())
                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <label for="automation_rule_id" class="block text-sm font-semibold text-slate-900">Lead automation</label>
                    <p class="mt-1 text-xs text-slate-600">Save ke baad ye form selected automation me automatically add ho jayega.</p>
                    <select id="automation_rule_id" name="automation_rule_id" required class="mt-3 w-full rounded-lg border border-emerald-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <option value="">Select automation</option>
                        @foreach($automationRules as $automation)
                            <option value="{{ $automation->id }}" @selected(($currentAutomationRule?->id ?? null) === $automation->id)>{{ $automation->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Active Facebook automation available nahi hai. Form mapping save ho jayegi; automation baad me create karni hogi.
                </div>
            @endif
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium">Save & Enable</button>
                <a href="{{ route($fbLeadAdsRoutePrefix . 'forms') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Modal: Add custom field -->
    <div id="custom-field-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add custom CRM field</h3>
            <p class="text-sm text-gray-600 mb-3">Create a new field key to use in mapping (e.g. job_title, budget). Use letters, numbers and underscores only.</p>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Field key</label>
                <input type="text" id="custom-field-key" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="e.g. job_title" maxlength="50" autocomplete="off">
                <p id="custom-field-key-error" class="text-red-600 text-xs mt-1 hidden"></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Label (optional)</label>
                <input type="text" id="custom-field-label" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Display name" maxlength="100" autocomplete="off">
            </div>
            <div class="flex gap-2 justify-end">
                <button type="button" id="custom-field-cancel" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">Cancel</button>
                <button type="button" id="custom-field-save" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Add field</button>
            </div>
        </div>
    </div>
</div>

<script>
function extractErrorMessage(data, fallback) {
    if (data && typeof data.message === 'string' && data.message.trim() !== '') {
        if (data.errors && typeof data.errors === 'object') {
            var firstKey = Object.keys(data.errors)[0];
            var firstError = firstKey ? data.errors[firstKey] : null;
            if (Array.isArray(firstError) && firstError.length) {
                return firstError[0];
            }
        }
        return data.message;
    }
    return fallback;
}

document.getElementById('mapping-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var fd = new FormData(form);
    var mapping = {};
    fd.forEach(function(value, key) {
        if (key.startsWith('mapping[')) {
            var name = key.replace('mapping[', '').replace(']', '');
            mapping[name] = value;
        }
    });
    fetch('{{ route($fbLeadAdsRoutePrefix . "save-mapping") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({
            fb_form_id: form.querySelector('input[name="fb_form_id"]').value,
            automation_rule_id: form.querySelector('[name="automation_rule_id"]')?.value || null,
            mapping: mapping,
            _token: document.querySelector('input[name="_token"]').value
        })
    }).then(function(r) {
        return r.json().then(function(data) {
            if (!r.ok) {
                throw new Error(extractErrorMessage(data, 'Save failed'));
            }
            return data;
        });
    }).then(function(data) {
        if (data.success) {
            alert(data.message || 'Saved.');
            window.location.href = data.redirect || '{{ route($fbLeadAdsRoutePrefix . "index") }}';
        } else {
            alert(extractErrorMessage(data, 'Save failed'));
        }
    }).catch(function(error) { alert(error.message || 'Save failed'); });
});

(function() {
    var modal = document.getElementById('custom-field-modal');
    var btnOpen = document.getElementById('btn-add-custom-field');
    var btnCancel = document.getElementById('custom-field-cancel');
    var btnSave = document.getElementById('custom-field-save');
    var inputKey = document.getElementById('custom-field-key');
    var inputLabel = document.getElementById('custom-field-label');
    var errEl = document.getElementById('custom-field-key-error');

    function showModal() {
        modal.classList.remove('hidden');
        inputKey.value = '';
        inputLabel.value = '';
        errEl.classList.add('hidden');
        errEl.textContent = '';
        setTimeout(function() { inputKey.focus(); }, 100);
    }
    function hideModal() {
        modal.classList.add('hidden');
    }

    btnOpen.addEventListener('click', showModal);
    btnCancel.addEventListener('click', hideModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) hideModal();
    });

    function addOptionToAllSelects(value, label) {
        label = label || value;
        document.querySelectorAll('.crm-field-select').forEach(function(sel) {
            if (sel.querySelector('option[value="' + value + '"]')) return;
            var opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            sel.appendChild(opt);
        });
    }

    function createFieldAndMap(fieldKey, fieldLabel, targetSelect, triggerButton) {
        if (!fieldKey) {
            return;
        }

        if (triggerButton) {
            triggerButton.disabled = true;
        }

        fetch('{{ route($fbLeadAdsRoutePrefix . "custom-field") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ field_key: fieldKey, label: fieldLabel, _token: document.querySelector('input[name="_token"]').value })
        }).then(function(r) {
            return r.json().then(function(data) {
                if (!r.ok || !data.success) {
                    throw new Error(extractErrorMessage(data, 'Could not add field.'));
                }
                return data;
            });
        }).then(function(data) {
            if (triggerButton) {
                triggerButton.disabled = false;
            }

            var createdFieldKey = data.field_key || fieldKey;
            var createdFieldLabel = data.label || fieldLabel || createdFieldKey;
            addOptionToAllSelects(createdFieldKey, createdFieldLabel);
            if (targetSelect) {
                targetSelect.value = createdFieldKey;
            }
            if (triggerButton) {
                triggerButton.remove();
            }
            window.alert(data.message || 'Custom field added.');
        }).catch(function(error) {
            if (triggerButton) {
                triggerButton.disabled = false;
            }
            window.alert(error.message || 'Request failed.');
        });
    }

    btnSave.addEventListener('click', function() {
        var key = (inputKey.value || '').trim().replace(/\s+/g, '_').replace(/[^a-z0-9_]/gi, '');
        if (!key) {
            errEl.textContent = 'Enter a field key (letters, numbers, underscores).';
            errEl.classList.remove('hidden');
            return;
        }
        key = key.toLowerCase();
        errEl.classList.add('hidden');
        btnSave.disabled = true;
        fetch('{{ route($fbLeadAdsRoutePrefix . "custom-field") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ field_key: key, label: (inputLabel.value || '').trim() || key, _token: document.querySelector('input[name="_token"]').value })
        }).then(function(r) {
            return r.json().then(function(data) {
                if (!r.ok || !data.success) {
                    throw new Error(extractErrorMessage(data, 'Could not add field.'));
                }
                return data;
            });
        }).then(function(data) {
            btnSave.disabled = false;
            var createdFieldKey = data.field_key || key;
            var createdFieldLabel = data.label || (inputLabel.value || '').trim() || createdFieldKey;
            addOptionToAllSelects(createdFieldKey, createdFieldLabel);
            hideModal();
            window.alert((data.message || 'Custom field added.') + ' Field key: ' + createdFieldKey);
        }).catch(function(error) {
            btnSave.disabled = false;
            errEl.textContent = error.message || 'Request failed.';
            errEl.classList.remove('hidden');
        });
    });

    document.querySelectorAll('.quick-create-field').forEach(function(button) {
        button.addEventListener('click', function() {
            var row = button.closest('tr');
            var select = row ? row.querySelector('.crm-field-select') : null;
            var fieldKey = button.getAttribute('data-field-key') || '';
            var fieldLabel = button.getAttribute('data-field-label') || fieldKey;
            createFieldAndMap(fieldKey, fieldLabel, select, button);
        });
    });
})();
</script>
@endsection
