<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        return [
            'bsp_per_sqft' => 'required|numeric|min:0',
            'price_rounding_rule' => 'nullable|in:none,nearest_1000,nearest_10000',
        ];
    }
}
