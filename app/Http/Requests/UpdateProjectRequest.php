<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isCrm());
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'project_type' => 'sometimes|in:residential,commercial,mixed',
            'project_status' => 'sometimes|in:prelaunch,under_construction,ready',
            'availability_type' => 'sometimes|in:fresh,resale,both',
            'city' => 'sometimes|string|max:255',
            'area' => 'sometimes|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'land_area_unit' => 'nullable|in:acres,sq_ft',
            'rera_no' => 'nullable|string|max:255',
            'possession_date' => 'nullable|date',
            'project_highlights' => 'nullable|string',
            'configuration_summary' => 'nullable|array',
            'configuration_summary.*' => 'in:studio,1bhk,2bhk,3bhk,4bhk,other',
            'contacts' => 'nullable|array',
            'contacts.primary' => 'required_with:contacts|exists:builder_contacts,id',
            'contacts.secondary' => 'nullable|exists:builder_contacts,id',
            'contacts.escalation' => 'nullable|exists:builder_contacts,id',
        ];
    }
}
