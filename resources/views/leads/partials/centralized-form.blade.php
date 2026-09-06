@php
    $user = auth()->user();
    $userRole = $user->role->slug ?? 'telecaller';
    
    // Get visible fields based on user role (STRICT VISIBILITY)
    $visibleFields = \App\Models\LeadFormField::active()
        ->visibleToRole($userRole)
        ->orderBy('display_order')
        ->get();
    
    // Group fields by level for display
    $fieldsByLevel = $visibleFields->groupBy('field_level');
    
    // Get existing field values for the lead
    $existingValues = $lead ? $lead->getFormFieldsArray() : [];
@endphp

<div class="centralized-lead-form">
    @if($visibleFields->count() > 0 || $lead)
        <form id="centralizedLeadForm" method="POST" action="{{ route('leads.update', $lead->id) }}">
            @csrf
            @method('PUT')
            
            <!-- Name and Phone Fields (Always visible, editable) -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="lead_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="lead_name"
                               value="{{ old('name', $lead->name) }}"
                               required
                               placeholder="Enter lead name"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                    </div>
                    
                    <div>
                        <label for="lead_phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Mobile Number <span class="text-red-500">*</span>
                        </label>
                        <x-international-phone-input id="lead_phone" :value="$lead->phone" :country="$lead->phone_country_iso ?: 'IN'"
                            input-class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]" required />
                    </div>

                    @if($userRole === 'crm' || $userRole === 'admin')
                    <div>
                        <label for="lead_source" class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                        <select
                            name="source"
                            id="lead_source"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                            @foreach(\App\Models\Lead::sourceOptions() as $value => $label)
                                <option value="{{ $value }}" {{ old('source', \App\Models\Lead::normalizeSource($lead->source)) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
            </div>
            
            @foreach($fieldsByLevel as $level => $levelFields)
                <div class="form-level-section mb-8" data-level="{{ $level }}">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">
                        {{ ucfirst(str_replace('_', ' ', $level)) }} Fields
                        @if($userRole === 'crm' || $userRole === 'admin')
                            <span class="ml-2 text-xs text-blue-600 font-normal">(All Fields Visible)</span>
                        @elseif($level === 'telecaller' && $lead && $lead->form_filled_by_telecaller)
                            <span class="ml-2 text-xs text-green-600 font-normal">(Completed)</span>
                        @elseif($level === 'sales_executive' && $lead && $lead->form_filled_by_executive)
                            <span class="ml-2 text-xs text-green-600 font-normal">(Completed)</span>
                        @endif
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($levelFields as $field)
                            @php
                                // Check if field should be read-only
                                $isReadOnly = false;
                                
                                if ($lead) {
                                    $fieldValueObj = $lead->formFieldValues()->where('field_key', $field->field_key)->first();
                                    
                                    // Manager/Admin/CRM/Sales Head can always edit all fields
                                    if (in_array($userRole, ['sales_manager', 'admin', 'crm', 'sales_head'])) {
                                        $isReadOnly = false; // CRM can edit all fields regardless of who filled them
                                    }
                                    // If field was filled by previous level user, make read-only
                                    elseif ($fieldValueObj && $fieldValueObj->filled_by_user_id && $fieldValueObj->filled_by_user_id != $user->id) {
                                        $filledBy = \App\Models\User::find($fieldValueObj->filled_by_user_id);
                                        if ($filledBy && $filledBy->role) {
                                            $filledByRole = $filledBy->role->slug;
                                            // Telecaller fields filled by telecaller should be read-only for executive
                                            if ($field->field_level === 'telecaller' && $filledByRole === 'telecaller' && $userRole === 'sales_executive') {
                                                $isReadOnly = true;
                                            }
                                        }
                                    }
                                }
                            @endphp
                            
                            <div class="@if($field->field_type === 'textarea') md:col-span-2 @endif">
                                @include('leads.partials.form-field', [
                                    'field' => $field,
                                    'lead' => $lead,
                                    'isReadOnly' => $isReadOnly
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            
            <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                <a href="{{ route('leads.show', $lead->id) }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200 font-medium">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                    Save Requirements
                </button>
            </div>
        </form>
    @else
        <div class="text-center py-12 text-gray-500">
            <p>No fields configured for your role level. Please contact administrator.</p>
        </div>
    @endif
</div>

@push('scripts')
<script src="{{ asset('js/lead-centralized-form.js') }}"></script>
@endpush
