<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuilderContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        return [
            'person_name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:15',
            'whatsapp_number' => 'nullable|string|max:15',
            'whatsapp_same_as_mobile' => 'nullable|boolean',
            'preferred_mode' => 'nullable|in:call,whatsapp,both',
            'is_active' => 'nullable|boolean',
        ];
    }
}
