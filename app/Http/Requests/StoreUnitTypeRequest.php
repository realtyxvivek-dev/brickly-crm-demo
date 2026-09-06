<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        return [
            'unit_type' => 'required|string|max:255',
            'area_sqft' => 'required|numeric|min:0.01',
        ];
    }
}
