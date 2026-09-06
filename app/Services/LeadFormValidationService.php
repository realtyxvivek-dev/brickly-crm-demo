<?php

namespace App\Services;

use App\Models\LeadFormField;
use Illuminate\Support\Facades\Validator;

class LeadFormValidationService
{
    /**
     * Validate fields for a specific user role level
     */
    public function validateFieldsByLevel(array $data, string $userRole): array
    {
        $visibleFields = LeadFormField::active()
            ->visibleToRole($userRole)
            ->get();

        $rules = [];
        $messages = [];

        foreach ($visibleFields as $field) {
            $fieldRules = [];
            
            if ($field->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Add type-specific validation
            switch ($field->field_type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i';
                    break;
            }

            // Add custom validation rules if configured
            if ($field->validation_rules && is_array($field->validation_rules)) {
                $fieldRules = array_merge($fieldRules, $field->validation_rules);
            }

            $rules[$field->field_key] = $fieldRules;
            $messages[$field->field_key . '.required'] = "The {$field->field_label} field is required.";
        }

        // Always require name and phone
        $rules['name'] = ['required', 'string', 'max:255'];
        $rules['phone'] = ['required', 'string', 'max:20'];

        return Validator::make($data, $rules, $messages)->validate();
    }

    /**
     * Validate a single field based on its configuration
     */
    public function validateField(LeadFormField $field, $value): bool
    {
        if ($field->is_required && empty($value)) {
            return false;
        }

        // Type-specific validation
        switch ($field->field_type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'number':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'time':
                return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value);
        }

        return true;
    }

    /**
     * Validate dependent field rules
     */
    public function validateDependentFields(array $data): array
    {
        $errors = [];

        // Validate CATEGORY → TYPE dependency
        if (isset($data['category']) && isset($data['type'])) {
            $category = $data['category'];
            $type = $data['type'];

            $typeField = LeadFormField::where('field_key', 'type')->first();
            if ($typeField && $typeField->dependent_conditions) {
                $validTypes = $typeField->dependent_conditions[$category] ?? [];
                if (!empty($validTypes) && !in_array($type, $validTypes)) {
                    $errors['type'] = "Selected Type is not valid for the chosen Category.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate follow-up fields are required when status is Follow Up
     */
    public function validateFollowUpRequired(?string $finalStatus, ?string $followUpDate, ?string $followUpTime): array
    {
        $errors = [];

        if ($finalStatus === 'Follow Up') {
            if (empty($followUpDate)) {
                $errors['follow_up_date'] = 'Follow-up date is required when status is Follow Up.';
            }
            if (empty($followUpTime)) {
                $errors['follow_up_time'] = 'Follow-up time is required when status is Follow Up.';
            }
        }

        return $errors;
    }
}
