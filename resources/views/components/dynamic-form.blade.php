@props(['form'])

@php
    $formData = is_string($form) ? \App\Models\DynamicForm::where('slug', $form)->orWhere('id', $form)->with('fields')->first() : $form;
    if (!$formData || !$formData->is_active) {
        return;
    }
    $fields = $formData->fields->sortBy('order');
    $fieldsBySection = $fields->groupBy('section');
@endphp

@if($formData)
<form action="{{ $formData->settings['submit_url'] ?? '#' }}" method="POST" class="dynamic-form" data-form-id="{{ $formData->id }}" data-form-slug="{{ $formData->slug }}">
    @csrf
    
    @foreach($fieldsBySection as $sectionName => $sectionFields)
        @if($sectionName && $sectionName !== 'default')
            <div class="form-section" style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 2px solid #E5DED4;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-color);">{{ $sectionName }}</h3>
        @endif
        
        @foreach($sectionFields as $field)
            <div class="form-field-wrapper" style="margin-bottom: 20px;">
                <label for="field_{{ $field->id }}" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-color);">
                    {{ $field->label }}
                    @if($field->required)
                        <span style="color: #ef4444;">*</span>
                    @endif
                </label>
                
                @if($field->help_text)
                    <p style="font-size: 12px; color: #666; margin-bottom: 8px;">{{ $field->help_text }}</p>
                @endif
                
                @switch($field->field_type)
                    @case('text')
                    @case('email')
                    @case('number')
                    @case('date')
                        <input 
                            type="{{ $field->field_type }}" 
                            id="field_{{ $field->id }}" 
                            name="{{ $field->field_key }}" 
                            value="{{ $field->default_value ?? '' }}"
                            placeholder="{{ $field->placeholder ?? '' }}"
                            class="form-control"
                            @if($field->required) required @endif
                            @if($field->validation && isset($field->validation['min'])) min="{{ $field->validation['min'] }}" @endif
                            @if($field->validation && isset($field->validation['max'])) max="{{ $field->validation['max'] }}" @endif
                        >
                        @break
                    
                    @case('textarea')
                        <textarea 
                            id="field_{{ $field->id }}" 
                            name="{{ $field->field_key }}" 
                            rows="{{ $field->validation['rows'] ?? 4 }}"
                            placeholder="{{ $field->placeholder ?? '' }}"
                            class="form-control"
                            @if($field->required) required @endif
                            @if($field->validation && isset($field->validation['min'])) minlength="{{ $field->validation['min'] }}" @endif
                            @if($field->validation && isset($field->validation['max'])) maxlength="{{ $field->validation['max'] }}" @endif
                        >{{ $field->default_value ?? '' }}</textarea>
                        @break
                    
                    @case('select')
                        <select 
                            id="field_{{ $field->id }}" 
                            name="{{ $field->field_key }}" 
                            class="form-control"
                            @if($field->required) required @endif
                        >
                            <option value="">-- Select --</option>
                            @if($field->options)
                                @foreach($field->options as $option)
                                    <option value="{{ $option }}" {{ ($field->default_value ?? '') === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            @endif
                        </select>
                        @break
                    
                    @case('radio')
                        @if($field->options)
                            @foreach($field->options as $option)
                                <div style="margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                        <input 
                                            type="radio" 
                                            name="{{ $field->field_key }}" 
                                            value="{{ $option }}"
                                            {{ ($field->default_value ?? '') === $option ? 'checked' : '' }}
                                            @if($field->required) required @endif
                                        >
                                        <span>{{ $option }}</span>
                                    </label>
                                </div>
                            @endforeach
                        @endif
                        @break
                    
                    @case('checkbox')
                        @if($field->options)
                            @foreach($field->options as $option)
                                <div style="margin-bottom: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                        <input 
                                            type="checkbox" 
                                            name="{{ $field->field_key }}[]" 
                                            value="{{ $option }}"
                                        >
                                        <span>{{ $option }}</span>
                                    </label>
                                </div>
                            @endforeach
                        @else
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                                <input 
                                    type="checkbox" 
                                    id="field_{{ $field->id }}" 
                                    name="{{ $field->field_key }}" 
                                    value="1"
                                >
                                <span>{{ $field->label }}</span>
                            </label>
                        @endif
                        @break
                    
                    @case('file')
                        <input 
                            type="file" 
                            id="field_{{ $field->id }}" 
                            name="{{ $field->field_key }}" 
                            class="form-control"
                            @if($field->required) required @endif
                            @if($field->validation && isset($field->validation['accept'])) accept="{{ $field->validation['accept'] }}" @endif
                        >
                        @break
                @endswitch
            </div>
        @endforeach
        
        @if($sectionName && $sectionName !== 'default')
            </div>
        @endif
    @endforeach
    
    <div style="margin-top: 24px;">
        <button type="submit" class="btn btn-brand-gradient" style="padding: 12px 24px; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
            {{ $formData->settings['submit_button_text'] ?? 'Submit' }}
        </button>
    </div>
</form>

<style>
    .dynamic-form .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #E5DED4;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    .dynamic-form .form-control:focus {
        outline: none;
        border-color: #205A44;
        box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.1);
    }
</style>
@endif
