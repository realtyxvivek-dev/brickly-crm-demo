@php
    $title = $title ?? 'Site Visit';
    $icon = $icon ?? 'fas fa-map-marker-alt';
    $closeHandler = $closeHandler ?? '';
    $formId = $formId ?? 'siteVisitForm';
    $submitHandler = $submitHandler ?? '';
    $projectInputId = $projectInputId ?? 'siteVisitProjectInput';
    $projectOptionsId = $projectOptionsId ?? 'siteVisitProjectOptions';
    $projectHiddenId = $projectHiddenId ?? 'siteVisitProjectHidden';
    $scheduledAtId = $scheduledAtId ?? 'siteVisitScheduledAt';
    $visitSequenceId = $visitSequenceId ?? 'siteVisitVisitSequence';
    $submitLabel = $submitLabel ?? 'Save Visit';
    $submitIcon = $submitIcon ?? 'fas fa-save';
    $cancelLabel = $cancelLabel ?? 'Cancel';
@endphp

<div class="modal-content" style="max-width: 600px; padding: 0; overflow: hidden;">
    <div class="bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-5 rounded-t-2xl w-full">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="{{ $icon }} text-white text-lg"></i>
                </div>
                <h3 class="text-xl font-bold text-white">{{ $title }}</h3>
            </div>
            <button type="button" onclick="{{ $closeHandler }}" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
    </div>

    <form id="{{ $formId }}" onsubmit="{{ $submitHandler }}" class="flex flex-col flex-1 min-h-0">
        <div class="px-6 py-5 overflow-y-auto flex-1" style="max-height: 70vh;">
            <div class="space-y-4">
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-building mr-1" style="color: #205A44;"></i> Project <span style="color: #ef4444;">*</span>
                    </label>
                    <div class="convert-project-dropdown-panel">
                        <div class="convert-project-search-wrap">
                            <input
                                type="text"
                                id="{{ $projectInputId }}"
                                placeholder="Search and select project"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205A44] focus:border-transparent transition"
                            >
                        </div>
                        <div id="{{ $projectOptionsId }}" class="convert-project-options"></div>
                    </div>
                    <input type="hidden" name="project" id="{{ $projectHiddenId }}" required>
                    <small class="text-gray-500">Search project, select from dropdown, ya Enter dabake typed project use karo.</small>
                </div>

                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-list-ol mr-1" style="color: #205A44;"></i> Visit Sequence
                    </label>
                    <select
                        id="{{ $visitSequenceId }}"
                        name="visit_sequence"
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205A44] bg-white transition"
                    >
                        <option value="">Select Visit Sequence</option>
                        <option value="fresh_visit">Fresh Visit</option>
                        <option value="2nd_visit">2nd Visit</option>
                        <option value="3rd_visit">3rd Visit</option>
                    </select>
                    <small class="text-gray-500">Select the visit sequence</small>
                </div>

                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clock mr-1" style="color: #205A44;"></i> Scheduled Date & Time <span style="color: #ef4444;">*</span>
                    </label>
                    <input
                        type="datetime-local"
                        id="{{ $scheduledAtId }}"
                        name="scheduled_at"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205A44] focus:border-transparent transition"
                    >
                    <small class="text-gray-500">Select date and time for the site visit</small>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex gap-3 shrink-0">
            <button
                type="button"
                onclick="{{ $closeHandler }}"
                class="flex-1 h-12 bg-white rounded-lg font-semibold transition-all shadow-sm flex items-center justify-center"
                style="border: 2px solid #205A44; color: #063A1C;"
                onmouseover="this.style.backgroundColor='#f0fdf4';"
                onmouseout="this.style.backgroundColor='white';"
            >
                <i class="fas fa-times mr-2"></i>{{ $cancelLabel }}
            </button>
            <button
                type="submit"
                class="flex-1 h-12 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg flex items-center justify-center"
                style="background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);"
                onmouseover="this.style.opacity='0.95';"
                onmouseout="this.style.opacity='1';"
            >
                <i class="{{ $submitIcon }} mr-2"></i>{{ $submitLabel }}
            </button>
        </div>
    </form>
</div>
