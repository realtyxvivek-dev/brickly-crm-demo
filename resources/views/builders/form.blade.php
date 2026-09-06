@extends('layouts.app')

@section('title', ($builder ? 'Edit Builder' : 'Create Builder') . ' - ' . brand_name())
@section('page-title', $builder ? 'Edit Builder' : 'Create Builder')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ $builder ? route('builders.update', $builder) : route('builders.store') }}" enctype="multipart/form-data">
            @csrf
            @if($builder)
                @method('PUT')
            @endif
            
            @if(isset($return_to) && $return_to === 'project_form')
                <input type="hidden" name="return_to" value="project_form">
            @endif

            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Basic Info -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Builder Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $builder ? $builder->name : '') }}" required
                           class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="mb-4">
                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                    <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/jpg,image/webp"
                           class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                    @if($builder && $builder->logo)
                        <div class="mt-2">
                            <img src="{{ $builder->logo_url }}" alt="Current logo" class="h-20 w-20 rounded object-cover">
                        </div>
                    @endif
                    <p class="mt-1 text-sm text-gray-500">Max 2MB. Formats: JPG, PNG, WebP</p>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="description" rows="6" maxlength="5000"
                              class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $builder ? $builder->description : '') }}</textarea>
                    <p class="mt-1 text-sm text-gray-500"><span id="word-count">0</span> / 1000 words</p>
                </div>

                <div class="mb-4">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" class="w-full px-4 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg">
                        <option value="active" {{ old('status', $builder ? $builder->status : 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $builder ? $builder->status : 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Contacts Section -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Builder Contacts <span class="text-red-500">*</span> (Min: 1, Max: 5)</h3>
                <div id="contacts-container">
                    @if($builder && $builder->contacts->count() > 0)
                        @foreach($builder->contacts as $index => $contact)
                            @include('builders.partials.contact-row', ['index' => $index, 'contact' => $contact])
                        @endforeach
                    @else
                        @include('builders.partials.contact-row', ['index' => 0, 'contact' => null])
                    @endif
                </div>
                <button type="button" id="add-contact" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm font-medium">+ Add Contact</button>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="{{ isset($return_to) && $return_to === 'project_form' ? route('projects.create') : route('builders.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
                    {{ $builder ? 'Update' : 'Create' }} Builder
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Count existing contacts (including inactive) for proper indexing
let contactCount = {{ $builder && $builder->contacts->count() > 0 ? $builder->contacts->count() : 1 }};
const maxContacts = 5;

document.getElementById('add-contact').addEventListener('click', function() {
    if (contactCount >= maxContacts) {
        alert('Maximum 5 contacts allowed');
        return;
    }
    
    const container = document.getElementById('contacts-container');
    const newIndex = 'new_' + Date.now();
    const newRow = document.createElement('div');
    newRow.className = 'contact-row mb-4 p-4 border border-gray-200 rounded-lg';
    newRow.setAttribute('data-contact-id', newIndex);
    newRow.innerHTML = `
        <div class="flex justify-between mb-2">
            <h4 class="font-medium">Contact ${contactCount + 1}</h4>
            <button type="button" class="remove-contact text-red-600 hover:text-red-800">Remove</button>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Person Name <span class="text-red-500">*</span></label>
                <input type="text" name="contacts[${newIndex}][person_name]" required class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
                <input type="text" name="contacts[${newIndex}][mobile_number]" required class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                <input type="text" name="contacts[${newIndex}][whatsapp_number]" class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Mode</label>
                <select name="contacts[${newIndex}][preferred_mode]" class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="both">Both</option>
                    <option value="call">Call</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>
        </div>
        <div class="mt-2">
            <label class="flex items-center">
                <input type="checkbox" name="contacts[${newIndex}][whatsapp_same_as_mobile]" value="1" class="mr-2">
                <span class="text-sm text-gray-700">WhatsApp same as mobile</span>
            </label>
            <label class="flex items-center mt-2">
                <input type="checkbox" name="contacts[${newIndex}][is_active]" value="1" checked class="mr-2">
                <span class="text-sm text-gray-700">Active</span>
            </label>
        </div>
    `;
    container.appendChild(newRow);
    contactCount++;
    
    // Add remove functionality
    newRow.querySelector('.remove-contact').addEventListener('click', function() {
        const totalContacts = container.querySelectorAll('.contact-row').length;
        if (totalContacts <= 1) {
            alert('At least one contact is required');
            return;
        }
        newRow.remove();
        contactCount--;
    });
});

// Add remove functionality to existing contact rows
document.querySelectorAll('.remove-contact').forEach(button => {
    button.addEventListener('click', function() {
        const row = this.closest('.contact-row');
        const container = document.getElementById('contacts-container');
        const totalContacts = container.querySelectorAll('.contact-row').length;
        if (totalContacts <= 1) {
            alert('At least one contact is required');
            return;
        }
        row.remove();
        contactCount--;
});

// Word count for description
document.getElementById('description').addEventListener('input', function() {
    const text = this.value;
    const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
    document.getElementById('word-count').textContent = wordCount;
});
</script>
@endsection
