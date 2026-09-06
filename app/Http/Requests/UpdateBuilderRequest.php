<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        $builderId = $this->route('builder')->id ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('builders')->ignore($builderId)],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
