@extends('sales-manager.layout')

@section('title', 'Create Site Visit - Senior Manager')
@section('page-title', 'Create Site Visit')

@push('styles')
<style>
    .schedule-shell { max-width: 980px; margin: 0 auto; }
    .form-container { background: #fff; border-radius: 28px; overflow: hidden; border: 1px solid rgba(14, 92, 58, 0.12); box-shadow: 0 28px 60px rgba(10, 61, 37, 0.08); max-width: 980px; margin: 0 auto; padding: 0; }
    .schedule-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 26px 28px; background: linear-gradient(135deg, #e6f4ec 0%, #f5fbf7 100%); border-bottom: 1px solid #d8eadf; }
    .schedule-head-main, .schedule-card-title { display: flex; align-items: center; gap: 14px; }
    .schedule-icon, .schedule-card-icon { width: 52px; height: 52px; border-radius: 16px; background: rgba(16, 107, 67, 0.12); color: #0f6d44; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; }
    .schedule-head h2 { margin: 0; font-size: 1.9rem; font-weight: 700; color: #0b5736; letter-spacing: -0.03em; }
    .schedule-head p { margin: 4px 0 0; color: #5c7266; font-size: 0.95rem; }
    .schedule-badge, .schedule-card-badge { border-radius: 999px; background: #0f6d44; color: #fff; padding: 8px 18px; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .schedule-body { padding: 28px; }
    #siteVisitForm { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 22px; }
    .schedule-primary-card, .schedule-secondary-card { grid-column: 1 / -1; border: 1px solid #dce7df; border-radius: 24px; overflow: hidden; background: #fff; }
    .schedule-card-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 24px 26px; border-bottom: 1px solid #e7efe9; }
    .schedule-card-title h3 { margin: 0; font-size: 1.55rem; color: #153c28; font-weight: 700; letter-spacing: -0.03em; }
    .schedule-card-title p { margin: 4px 0 0; color: #69806f; font-size: 0.92rem; }
    .schedule-card-body, .support-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 22px; padding: 28px; }
    .support-grid { padding-top: 22px; }
    .form-group { margin-bottom: 0; }
    .form-group.form-wide { grid-column: 1 / -1; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 0.95rem; }
    .form-group label .required { color: #ef4444; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px 16px; border: 1px solid #d5dfdb; border-radius: 14px; font-size: 15px; transition: border-color 0.2s, box-shadow 0.2s; background: #fff; color: #1f2937; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #0f6d44; box-shadow: 0 0 0 4px rgba(15, 109, 68, 0.08); }
    .form-group input[readonly] { background: #f8fafc; color: #4b5563; }
    .visit-type-toggle { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #d5dfd8; border-radius: 16px; overflow: hidden; background: #fff; }
    .visit-type-option { border: none; background: transparent; color: #5b685f; padding: 15px 18px; font-size: 1rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: background 0.2s ease, color 0.2s ease; }
    .visit-type-option.active { background: linear-gradient(135deg, #0f6d44 0%, #125b3e 100%); color: #fff; }
    .project-choice-wrap { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
    .project-choice { border: 1px solid #d3ddd5; background: #fff; color: #52665a; border-radius: 999px; padding: 10px 18px; font-size: 0.96rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
    .project-choice.active { background: linear-gradient(135deg, #0f6d44 0%, #125b3e 100%); border-color: #0f6d44; color: #fff; box-shadow: 0 10px 24px rgba(15, 109, 68, 0.18); }
    .reminder-box { display: flex; align-items: flex-start; gap: 14px; padding: 18px 20px; border-radius: 18px; background: #edf6f0; border: 1px solid #d4e6d9; }
    .reminder-box input[type="checkbox"] { margin-top: 3px; width: 18px; height: 18px; accent-color: #0f6d44; }
    .reminder-box strong { display: block; color: #174c32; font-size: 1rem; }
    .reminder-box span { display: block; margin-top: 4px; color: #6a7b70; font-size: 0.9rem; }
    .form-meta-note { margin-top: 8px; color: #6b7280; font-size: 0.82rem; }
    .photo-preview { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .photo-preview-item { position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 2px solid #e0e0e0; }
    .photo-preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .photo-preview-item .remove-photo { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    .form-actions { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: 10px; padding-top: 22px; border-top: 1px solid #edf1ee; }
    .form-actions-copy { color: #6b7280; font-size: 0.9rem; }
    .form-actions-buttons { display: flex; gap: 12px; }
    .btn { padding: 13px 24px; border: none; border-radius: 14px; cursor: pointer; font-size: 15px; font-weight: 700; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #0f6d44 0%, #125b3e 100%); color: white; box-shadow: 0 14px 28px rgba(15, 109, 68, 0.18); }
    .btn-primary:hover { background: linear-gradient(135deg, #0c5d3a 0%, #104d34 100%); transform: translateY(-1px); box-shadow: 0 18px 32px rgba(15, 109, 68, 0.24); }
    .btn-secondary { background: #fff; color: #4b5563; border: 1px solid #d1d5db; }
    .btn-secondary:hover { background: #f9fafb; }
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    @media (max-width: 768px) { .schedule-head, .schedule-body, .schedule-card-head, .schedule-card-body, .support-grid { padding-left: 20px; padding-right: 20px; } .schedule-head, .schedule-card-head { align-items: flex-start; flex-direction: column; } #siteVisitForm, .schedule-card-body, .support-grid { grid-template-columns: 1fr; } .form-actions, .form-actions-buttons { flex-direction: column; align-items: stretch; } }
</style>
@endpush

@section('content')
<div class="schedule-shell">
<div class="form-container">
    <div class="schedule-head">
        <div class="schedule-head-main">
            <div class="schedule-icon">
                <i class="fas fa-location-dot"></i>
            </div>
            <div>
                <h2>Schedule visit</h2>
                <p>Green-themed visit planning flow for ASM.</p>
            </div>
        </div>
        <span class="schedule-badge">Visit</span>
    </div>
    <div class="schedule-body">
    <div id="alertContainer"></div>
    
    @if(isset($dynamicForm) && $dynamicForm)
        <x-dynamic-form :form="$dynamicForm" />
    @else
    <form id="siteVisitForm" enctype="multipart/form-data">
        @csrf

        <div class="schedule-primary-card">
            <div class="schedule-card-head">
                <div class="schedule-card-title">
                    <div class="schedule-card-icon"><i class="fas fa-location-dot"></i></div>
                    <div>
                        <h3>Schedule visit</h3>
                        <p>Choose date, time, project and next visit setup.</p>
                    </div>
                </div>
                <span class="schedule-card-badge">Visit</span>
            </div>
            <div class="schedule-card-body">
                <div class="form-group">
                    <label for="date_of_visit">Visit date <span class="required">*</span></label>
                    <input type="date" id="date_of_visit" name="date_of_visit" required>
                </div>
                <div class="form-group">
                    <label for="visit_time">Visit time <span class="required">*</span></label>
                    <input type="time" id="visit_time" required>
                </div>
                <div class="form-group form-wide">
                    <label>Visit type</label>
                    <div class="visit-type-toggle" id="visitTypeToggle">
                        <button type="button" class="visit-type-option active" data-visit-mode="site_visit"><i class="fas fa-house"></i>Site visit</button>
                        <button type="button" class="visit-type-option" data-visit-mode="office_visit"><i class="fas fa-building"></i>Office visit</button>
                    </div>
                    <input type="hidden" id="visit_mode" name="visit_mode" value="site_visit">
                </div>
                <div class="form-group form-wide">
                    <label for="project">Select projects to visit <span class="required">*</span></label>
                    <div class="project-choice-wrap" id="projectChoiceWrap"></div>
                    <input type="text" id="project" name="project" placeholder="Selected project or custom project name" required>
                </div>
                <div class="form-group form-wide">
                    <label for="property_address">Visit location</label>
                    <input type="text" id="property_address" name="property_address" placeholder="Project site address or landmark">
                </div>
                <div class="form-group form-wide">
                    <label for="visit_notes">Remark</label>
                    <textarea id="visit_notes" name="visit_notes" rows="4" placeholder="Any notes about this visit..."></textarea>
                </div>
                <div class="form-group form-wide">
                    <label for="photos">Photos (Multiple, max 5MB each)</label>
                    <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/jpg,image/png,image/webp">
                    <div class="form-meta-note">You can select multiple images, maximum 5MB each.</div>
                    <div id="photoPreview" class="photo-preview"></div>
                </div>
                <div class="form-group form-wide">
                    <label class="reminder-box" for="create_visit_reminder">
                        <input type="checkbox" id="create_visit_reminder" name="create_visit_reminder" checked>
                        <span><strong>Remind me before visit</strong><span>Keep a calling reminder linked to this visit schedule.</span></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="schedule-secondary-card">
            <div class="schedule-card-head">
                <div class="schedule-card-title">
                    <div class="schedule-card-icon"><i class="fas fa-address-card"></i></div>
                    <div>
                        <h3>CRM details</h3>
                        <p>Required lead fields for pipeline and reporting.</p>
                    </div>
                </div>
            </div>
            <div class="support-grid">
                <div class="form-group">
                    <label for="customer_name">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" maxlength="16" required>
                </div>
                <div class="form-group">
                    <label for="employee">Employee</label>
                    <input type="text" id="employee" name="employee" readonly value="{{ auth()->user()->name }}">
                </div>
                <div class="form-group">
                    <label for="occupation">Occupation</label>
                    <input type="text" id="occupation" name="occupation" placeholder="e.g. IT / Business">
                </div>
                <div class="form-group">
                    <label for="budget_range">Budget Range <span class="required">*</span></label>
                    <select id="budget_range" name="budget_range" required>
                        <option value="">Select Budget Range</option>
                        <option value="Under 50 Lac">Under 50 Lac</option>
                        <option value="50 Lac - 1 Cr">50 Lac - 1 Cr</option>
                        <option value="1 Cr - 2 Cr">1 Cr - 2 Cr</option>
                        <option value="2 Cr - 3 Cr">2 Cr - 3 Cr</option>
                        <option value="Above 3 Cr">Above 3 Cr</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="team_leader">Select TL <span class="required">*</span></label>
                    <select id="team_leader" name="team_leader" required>
                        <option value="">Select Team Leader</option>
                        <option value="Admin">Admin</option>
                        <option value="Alpish">Alpish</option>
                        <option value="Akash">Akash</option>
                        <option value="Omkar">Omkar</option>
                        <option value="Shushank">Shushank</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="property_type">Property Type <span class="required">*</span></label>
                    <select id="property_type" name="property_type" required>
                        <option value="">Select Property Type</option>
                        <option value="Plot/Villa">Plot/Villa</option>
                        <option value="Flat">Flat</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Just Exploring">Just Exploring</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_mode">Payment Mode <span class="required">*</span></label>
                    <select id="payment_mode" name="payment_mode" required>
                        <option value="">Select Payment Mode</option>
                        <option value="Self Fund">Self Fund</option>
                        <option value="Loan">Loan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tentative_period">Tentative Finalisation Period <span class="required">*</span></label>
                    <select id="tentative_period" name="tentative_period" required>
                        <option value="">Select Period</option>
                        <option value="Within 1 Month">Within 1 Month</option>
                        <option value="Within 3 Months">Within 3 Months</option>
                        <option value="Within 6 Months">Within 6 Months</option>
                        <option value="More than 6 Months">More than 6 Months</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lead_type">Lead Type <span class="required">*</span></label>
                    <select id="lead_type" name="lead_type" required>
                        <option value="">Select Lead Type</option>
                        <option value="New Visit">New Visit</option>
                        <option value="Revisited">Revisited</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Prospect">Prospect</option>
                    </select>
                </div>
                <div class="form-group form-wide">
                    <label for="property_name">Property Name</label>
                    <input type="text" id="property_name" name="property_name" placeholder="Property name">
                </div>
            </div>
        </div>

        <input type="hidden" id="scheduled_at" name="scheduled_at" required>
        <input type="hidden" id="lead_id" name="lead_id" value="{{ request('lead_id') }}">
        <input type="hidden" id="prospect_id" name="prospect_id" value="{{ request('prospect_id') }}">

        <div class="form-actions">
            <div class="form-actions-copy">Site visit entry will be added to the CRM pipeline and linked lead activity.</div>
            <div class="form-actions-buttons">
                <a href="{{ route('sales-manager.prospects') }}" class="btn btn-secondary"><i class="fas fa-times"></i>Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-location-dot"></i>Schedule visit</button>
            </div>
        </div>
    </form>
    @endif
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const DEFAULT_VISIT_PROJECTS = ['Eldeco La Vida', 'Omaxe Heights', 'Godrej Reserve', 'ATS Pristine', 'Shalimar Mannat', 'ACE Divino'];

    function getToken() {
        return window.getManagerApiToken ? window.getManagerApiToken() : '{{ session("api_token") }}';
    }

    function showAlert(message, type) {
        const container = document.getElementById('alertContainer');
        if (!container) return;
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => { container.innerHTML = ''; }, 5000);
    }

    document.getElementById('photos')?.addEventListener('change', function(e) {
        const preview = document.getElementById('photoPreview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach((file, index) => {
            if (file.size > 5 * 1024 * 1024) {
                showAlert('File ' + file.name + ' exceeds 5MB limit', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(event) {
                const div = document.createElement('div');
                div.className = 'photo-preview-item';
                div.innerHTML = `<img src="${event.target.result}" alt="Preview"><button type="button" class="remove-photo" onclick="removePhoto(${index})"><i class="fas fa-times"></i></button>`;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });

    function removePhoto(index) {
        const input = document.getElementById('photos');
        const dt = new DataTransfer();
        Array.from(input.files).forEach((file, i) => { if (i !== index) dt.items.add(file); });
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    }

    function formatLocalDateInputValue(dateValue) {
        const date = dateValue instanceof Date ? dateValue : new Date(dateValue);
        if (Number.isNaN(date.getTime())) return '';
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function formatLocalDateTimeInputValue(dateValue) {
        const date = dateValue instanceof Date ? dateValue : new Date(dateValue);
        if (Number.isNaN(date.getTime())) return '';
        return `${formatLocalDateInputValue(date)}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    const dateOfVisitInput = document.getElementById('date_of_visit');
    const visitTimeInput = document.getElementById('visit_time');
    const scheduledAtInput = document.getElementById('scheduled_at');
    if (dateOfVisitInput) dateOfVisitInput.min = formatLocalDateInputValue(new Date());
    if (visitTimeInput && !visitTimeInput.value) visitTimeInput.value = '11:00';

    function syncScheduledAtValue() {
        if (!dateOfVisitInput || !visitTimeInput || !scheduledAtInput) return;
        if (!dateOfVisitInput.value || !visitTimeInput.value) return;
        scheduledAtInput.value = `${dateOfVisitInput.value}T${visitTimeInput.value}`;
    }

    function syncVisitTimeFromScheduledAt(value) {
        if (!value || !visitTimeInput) return;
        const parts = String(value).split('T');
        if (parts[1]) visitTimeInput.value = parts[1].slice(0, 5);
    }

    dateOfVisitInput?.addEventListener('change', syncScheduledAtValue);
    visitTimeInput?.addEventListener('change', syncScheduledAtValue);

    function initVisitTypeToggle() {
        const hiddenInput = document.getElementById('visit_mode');
        const buttons = Array.from(document.querySelectorAll('.visit-type-option'));
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                hiddenInput.value = button.dataset.visitMode || 'site_visit';
                buttons.forEach(item => item.classList.toggle('active', item === button));
            });
        });
    }

    function initProjectChoices(prefilledProject) {
        const wrap = document.getElementById('projectChoiceWrap');
        const input = document.getElementById('project');
        const propertyNameInput = document.getElementById('property_name');
        if (!wrap || !input) return;
        const options = [...new Set([prefilledProject, ...DEFAULT_VISIT_PROJECTS].filter(Boolean))];
        wrap.innerHTML = options.map(project => `<button type="button" class="project-choice" data-project="${project}">${project}</button>`).join('');

        function applySelectedProject(value) {
            input.value = value || '';
            if (propertyNameInput && !propertyNameInput.value) propertyNameInput.value = value || '';
            Array.from(wrap.querySelectorAll('.project-choice')).forEach(button => button.classList.toggle('active', button.dataset.project === value));
        }

        wrap.addEventListener('click', function(event) {
            const button = event.target.closest('.project-choice');
            if (!button) return;
            applySelectedProject(button.dataset.project || '');
        });

        input.addEventListener('input', function() {
            Array.from(wrap.querySelectorAll('.project-choice')).forEach(button => button.classList.toggle('active', button.dataset.project === input.value.trim()));
        });

        applySelectedProject(input.value || prefilledProject || '');
    }

    function prefillSiteVisitFormFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const normalize = value => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        const setValue = (id, value) => {
            const field = document.getElementById(id);
            if (!field || !value) return;
            if (field.tagName === 'SELECT') {
                const match = Array.from(field.options).find(option => normalize(option.value) === normalize(value));
                field.value = match ? match.value : value;
                return;
            }
            field.value = value;
        };

        const prefilledProject = params.get('prefill_project');
        setValue('customer_name', params.get('prefill_name'));
        setValue('phone', params.get('prefill_phone'));
        setValue('project', prefilledProject);
        setValue('property_name', prefilledProject);
        setValue('budget_range', params.get('prefill_budget'));
        setValue('property_type', params.get('prefill_property_type'));
        setValue('lead_type', params.get('prefill_lead_type'));
        setValue('visit_notes', params.get('prefill_notes'));
        setValue('date_of_visit', params.get('prefill_date'));
        setValue('lead_id', params.get('lead_id'));
        setValue('prospect_id', params.get('prospect_id'));
        initProjectChoices(prefilledProject);
        syncScheduledAtValue();
    }

    async function preFillFromMeeting() {
        const urlParams = new URLSearchParams(window.location.search);
        const meetingId = urlParams.get('meeting_id');
        const siteVisitId = urlParams.get('site_visit_id');
        if (siteVisitId) {
            try {
                const response = await fetch(`${API_BASE_URL}/site-visits/${siteVisitId}`, {
                    headers: window.getManagerAuthHeaders
                        ? window.getManagerAuthHeaders({ 'Accept': 'application/json' })
                        : { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data) fillFormFromData(data);
            } catch (error) {
                console.error('Error loading site visit:', error);
            }
            return;
        }
        if (!meetingId) return;
        try {
            const response = await fetch(`${API_BASE_URL}/meetings/${meetingId}`, {
                headers: window.getManagerAuthHeaders
                    ? window.getManagerAuthHeaders({ 'Accept': 'application/json' })
                    : { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (result && result.data) fillFormFromMeetingData(result.data);
        } catch (error) {
            console.error('Error loading meeting:', error);
        }
    }

    function fillFormFromMeetingData(meeting) {
        if (meeting.customer_name) document.getElementById('customer_name').value = meeting.customer_name;
        if (meeting.phone) document.getElementById('phone').value = meeting.phone;
        if (meeting.employee) document.getElementById('employee').value = meeting.employee;
        if (meeting.occupation) document.getElementById('occupation').value = meeting.occupation;
        if (meeting.date_of_visit) document.getElementById('date_of_visit').value = formatLocalDateInputValue(meeting.date_of_visit);
        if (meeting.project) { document.getElementById('project').value = meeting.project; document.getElementById('property_name').value = meeting.project; }
        if (meeting.budget_range) document.getElementById('budget_range').value = meeting.budget_range;
        if (meeting.team_leader) document.getElementById('team_leader').value = meeting.team_leader;
        if (meeting.property_type) document.getElementById('property_type').value = meeting.property_type;
        if (meeting.payment_mode) document.getElementById('payment_mode').value = meeting.payment_mode;
        if (meeting.tentative_period) document.getElementById('tentative_period').value = meeting.tentative_period;
        if (meeting.lead_type) document.getElementById('lead_type').value = meeting.lead_type;
        if (meeting.lead_id) document.getElementById('lead_id').value = meeting.lead_id;
        if (meeting.prospect_id) document.getElementById('prospect_id').value = meeting.prospect_id;
        if (meeting.scheduled_at) {
            const nextDay = new Date(meeting.scheduled_at);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('scheduled_at').value = formatLocalDateTimeInputValue(nextDay);
            syncVisitTimeFromScheduledAt(document.getElementById('scheduled_at').value);
        }
        initProjectChoices(meeting.project);
        syncScheduledAtValue();
    }

    function fillFormFromData(data) {
        if (data.customer_name) document.getElementById('customer_name').value = data.customer_name;
        if (data.phone) document.getElementById('phone').value = data.phone;
        if (data.employee) document.getElementById('employee').value = data.employee;
        if (data.occupation) document.getElementById('occupation').value = data.occupation;
        if (data.date_of_visit) document.getElementById('date_of_visit').value = formatLocalDateInputValue(data.date_of_visit);
        if (data.project) { document.getElementById('project').value = data.project; document.getElementById('property_name').value = data.project; }
        if (data.budget_range) document.getElementById('budget_range').value = data.budget_range;
        if (data.team_leader) document.getElementById('team_leader').value = data.team_leader;
        if (data.property_type) document.getElementById('property_type').value = data.property_type;
        if (data.payment_mode) document.getElementById('payment_mode').value = data.payment_mode;
        if (data.tentative_period) document.getElementById('tentative_period').value = data.tentative_period;
        if (data.lead_type) document.getElementById('lead_type').value = data.lead_type;
        if (data.property_address) document.getElementById('property_address').value = data.property_address;
        if (data.lead_id) document.getElementById('lead_id').value = data.lead_id;
        if (data.scheduled_at) {
            document.getElementById('scheduled_at').value = formatLocalDateTimeInputValue(data.scheduled_at);
            syncVisitTimeFromScheduledAt(document.getElementById('scheduled_at').value);
        }
        initProjectChoices(data.project);
        syncScheduledAtValue();
    }

    document.getElementById('siteVisitForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        syncScheduledAtValue();
        const formData = new FormData(this);
        const token = getToken();
        if (!token) { window.location.href = '{{ route("login") }}'; return; }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const response = await fetch(`${API_BASE_URL}/site-visits`, {
                method: 'POST',
                headers: window.getManagerAuthHeaders
                    ? window.getManagerAuthHeaders({
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    })
                    : { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: formData,
            });
            const result = await response.json();
            if (response.status === 401 || response.status === 403) {
                if (window.handleManagerAuthFailure) {
                    window.handleManagerAuthFailure('create site visit');
                } else {
                    window.location.href = '{{ route("login") }}';
                }
                return;
            }
            if (response.ok && result.success) {
                showAlert('Site visit scheduled successfully!', 'success');
                setTimeout(() => { window.location.href = '{{ route("sales-manager.prospects") }}'; }, 1500);
            } else {
                showAlert(result.message || 'Failed to schedule site visit', 'error');
                if (result.errors) console.error('Validation errors:', result.errors);
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Network error. Please try again.', 'error');
        }
    });

    (function() {
        initVisitTypeToggle();
        prefillSiteVisitFormFromQuery();
        preFillFromMeeting();
    })();
</script>
@endpush
