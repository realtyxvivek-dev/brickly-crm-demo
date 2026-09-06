<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:builders,name',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|in:active,inactive',
            'contacts' => 'required|array|min:1|max:5',
            'contacts.*.person_name' => 'required|string|max:255',
            'contacts.*.mobile_number' => 'required|string|max:15',
            'contacts.*.whatsapp_number' => 'nullable|string|max:15',
            'contacts.*.whatsapp_same_as_mobile' => 'nullable|boolean',
            'contacts.*.preferred_mode' => 'nullable|in:call,whatsapp,both',
            'contacts.*.is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Builder name is required.',
            'name.unique' => 'Builder name already exists.',
            'logo.image' => 'Logo must be an image file.',
            'logo.max' => 'Logo size must not exceed 2MB.',
            'description.max' => 'Description must not exceed 5000 characters.',
            'contacts.required' => 'At least one contact is required.',
            'contacts.max' => 'Maximum 5 contacts allowed.',
            'contacts.*.person_name.required' => 'Contact person name is required.',
            'contacts.*.mobile_number.required' => 'Mobile number is required.',
        ];
    }
}
