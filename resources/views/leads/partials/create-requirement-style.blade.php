@php
    $formAction = $formAction ?? route('leads.store');
    $duplicateCheckUrl = $duplicateCheckUrl ?? route('leads.check-duplicate');
    $cancelUrl = $cancelUrl ?? route('leads.index');
    $submitLabel = $submitLabel ?? 'Create Lead';
    $pageTitle = $pageTitle ?? 'Add Lead';
    $pageSubtitle = $pageSubtitle ?? '';
    $selectedOwner = (string) old('assigned_to', $defaultAssignedTo ?? '');
    $ownerPlaceholder = $ownerPlaceholder ?? 'Keep unassigned';
    $duplicateLead = session('duplicate_lead');
    $selectedProjects = array_map('strval', old('preferred_projects', []));
    $selectedInterestedProjects = old('interested_projects', []);
    if (is_string($selectedInterestedProjects)) {
        $decodedInterestedProjects = json_decode($selectedInterestedProjects, true);
        $selectedInterestedProjects = is_array($decodedInterestedProjects) ? $decodedInterestedProjects : [];
    }
    $selectedInterestedProjects = collect($selectedInterestedProjects)
        ->map(function ($project) {
            if (is_array($project)) {
                return trim((string) ($project['name'] ?? ''));
            }

            return trim((string) $project);
        })
        ->filter()
        ->unique()
        ->values()
        ->all();
    $projectOptionNames = collect($interestedProjectOptions ?? [])
        ->merge(collect($projects ?? [])->pluck('name')->all())
        ->map(fn ($name) => trim((string) $name))
        ->filter()
        ->unique()
        ->values();
    if ($projectOptionNames->isEmpty()) {
        $projectOptionNames = collect(['Jash Elevate', 'Oro Constella', 'Okas Res.', 'Other']);
    }
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-lead-form.css') }}?v={{ @filemtime(public_path('css/manager-lead-form.css')) ?: time() }}">
<style>
    .manual-lead-create-wrap {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .manual-lead-create-card {
        border: 1px solid #e6ece8;
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff, #fbfcfb);
        padding: 20px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
    }
    .manual-lead-create-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }
    .manual-lead-create-copy h1,
    .manual-lead-create-copy h2 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        color: #111827;
    }
    .manual-lead-create-copy p {
        margin: 8px 0 0;
        max-width: 760px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
    }
    .manual-lead-create-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        padding: 7px 11px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #0b6b4f;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .manual-lead-alert {
        border-radius: 16px;
        padding: 14px 16px;
        font-size: 14px;
        line-height: 1.6;
    }
    .manual-lead-alert-danger {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #9f1239;
    }
    .manual-lead-alert-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }
    .manual-lead-alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .manual-lead-dupe-box {
        display: none;
        margin-top: 12px;
    }
    .manual-lead-section-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    @media (max-width: 767px) {
        .manual-lead-create-head {
            flex-direction: column;
        }
    }
</style>
@endpush

<div class="manual-lead-create-wrap">
    <div class="manual-lead-create-card">
        <div class="manual-lead-create-head">
            <div class="manual-lead-create-copy">
                <div class="manual-lead-create-kicker">
                    <i class="fas fa-file-signature"></i>
                    Requirement Form Layout
                </div>
                <h1>{{ $pageTitle }}</h1>
                @if(!empty($pageSubtitle))
                    <p>{{ $pageSubtitle }}</p>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="manual-lead-alert manual-lead-alert-danger" style="margin-bottom:16px;">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(is_array($duplicateLead) && ($duplicateLead['duplicate'] ?? false) === true)
            <div class="manual-lead-alert manual-lead-alert-danger" style="margin-bottom:16px;">
                <strong>Lead already exists for this phone number.</strong>
                @if(!empty($duplicateLead['existing_lead_url']))
                    <div style="margin-top:8px;">
                        <a href="{{ $duplicateLead['existing_lead_url'] }}" target="_blank" rel="noopener noreferrer" style="color:inherit;font-weight:700;text-decoration:underline;">Open existing lead</a>
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}" id="manualLeadRequirementCreateForm" novalidate>
            @csrf

            <div class="manager-lead-shell">
                <div class="manager-lead-section">
                    <div class="manager-lead-section-head">
                        <div class="manager-lead-section-icon manager-lead-section-icon-blue"><i class="fas fa-list-ul"></i></div>
                        <div class="manager-lead-section-copy">
                            <h3>Customer requirement</h3>
                        </div>
                        <div class="manual-lead-section-pill">Create Mode</div>
                    </div>
                    <div class="manager-lead-section-body">
                        <div class="manager-lead-grid">
                            <div class="manager-lead-field">
                                <label for="manual_lead_name">Customer name <span class="req">*</span></label>
                                <input class="manager-lead-input" type="text" name="name" id="manual_lead_name" value="{{ old('name') }}" required placeholder="Enter lead name">
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_phone">Phone <span class="req">*</span></label>
                                <div style="display:flex;gap:10px;align-items:center;">
                                    <x-international-phone-input id="manual_lead_phone" input-class="manager-lead-input" required />
                                    <button type="button" class="manager-lead-btn manager-lead-btn-secondary" id="manualLeadDuplicateCheckBtn" style="white-space:nowrap;">Check</button>
                                </div>
                                <div id="manualLeadDuplicateBox" class="manual-lead-dupe-box"></div>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_owner">Owner</label>
                                <select class="manager-lead-select" name="assigned_to" id="manual_lead_owner">
                                    <option value="">{{ $ownerPlaceholder }}</option>
                                    @foreach($users as $owner)
                                        <option value="{{ $owner->id }}" {{ $selectedOwner === (string) $owner->id ? 'selected' : '' }}>
                                            {{ $owner->name }}{{ $owner->role ? ' (' . $owner->role->name . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_email">Email</label>
                                <input class="manager-lead-input" type="email" name="email" id="manual_lead_email" value="{{ old('email') }}" placeholder="Enter email address">
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_source">Source</label>
                                <select class="manager-lead-select" name="source" id="manual_lead_source">
                                    <option value="">Select source</option>
                                    @foreach(\App\Models\Lead::sourceOptions() as $sourceValue => $sourceLabel)
                                        <option value="{{ $sourceValue }}" {{ old('source') === $sourceValue ? 'selected' : '' }}>
                                            {{ $sourceLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_category">Category</label>
                                <select class="manager-lead-select" name="category" id="manual_category">
                                    <option value="">Select category</option>
                                    @foreach(['Residential', 'Commercial', 'Both', 'N.A'] as $option)
                                        <option value="{{ $option }}" {{ old('category') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_preferred_location">Location</label>
                                <select class="manager-lead-select" name="preferred_location" id="manual_preferred_location">
                                    <option value="">Select location</option>
                                    @foreach(['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'] as $option)
                                        <option value="{{ $option }}" {{ old('preferred_location') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_budget">Budget</label>
                                <select class="manager-lead-select" name="budget" id="manual_budget">
                                    <option value="">Select budget</option>
                                    @foreach(['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'] as $option)
                                        <option value="{{ $option }}" {{ old('budget') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_type">Type</label>
                                <select class="manager-lead-select" name="type" id="manual_type">
                                    <option value="">Select type</option>
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_purpose">Purpose</label>
                                <select class="manager-lead-select" name="purpose" id="manual_purpose">
                                    <option value="">Select purpose</option>
                                    @foreach(['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'] as $option)
                                        <option value="{{ $option }}" {{ old('purpose') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_possession">Possession</label>
                                <select class="manager-lead-select" name="possession" id="manual_possession">
                                    <option value="">Select possession</option>
                                    @foreach(['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'] as $option)
                                        <option value="{{ $option }}" {{ old('possession') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_status">Status</label>
                                <select class="manager-lead-select" name="lead_status" id="manual_lead_status">
                                    <option value="">Select status</option>
                                    @foreach(['hot', 'warm', 'cold', 'junk'] as $option)
                                        <option value="{{ $option }}" {{ old('lead_status') === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_lead_quality">Lead quality</label>
                                <select class="manager-lead-select" name="lead_quality" id="manual_lead_quality">
                                    <option value="">Select lead quality</option>
                                    @foreach(['1', '2', '3', '4', '5'] as $option)
                                        <option value="{{ $option }}" {{ old('lead_quality') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field manager-lead-field-full">
                                <label for="manual_project_search">Interested projects</label>
                                <div class="manager-project-dropdown-panel" style="position:static;display:block;box-shadow:none;padding:14px 14px 10px;">
                                    <div class="manager-project-search-wrap">
                                        <input class="manager-lead-input manager-project-search" type="text" id="manual_project_search" placeholder="Search and select interested projects">
                                    </div>
                                    <div class="manager-project-options" id="manual_project_options">
                                        @foreach($projectOptionNames as $projectName)
                                            <button
                                                type="button"
                                                class="project-tag {{ in_array($projectName, $selectedInterestedProjects, true) ? 'selected' : '' }}"
                                                data-project-name="{{ $projectName }}"
                                            >
                                                <span class="project-tag-check"><i class="fas fa-check"></i></span>
                                                <span class="project-tag-text">{{ $projectName }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <input type="hidden" name="interested_projects" id="manual_interested_projects" value='@json($selectedInterestedProjects)'>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="manager-lead-section">
                    <div class="manager-lead-section-head">
                        <div class="manager-lead-section-icon manager-lead-section-icon-green"><i class="fas fa-sliders-h"></i></div>
                        <div class="manager-lead-section-copy">
                            <h3>Requirement snapshot</h3>
                        </div>
                    </div>
                    <div class="manager-lead-section-body">
                        <div class="manager-lead-grid">
                            <div class="manager-lead-field">
                                <label for="manual_customer_job">Customer job</label>
                                <input class="manager-lead-input" type="text" name="customer_job" id="manual_customer_job" value="{{ old('customer_job') }}" placeholder="Enter job / occupation">
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_industry_sector">Industry / sector</label>
                                <select class="manager-lead-select" name="industry_sector" id="manual_industry_sector">
                                    <option value="">Select industry / sector</option>
                                    @foreach(['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other'] as $option)
                                        <option value="{{ $option }}" {{ old('industry_sector') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_buying_frequency">Buying frequency</label>
                                <select class="manager-lead-select" name="buying_frequency" id="manual_buying_frequency">
                                    <option value="">Select buying frequency</option>
                                    @foreach(['Regular', 'Occasional', 'First-time'] as $option)
                                        <option value="{{ $option }}" {{ old('buying_frequency') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_living_city">Living city</label>
                                <input class="manager-lead-input" type="text" name="living_city" id="manual_living_city" value="{{ old('living_city') }}" placeholder="Enter living city">
                            </div>
                            <div class="manager-lead-field">
                                <label for="manual_city_type">City type</label>
                                <select class="manager-lead-select" name="city_type" id="manual_city_type">
                                    <option value="">Select city type</option>
                                    @foreach(['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident'] as $option)
                                        <option value="{{ $option }}" {{ old('city_type') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="manager-lead-field manager-lead-field-full">
                                <label for="manual_manager_remark">Remark</label>
                                <textarea class="manager-lead-textarea" name="manager_remark" id="manual_manager_remark" rows="3" placeholder="Enter remarks or notes...">{{ old('manager_remark') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="manager-lead-footer" style="margin-top:18px;">
                <a href="{{ $cancelUrl }}" class="manager-lead-btn manager-lead-btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="manager-lead-btn manager-lead-btn-primary">{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const typeOptionsByCategory = {
            'Residential': ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
            'Commercial': ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
            'Both': ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
            'N.A': ['N.A']
        };
        const phoneInput = document.getElementById('manual_lead_phone');
        const phoneCountryInput = document.getElementById('manual_lead_phone_country');
        const checkButton = document.getElementById('manualLeadDuplicateCheckBtn');
        const resultBox = document.getElementById('manualLeadDuplicateBox');
        const duplicateCheckUrl = @json($duplicateCheckUrl);
        const categorySelect = document.getElementById('manual_category');
        const typeSelect = document.getElementById('manual_type');
        const existingType = @json(old('type'));
        const projectSearchInput = document.getElementById('manual_project_search');
        const projectOptionsWrap = document.getElementById('manual_project_options');
        const interestedProjectsInput = document.getElementById('manual_interested_projects');
        let selectedInterestedProjects = @json($selectedInterestedProjects);

        if (!phoneInput || !checkButton || !resultBox || !duplicateCheckUrl) {
            return;
        }

        function rebuildTypeOptions() {
            if (!categorySelect || !typeSelect) {
                return;
            }

            const category = categorySelect.value || '';
            const options = typeOptionsByCategory[category] || [];
            typeSelect.innerHTML = '<option value="">Select type</option>' + options.map(function (option) {
                const selected = existingType === option ? ' selected' : '';
                return '<option value=\"' + option.replace(/\"/g, '&quot;') + '\"' + selected + '>' + option + '</option>';
            }).join('');
        }

        function renderDuplicateResult(kind, html) {
            resultBox.style.display = 'block';
            resultBox.className = 'manual-lead-dupe-box manual-lead-alert ' + kind;
            resultBox.innerHTML = html;
        }

        function syncInterestedProjects() {
            if (!projectOptionsWrap || !interestedProjectsInput) {
                return;
            }

            interestedProjectsInput.value = JSON.stringify(selectedInterestedProjects);

            Array.from(projectOptionsWrap.querySelectorAll('[data-project-name]')).forEach(function (button) {
                const projectName = (button.getAttribute('data-project-name') || '').trim();
                const isSelected = selectedInterestedProjects.indexOf(projectName) !== -1;
                button.classList.toggle('selected', isSelected);
            });
        }

        function filterInterestedProjects() {
            if (!projectOptionsWrap || !projectSearchInput) {
                return;
            }

            const searchTerm = projectSearchInput.value.trim().toLowerCase();
            Array.from(projectOptionsWrap.querySelectorAll('[data-project-name]')).forEach(function (button) {
                const projectName = (button.getAttribute('data-project-name') || '').toLowerCase();
                button.style.display = projectName.indexOf(searchTerm) !== -1 ? '' : 'none';
            });
        }

        async function checkDuplicate() {
            const phone = phoneInput.value.trim();
            if (!phone) {
                renderDuplicateResult('manual-lead-alert-warning', 'Phone number enter karke duplicate check karo.');
                return;
            }

            checkButton.disabled = true;
            checkButton.textContent = 'Checking...';

            try {
            const params = new URLSearchParams({ phone });
            if (phoneCountryInput && phoneCountryInput.value) {
                params.set('phone_country_iso', phoneCountryInput.value);
            }
            const response = await fetch(duplicateCheckUrl + '?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Duplicate check failed');
                }

                if (data.duplicate) {
                    const linkHtml = data.existing_lead_url
                        ? '<div style="margin-top:8px;"><a href="' + data.existing_lead_url + '" target="_blank" rel="noopener noreferrer" style="color:inherit;font-weight:700;text-decoration:underline;">Open existing lead</a></div>'
                        : '';
                    renderDuplicateResult('manual-lead-alert-danger', '<strong>Lead already exists for this phone number.</strong>' + linkHtml);
                    return;
                }

                renderDuplicateResult('manual-lead-alert-success', 'No duplicate lead found for this phone number.');
            } catch (error) {
                renderDuplicateResult('manual-lead-alert-warning', error.message || 'Duplicate check failed');
            } finally {
                checkButton.disabled = false;
                checkButton.textContent = 'Check';
            }
        }

        checkButton.addEventListener('click', checkDuplicate);
        phoneInput.addEventListener('blur', function () {
            if (phoneInput.value.trim()) {
                checkDuplicate();
            }
        });

        if (categorySelect && typeSelect) {
            categorySelect.addEventListener('change', rebuildTypeOptions);
            rebuildTypeOptions();
        }

        if (projectOptionsWrap && interestedProjectsInput) {
            projectOptionsWrap.addEventListener('click', function (event) {
                const button = event.target.closest('[data-project-name]');
                if (!button) {
                    return;
                }

                const projectName = (button.getAttribute('data-project-name') || '').trim();
                if (!projectName) {
                    return;
                }

                if (selectedInterestedProjects.indexOf(projectName) === -1) {
                    selectedInterestedProjects.push(projectName);
                } else {
                    selectedInterestedProjects = selectedInterestedProjects.filter(function (selectedName) {
                        return selectedName !== projectName;
                    });
                }

                syncInterestedProjects();
            });

            syncInterestedProjects();
        }

        if (projectSearchInput) {
            projectSearchInput.addEventListener('input', filterInterestedProjects);
        }
    })();
</script>
@endpush
