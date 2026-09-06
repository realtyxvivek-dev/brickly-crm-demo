@extends('layouts.app')

@section('title', 'Edit Form Field - Admin')
@section('page-title', 'Edit Form Field: ' . $leadFormField->field_label)
@section('page-subtitle', 'Update field configuration')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @if($errors->any())
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.lead-form-builder.update', $leadFormField) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="field_key" class="block text-sm font-medium text-gray-700 mb-2">
                        Field Key <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="field_key" id="field_key" value="{{ old('field_key', $leadFormField->field_key) }}" required
                           placeholder="e.g., category, preferred_location"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                    <p class="text-xs text-gray-500 mt-1">Unique identifier (lowercase, underscore separated)</p>
                </div>

                <div>
                    <label for="field_label" class="block text-sm font-medium text-gray-700 mb-2">
                        Field Label <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="field_label" id="field_label" value="{{ old('field_label', $leadFormField->field_label) }}" required
                           placeholder="e.g., Category, Preferred Location"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="field_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Field Type <span class="text-red-500">*</span>
                    </label>
                    <select name="field_type" id="field_type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                        <option value="">-- Select Type --</option>
                        <option value="text" {{ old('field_type', $leadFormField->field_type) == 'text' ? 'selected' : '' }}>Text</option>
                        <option value="textarea" {{ old('field_type', $leadFormField->field_type) == 'textarea' ? 'selected' : '' }}>Textarea</option>
                        <option value="select" {{ old('field_type', $leadFormField->field_type) == 'select' ? 'selected' : '' }}>Select/Dropdown</option>
                        <option value="date" {{ old('field_type', $leadFormField->field_type) == 'date' ? 'selected' : '' }}>Date</option>
                        <option value="time" {{ old('field_type', $leadFormField->field_type) == 'time' ? 'selected' : '' }}>Time</option>
                        <option value="number" {{ old('field_type', $leadFormField->field_type) == 'number' ? 'selected' : '' }}>Number</option>
                        <option value="email" {{ old('field_type', $leadFormField->field_type) == 'email' ? 'selected' : '' }}>Email</option>
                        <option value="tel" {{ old('field_type', $leadFormField->field_type) == 'tel' ? 'selected' : '' }}>Phone</option>
                    </select>
                </div>

                <div>
                    <label for="field_level" class="block text-sm font-medium text-gray-700 mb-2">
                        Field Level <span class="text-red-500">*</span>
                    </label>
                    <select name="field_level" id="field_level" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                        <option value="">-- Select Level --</option>
                        <option value="sales_executive" {{ in_array(old('field_level', $leadFormField->field_level), ['telecaller', 'sales_executive']) ? 'selected' : '' }}>Sales Executive</option>
                        <option value="sales_manager" {{ old('field_level', $leadFormField->field_level) == 'sales_manager' ? 'selected' : '' }}>Senior Manager</option>
                    </select>
                </div>
            </div>

            <div id="options_section" class="mb-6" style="display: none;">
                <label for="options" class="block text-sm font-medium text-gray-700 mb-2">
                    Options (one per line)
                </label>
                <textarea name="options[]" id="options" rows="5"
                          placeholder="Option 1&#10;Option 2&#10;Option 3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">{{ old('options') ? implode("\n", old('options', [])) : (is_array($leadFormField->options) ? implode("\n", $leadFormField->options) : '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Enter each option on a new line (for select/dropdown fields)</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700 mb-2">
                        Display Order
                    </label>
                    <input type="number" name="display_order" id="display_order" value="{{ old('display_order', $leadFormField->display_order) }}"
                           placeholder="Auto-calculated if empty"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                </div>

                <div>
                    <label for="placeholder" class="block text-sm font-medium text-gray-700 mb-2">
                        Placeholder Text
                    </label>
                    <input type="text" name="placeholder" id="placeholder" value="{{ old('placeholder', $leadFormField->placeholder) }}"
                           placeholder="e.g., Select an option"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                </div>
            </div>

            <div class="mb-6">
                <label for="help_text" class="block text-sm font-medium text-gray-700 mb-2">
                    Help Text
                </label>
                <textarea name="help_text" id="help_text" rows="2"
                          placeholder="Help text displayed below the field"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">{{ old('help_text', $leadFormField->help_text) }}</textarea>
            </div>

            <div class="flex items-center gap-4 mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_required" value="1" {{ old('is_required', $leadFormField->is_required) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                    <span class="ml-2 text-sm text-gray-700">Required Field</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $leadFormField->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>
            </div>

            <div class="flex justify-end gap-4">
                <a href="{{ route('admin.lead-form-builder.index') }}"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200 font-medium">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                    Update Field
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('field_type').addEventListener('change', function() {
    const optionsSection = document.getElementById('options_section');
    if (this.value === 'select') {
        optionsSection.style.display = 'block';
    } else {
        optionsSection.style.display = 'none';
    }
});

// Trigger on page load if type is already select
const fieldType = document.getElementById('field_type').value;
if (fieldType === 'select') {
    document.getElementById('options_section').style.display = 'block';
}

// Handle options textarea - convert to array format on submit
document.querySelector('form').addEventListener('submit', function(e) {
    const optionsTextarea = document.getElementById('options');
    if (optionsTextarea && fieldType === 'select') {
        const lines = optionsTextarea.value.split('\n').filter(line => line.trim() !== '');
        // Create hidden inputs for each option
        lines.forEach((option, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `options[${index}]`;
            input.value = option.trim();
            this.appendChild(input);
        });
    }
});
</script>
@endsection
