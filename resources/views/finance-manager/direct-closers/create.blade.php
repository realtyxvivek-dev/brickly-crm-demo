@extends('finance-manager.layout')

@section('title', 'Add Direct Closer')
@section('page_title', 'Add Direct Closer')
@section('page_subtitle', 'Create a new booking request with complete KYC for Admin approval.')

@section('content')
<style>
    .dc-wrap { display:flex; flex-direction:column; gap:16px; }
    .dc-panel { background:#fff; border:1px solid #dbe4ef; border-radius:12px; box-shadow:0 10px 28px rgba(15,23,42,.06); padding:18px; }
    .dc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; }
    .dc-title { margin:0; font-size:22px; font-weight:800; color:#0f172a; }
    .dc-sub { margin:4px 0 0; color:#64748b; font-size:13px; }
    .dc-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
    .dc-section { display:flex; flex-direction:column; gap:12px; margin-top:16px; }
    .dc-section-title { margin:0; color:#0f172a; font-size:15px; font-weight:800; padding-bottom:8px; border-bottom:1px solid #e5edf6; }
    .dc-field { display:flex; flex-direction:column; gap:6px; min-width:0; }
    .dc-field.full { grid-column:1 / -1; }
    .dc-label { font-size:12px; font-weight:800; color:#334155; }
    .dc-input, .dc-select, .dc-textarea { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px 11px; font-size:14px; color:#0f172a; background:#fff; }
    .dc-textarea { min-height:86px; resize:vertical; }
    .dc-check { display:flex; align-items:center; gap:8px; min-height:42px; padding:9px 10px; border:1px solid #dbe4ef; border-radius:8px; background:#f8fafc; }
    .dc-help { color:#64748b; font-size:12px; line-height:1.35; }
    .dc-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }
    .dc-btn { border:1px solid #cbd5e1; border-radius:8px; padding:10px 16px; font-weight:800; cursor:pointer; background:#fff; color:#0f172a; }
    .dc-btn.primary { background:#065f46; color:#fff; border-color:#065f46; }
    .dc-alert { border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:10px; padding:12px 14px; font-size:13px; }
    .dc-field[hidden] { display:none !important; }
    @media (max-width: 1100px) { .dc-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 720px) { .dc-grid { grid-template-columns:1fr; } .dc-actions { flex-direction:column; } }
</style>

@php
    $sections = collect($schema['sections'] ?? []);
    $fieldInputType = function (array $field): string {
        return match ($field['field_type'] ?? 'text') {
            'email' => 'email',
            'number' => 'number',
            'date' => 'date',
            default => 'text',
        };
    };
@endphp

<form class="dc-wrap" method="POST" action="{{ route('finance-manager.direct-closers.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="kyc_form_id" value="{{ $schema['id'] ?? null }}">

    @if($errors->any())
        <div class="dc-alert">{{ $errors->first() }}</div>
    @endif

    <section class="dc-panel">
        <div class="dc-head">
            <div>
                <h2 class="dc-title">Direct Closer Request</h2>
                <p class="dc-sub">KYC complete hoga tabhi request selected Admin ke approval queue me jayegi.</p>
            </div>
        </div>

        <div class="dc-section">
            <h3 class="dc-section-title">Lead & Approval</h3>
            <div class="dc-grid">
                <div class="dc-field">
                    <label class="dc-label">Customer Name *</label>
                    <input class="dc-input" name="lead_name" value="{{ old('lead_name') }}" required>
                </div>
                <div class="dc-field">
                    <label class="dc-label">Phone *</label>
                    <x-international-phone-input name="lead_phone" country-name="lead_phone_country_iso" id="lead_phone" input-class="dc-input" required />
                </div>
                <div class="dc-field">
                    <label class="dc-label">Email</label>
                    <input class="dc-input" type="email" name="lead_email" value="{{ old('lead_email') }}">
                </div>
                <div class="dc-field">
                    <label class="dc-label">Source *</label>
                    <select class="dc-select" name="lead_source" required>
                        @foreach($sources as $key => $label)
                            <option value="{{ $key }}" @selected(old('lead_source', 'other') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-field">
                    <label class="dc-label">Booking Credit To *</label>
                    <select class="dc-select" name="booking_user_id" required>
                        <option value="">Select user</option>
                        @foreach($bookingUsers as $bookingUser)
                            <option value="{{ $bookingUser->id }}" @selected((string) old('booking_user_id') === (string) $bookingUser->id)>{{ $bookingUser->name }} ({{ $bookingUser->role?->name ?? $bookingUser->role?->slug }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-field">
                    <label class="dc-label">Send Approval To Admin *</label>
                    <select class="dc-select" name="approval_admin_id" required>
                        <option value="">Select admin</option>
                        @foreach($approvalAdmins as $admin)
                            <option value="{{ $admin->id }}" @selected((string) old('approval_admin_id') === (string) $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dc-field">
                    <label class="dc-label">Location</label>
                    <input class="dc-input" name="lead_location" value="{{ old('lead_location') }}">
                </div>
                <div class="dc-field">
                    <label class="dc-label">Budget</label>
                    <input class="dc-input" name="lead_budget" value="{{ old('lead_budget') }}">
                </div>
                <div class="dc-field full">
                    <label class="dc-label">Finance Remark</label>
                    <textarea class="dc-textarea" name="finance_remark">{{ old('finance_remark') }}</textarea>
                </div>
            </div>
        </div>
    </section>

    @foreach($sections as $section)
        <section class="dc-panel">
            <div class="dc-section" style="margin-top:0;">
                <h3 class="dc-section-title">{{ $section['label'] ?? $section['section'] ?? 'KYC' }}</h3>
                <div class="dc-grid">
                    @foreach(($section['fields'] ?? []) as $field)
                        @php
                            $key = (string) ($field['field_key'] ?? '');
                            $type = (string) ($field['field_type'] ?? 'text');
                            $required = !empty($field['required']);
                            $label = (string) ($field['label'] ?? $key);
                            $oldValue = old($key);
                            $visibilityCondition = $field['visibility_condition'] ?? null;
                            $fieldAttrs = $visibilityCondition
                                ? ' data-dc-field="' . e($key) . '" data-dc-visibility="' . e(json_encode($visibilityCondition, JSON_UNESCAPED_SLASHES)) . '"'
                                : ' data-dc-field="' . e($key) . '"';
                        @endphp

                        @if(in_array($key, ['kyc_documents', 'proof_photos', 'booking_payment_proofs'], true))
                            <div class="dc-field"{!! $fieldAttrs !!}>
                                <label class="dc-label">{{ $label }} {{ in_array($key, ['kyc_documents', 'proof_photos'], true) ? '*' : '' }}</label>
                                <input class="dc-input" type="file" name="{{ $key }}[]" multiple @if(in_array($key, ['kyc_documents', 'proof_photos'], true)) required @endif>
                                @if(!empty($field['help_text']))<div class="dc-help">{{ $field['help_text'] }}</div>@endif
                            </div>
                            @continue
                        @endif

                        <div class="dc-field {{ in_array($type, ['textarea'], true) ? 'full' : '' }}"{!! $fieldAttrs !!}>
                            @if($type === 'checkbox')
                                <label class="dc-check">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key))>
                                    <span>{{ $label }}</span>
                                </label>
                            @else
                                <label class="dc-label">{{ $label }} {{ $required ? '*' : '' }}</label>
                                @if($type === 'select')
                                    <select class="dc-select" name="{{ $key }}" @if($required) required @endif>
                                        <option value="">Select</option>
                                        @foreach((array) ($field['options'] ?? []) as $option)
                                            <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'textarea')
                                    <textarea class="dc-textarea" name="{{ $key }}" @if($required) required @endif>{{ $oldValue }}</textarea>
                                @else
                                    <input class="dc-input" type="{{ $fieldInputType($field) }}" name="{{ $key }}" value="{{ $oldValue }}" @if($required) required @endif @if(str_ends_with($key, 'date_of_birth')) min="1900-01-01" max="{{ now()->toDateString() }}" @elseif($key === 'actual_closer_date' || $key === 'booking_date') max="{{ now()->toDateString() }}" @endif>
                                @endif
                                @if(!empty($field['help_text']))<div class="dc-help">{{ $field['help_text'] }}</div>@endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    <section class="dc-panel">
        <div class="dc-actions">
            <button type="reset" class="dc-btn">Clear</button>
            <button type="submit" class="dc-btn primary">Send For Admin Approval</button>
        </div>
    </section>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fields = Array.from(document.querySelectorAll('[data-dc-visibility]'));
    const controls = Array.from(document.querySelectorAll('[data-dc-field] input, [data-dc-field] select, [data-dc-field] textarea'));

    controls.forEach((control) => {
        control.dataset.initialRequired = control.required ? '1' : '0';
    });

    function controlValue(name) {
        const control = document.querySelector(`[name="${CSS.escape(name)}"]`);
        if (!control) return '';
        if (control.type === 'checkbox') return control.checked ? '1' : '';
        return control.value || '';
    }

    function matches(condition) {
        return Object.entries(condition || {}).every(([name, expected]) => {
            const values = Array.isArray(expected) ? expected : [expected];
            return values.includes(controlValue(name));
        });
    }

    function applyVisibility() {
        fields.forEach((field) => {
            let visible = true;
            try {
                visible = matches(JSON.parse(field.dataset.dcVisibility || '{}'));
            } catch (error) {
                visible = true;
            }

            field.hidden = !visible;
            field.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !visible;
                control.required = visible && control.dataset.initialRequired === '1';
            });
        });

        const hasJointApplicant = document.querySelector('[name="booking_joint_applicant_available"]')?.checked || false;
        document.querySelectorAll('[data-dc-field^="joint_applicant_"]').forEach((field) => {
            field.hidden = !hasJointApplicant;
            field.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !hasJointApplicant;
                control.required = hasJointApplicant && control.dataset.initialRequired === '1';
            });
        });
    }

    document.querySelector('[name="booking_type"]')?.addEventListener('change', applyVisibility);
    document.querySelector('[name="booking_joint_applicant_available"]')?.addEventListener('change', applyVisibility);
    applyVisibility();
});
</script>
@endsection
