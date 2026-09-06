@php
    $contactId = $contact ? $contact->id : 'new_' . $index;
    $contactKey = $contact ? $contact->id : $index;
@endphp
<div class="contact-row mb-4 p-4 border border-gray-200 rounded-lg" data-contact-id="{{ $contactId }}">
    <div class="flex justify-between mb-2">
        <h4 class="font-medium">Contact {{ $index + 1 }}</h4>
        @if($index > 0 || ($contact && $contact->id))
            <button type="button" class="remove-contact text-red-600 hover:text-red-800">Remove</button>
        @endif
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Person Name <span class="text-red-500">*</span></label>
            <input type="text" name="contacts[{{ $contactKey }}][person_name]" value="{{ old("contacts.$contactKey.person_name", $contact ? $contact->person_name : '') }}" required class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
            <input type="text" name="contacts[{{ $contactKey }}][mobile_number]" value="{{ old("contacts.$contactKey.mobile_number", $contact ? $contact->mobile_number : '') }}" required class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
            <input type="text" name="contacts[{{ $contactKey }}][whatsapp_number]" value="{{ old("contacts.$contactKey.whatsapp_number", $contact ? $contact->whatsapp_number : '') }}" class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Mode</label>
            <select name="contacts[{{ $contactKey }}][preferred_mode]" class="w-full px-3 py-2 bg-white text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="both" {{ old("contacts.$contactKey.preferred_mode", $contact ? $contact->preferred_mode : 'both') === 'both' ? 'selected' : '' }}>Both</option>
                <option value="call" {{ old("contacts.$contactKey.preferred_mode", $contact ? $contact->preferred_mode : 'both') === 'call' ? 'selected' : '' }}>Call</option>
                <option value="whatsapp" {{ old("contacts.$contactKey.preferred_mode", $contact ? $contact->preferred_mode : 'both') === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
            </select>
        </div>
    </div>
    <div class="mt-2">
        <label class="flex items-center">
            <input type="checkbox" name="contacts[{{ $contactKey }}][whatsapp_same_as_mobile]" value="1" {{ old("contacts.$contactKey.whatsapp_same_as_mobile", $contact && $contact->whatsapp_same_as_mobile ? '1' : '') ? 'checked' : '' }} class="mr-2">
            <span class="text-sm text-gray-700">WhatsApp same as mobile</span>
        </label>
        <label class="flex items-center mt-2">
            <input type="checkbox" name="contacts[{{ $contactKey }}][is_active]" value="1" {{ old("contacts.$contactKey.is_active", $contact && $contact->is_active !== false ? '1' : '') ? 'checked' : '' }} class="mr-2">
            <span class="text-sm text-gray-700">Active</span>
        </label>
    </div>
    @if($contact && $contact->id)
        <input type="hidden" name="contacts[{{ $contactKey }}][id]" value="{{ $contact->id }}">
    @endif
</div>
