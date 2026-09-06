@php
    $fieldValue = $lead ? $lead->getFormFieldValue($field->field_key) : old($field->field_key, $field->default_value);
    $fieldId = 'field_' . $field->field_key;
    $fieldName = $field->field_key;
    $isReadOnly = $isReadOnly ?? false;
    
    // Check if dependent field has value (for initial disable state)
    $dependentFieldHasValue = false;
    if ($field->dependent_field && $lead) {
        $dependentValue = $lead->getFormFieldValue($field->dependent_field);
        $dependentFieldHasValue = !empty($dependentValue);
    }
@endphp

<div class="form-field-group mb-6" data-field-key="{{ $field->field_key }}" data-field-level="{{ $field->field_level }}">
    @if($field->field_type === 'select')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <select 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            data-dependent-field="{{ $field->dependent_field }}"
            data-dependent-conditions="{{ $field->dependent_conditions ? json_encode($field->dependent_conditions) : '' }}"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly disabled @endif
            @if($field->dependent_field && !$dependentFieldHasValue) disabled @endif
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly || ($field->dependent_field && !$dependentFieldHasValue)) bg-gray-50 cursor-not-allowed @endif">
            <option value="">-- Select {{ $field->field_label }} --</option>
            @if($field->options && is_array($field->options))
                @foreach($field->options as $option)
                    <option value="{{ $option }}" {{ $fieldValue == $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            @endif
        </select>
        
    @elseif($field->field_type === 'textarea')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <textarea 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            rows="3"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly @endif
            placeholder="{{ $field->placeholder ?? '' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly) bg-gray-50 cursor-not-allowed @endif">{{ $fieldValue }}</textarea>
        
    @elseif($field->field_type === 'date')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input 
            type="date" 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly @endif
            placeholder="{{ $field->placeholder ?? '' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly) bg-gray-50 cursor-not-allowed @endif">
            
    @elseif($field->field_type === 'time')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input 
            type="time" 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly @endif
            placeholder="{{ $field->placeholder ?? '' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly) bg-gray-50 cursor-not-allowed @endif">
            
    @elseif($field->field_type === 'number')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input 
            type="number" 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly @endif
            placeholder="{{ $field->placeholder ?? '' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly) bg-gray-50 cursor-not-allowed @endif">
            
    @else
        {{-- Default: text, email, tel --}}
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-2">
            {{ $field->field_label }}
            @if($field->is_required)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input 
            type="{{ $field->field_type }}" 
            name="{{ $fieldName }}" 
            id="{{ $fieldId }}"
            value="{{ $fieldValue }}"
            @if($field->is_required) required @endif
            @if($isReadOnly) readonly @endif
            placeholder="{{ $field->placeholder ?? '' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44] @if($isReadOnly) bg-gray-50 cursor-not-allowed @endif">
    @endif
    
    @if($field->help_text)
        <p class="mt-1 text-xs text-gray-500">{{ $field->help_text }}</p>
    @endif
    
    @if($isReadOnly && $lead)
        @php
            $filledByUser = \App\Models\User::find($lead->formFieldValues()->where('field_key', $field->field_key)->first()?->filled_by_user_id);
        @endphp
        @if($filledByUser)
            <p class="mt-1 text-xs text-gray-400 italic">Filled by {{ $filledByUser->name }} ({{ $filledByUser->role->name ?? 'N/A' }})</p>
        @endif
    @endif
</div>
