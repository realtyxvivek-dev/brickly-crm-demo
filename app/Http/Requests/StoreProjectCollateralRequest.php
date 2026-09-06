<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\CollateralService;

class StoreProjectCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        $collateralService = app(CollateralService::class);
        
        return [
            'category' => 'required|in:brochure,floor_plans,layout_plan,price_sheet,videos,legal_approvals,other',
            'title' => 'required|string|max:255',
            'link' => [
                'required',
                'url',
                function ($attribute, $value, $fail) use ($collateralService) {
                    if (!$collateralService->validateLink($value)) {
                        $fail('The link must be a valid YouTube or Google Drive URL.');
                    }
                },
            ],
            'is_latest' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ];
    }
}
