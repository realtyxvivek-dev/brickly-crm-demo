<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\SiteVisit;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class KycFormSchemaService
{
    public const LOCATION_PATH = 'closer.kyc';
    public const FORM_TYPE = 'kyc';

    public function __construct(
        protected DynamicFormService $dynamicFormService
    ) {
    }

    public function getLatestForm(): ?DynamicForm
    {
        return $this->dynamicFormService->getLatestFormByLocation(self::LOCATION_PATH);
    }

    public function getPublishedForm(): ?DynamicForm
    {
        return $this->dynamicFormService->getPublishedFormByLocation(self::LOCATION_PATH);
    }

    public function getResolvedSchema(?int $formId = null): array
    {
        $form = $formId
            ? DynamicForm::with(['fields' => fn ($query) => $query->orderBy('order')])->find($formId)
            : $this->getLatestForm();

        $defaultFields = collect($this->getDefaultFieldDefinitions())->keyBy('field_key');
        $storedFields = collect($form?->fields ?? [])->map(function ($field) {
            return [
                'field_key' => (string) $field->field_key,
                'field_type' => (string) $field->field_type,
                'label' => (string) $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'options' => is_array($field->options) ? $field->options : null,
                'validation' => is_array($field->validation) ? $field->validation : null,
                'required' => (bool) $field->required,
                'order' => (int) $field->order,
                'section' => $field->section ?: 'Additional Information',
                'styles' => is_array($field->styles) ? $field->styles : null,
                'default_value' => $field->default_value,
                'is_system' => (bool) ($field->is_system ?? false),
                'system_binding' => $field->system_binding,
                'is_visible' => $field->is_visible !== false,
                'requires_form_version' => null,
                'visibility_condition' => null,
                'required_if' => null,
                'readonly_if' => null,
                'role_visibility' => null,
            ];
        })->keyBy('field_key');

        $mergedFields = collect();

        foreach ($defaultFields as $fieldKey => $defaultField) {
            $storedField = $storedFields->get($fieldKey);
            $field = $defaultField;

            if ($storedField) {
                // System KYC fields must follow the product-approved schema.
                // Old dynamic-form rows may still contain legacy labels like "Mobile No.".
                $field['placeholder'] = $storedField['placeholder'] ?? $field['placeholder'];
                $field['help_text'] = $storedField['help_text'] ?? $field['help_text'];
                $field['order'] = (int) ($storedField['order'] ?? $field['order']);
                $field['requires_form_version'] = $defaultField['requires_form_version'] ?? null;
                if ($fieldKey === 'booking_unit_type' && !empty($storedField['options'])) {
                    $field['options'] = array_values(array_unique(array_filter(array_map('trim', $storedField['options']))));
                }
            }

            if ($fieldKey === 'joint_applicant_name') {
                $field['required'] = false;
            }

            $mergedFields->push($field);
        }

        $fields = $this->sortFields($mergedFields->values()->all());

        return [
            'id' => $form?->id,
            'name' => $form?->name ?? 'Closer KYC Form',
            'status' => $form?->status ?? 'system',
            'location_path' => self::LOCATION_PATH,
            'fields' => $fields,
            'sections' => $this->groupFieldsBySection($fields),
        ];
    }

    public function groupFieldsBySection(array $fields): array
    {
        return collect($this->sortFields($fields))
            ->filter(fn (array $field) => (bool) ($field['is_visible'] ?? true))
            ->groupBy(fn (array $field) => $field['section'] ?: 'Additional Information')
            ->map(function (Collection $sectionFields, string $sectionLabel) {
                return [
                    'key' => $this->sectionKey($sectionLabel),
                    'label' => $sectionLabel,
                    'fields' => $sectionFields->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function prepareFieldsForPersistence(array $fields): array
    {
        $defaultFields = collect($this->getDefaultFieldDefinitions())->keyBy('field_key');
        $normalized = collect($fields)->map(function (array $field, int $index) use ($defaultFields) {
            $fieldKey = (string) ($field['field_key'] ?? '');

            if ($defaultFields->has($fieldKey)) {
                $default = $defaultFields->get($fieldKey);

                return [
                    'field_key' => $default['field_key'],
                    'field_type' => $default['field_type'],
                    'label' => trim((string) ($field['label'] ?? $default['label'])) ?: $default['label'],
                    'placeholder' => $field['placeholder'] ?? $default['placeholder'],
                    'help_text' => $field['help_text'] ?? $default['help_text'],
                    'options' => $fieldKey === 'booking_unit_type' && !empty($field['options'])
                        ? array_values(array_unique(array_filter(array_map('trim', $field['options']))))
                        : ($default['options'] ?? null),
                    'validation' => $default['validation'] ?? null,
                    'required' => $default['field_key'] === 'joint_applicant_name'
                        ? false
                        : (bool) ($field['required'] ?? $default['required']),
                    'order' => (int) ($field['order'] ?? $index),
                    'section' => trim((string) ($field['section'] ?? $default['section'])) ?: $default['section'],
                    'styles' => is_array($field['styles'] ?? null) ? $field['styles'] : null,
                    'default_value' => $field['default_value'] ?? $default['default_value'],
                    'is_system' => true,
                    'system_binding' => $default['system_binding'],
                    'is_visible' => array_key_exists('is_visible', $field) ? (bool) $field['is_visible'] : true,
                ];
            }

            return [
                'field_key' => $fieldKey,
                'field_type' => (string) ($field['field_type'] ?? 'text'),
                'label' => trim((string) ($field['label'] ?? 'Untitled Field')) ?: 'Untitled Field',
                'placeholder' => $field['placeholder'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'options' => is_array($field['options'] ?? null) ? $field['options'] : null,
                'validation' => is_array($field['validation'] ?? null) ? $field['validation'] : null,
                'required' => (bool) ($field['required'] ?? false),
                'order' => (int) ($field['order'] ?? $index),
                'section' => trim((string) ($field['section'] ?? 'Additional Information')) ?: 'Additional Information',
                'styles' => is_array($field['styles'] ?? null) ? $field['styles'] : null,
                'default_value' => $field['default_value'] ?? null,
                'is_system' => false,
                'system_binding' => null,
                'is_visible' => array_key_exists('is_visible', $field) ? (bool) $field['is_visible'] : true,
            ];
        })->filter(fn (array $field) => $field['field_key'] !== '');

        $byKey = $normalized->keyBy('field_key');

        foreach ($defaultFields as $fieldKey => $defaultField) {
            if ($byKey->has($fieldKey)) {
                continue;
            }

            $byKey->put($fieldKey, $defaultField);
        }

        return collect($this->sortFields($byKey->values()->all()))->values()->map(function (array $field, int $index) {
            $field['order'] = $index;
            unset($field['requires_form_version'], $field['visibility_condition'], $field['required_if'], $field['readonly_if'], $field['role_visibility']);
            return $field;
        })->all();
    }

    public function getValidationRules(bool $isSubmit, ?int $formId = null): array
    {
        $fields = collect($this->getResolvedSchema($formId)['fields']);
        $requiredByField = $fields->mapWithKeys(fn (array $field) => [$field['field_key'] => (bool) ($field['required'] ?? false)])->all();
        $rules = [];

        foreach ($fields as $field) {
            $fieldKey = $field['field_key'];
            $rulePrefix = ($isSubmit && !empty($requiredByField[$fieldKey])) ? 'required' : 'nullable';

            if (!empty($field['is_system'])) {
                $rules[$fieldKey] = $this->systemFieldRule($fieldKey, $rulePrefix);
                continue;
            }

            $rules[$fieldKey] = $this->customFieldRule((string) $field['field_type'], $rulePrefix);

            if (($field['field_type'] ?? '') === 'file') {
                $rules[$fieldKey] = 'nullable|file|max:5120|mimes:jpeg,jpg,png,pdf,webp';
            }
        }

        $rules['kyc_documents'] = 'nullable|array';
        $rules['kyc_documents.*'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
        $rules['proof_photos'] = 'nullable|array';
        $rules['proof_photos.*'] = 'required|image|mimes:jpeg,jpg,png,webp|max:5120';
        $rules['booking_payment_proofs'] = 'nullable|array';
        $rules['booking_payment_proofs.*'] = 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:5120';
        $rules['existing_kyc_documents'] = 'nullable|array';
        $rules['existing_kyc_documents.*'] = 'nullable|string';
        $rules['existing_proof_photos'] = 'nullable|array';
        $rules['existing_proof_photos.*'] = 'nullable|string';
        $rules['existing_booking_payment_proofs'] = 'nullable|array';
        $rules['existing_booking_payment_proofs.*'] = 'nullable|string';
        $rules['replace_kyc_documents'] = 'nullable|boolean';
        $rules['replace_proof_photos'] = 'nullable|boolean';
        $rules['replace_booking_payment_proofs'] = 'nullable|boolean';
        $rules['kyc_form_id'] = 'nullable|integer|exists:dynamic_forms,id';

        return $rules;
    }

    public function getFieldValue(SiteVisit $siteVisit, array $field): mixed
    {
        if (!empty($field['is_system'])) {
            return $this->getBindingValue($siteVisit, (string) ($field['system_binding'] ?? ''));
        }

        return Arr::get((array) $siteVisit->kyc_custom_fields, $field['field_key'], $field['default_value'] ?? null);
    }

    public function buildReadPayload(SiteVisit $siteVisit): array
    {
        $schema = $this->getResolvedSchema($siteVisit->kyc_dynamic_form_id);
        $sections = collect($schema['sections'])->map(function (array $section) use ($siteVisit) {
            $section['fields'] = collect($section['fields'])->map(function (array $field) use ($siteVisit) {
                $field['value'] = $this->getFieldValue($siteVisit, $field);
                return $field;
            })->values()->all();

            return $section;
        })->values()->all();

        return [
            'form_id' => $schema['id'],
            'sections' => $sections,
            'section_remarks' => is_array($siteVisit->kyc_section_remarks) ? $siteVisit->kyc_section_remarks : [],
        ];
    }

    public function extractCustomFieldValues(array $data, array $files, array $schemaFields, array $existing = []): array
    {
        $values = $existing;

        foreach ($schemaFields as $field) {
            if (!empty($field['is_system'])) {
                continue;
            }

            $fieldKey = $field['field_key'];
            $fieldType = (string) ($field['field_type'] ?? 'text');

            if ($fieldType === 'file') {
                if (isset($files[$fieldKey])) {
                    $upload = $files[$fieldKey];
                    if (is_array($upload)) {
                        $stored = [];
                        foreach ($upload as $item) {
                            if ($item instanceof \Illuminate\Http\UploadedFile) {
                                $stored[] = $item->storeAs('public', 'closings/custom/' . time() . '_' . uniqid() . '.' . $item->getClientOriginalExtension());
                                $stored[count($stored) - 1] = str_replace('public/', '', $stored[count($stored) - 1]);
                            }
                        }
                        $values[$fieldKey] = $stored;
                    } elseif ($upload instanceof \Illuminate\Http\UploadedFile) {
                        $stored = $upload->storeAs('public', 'closings/custom/' . time() . '_' . uniqid() . '.' . $upload->getClientOriginalExtension());
                        $values[$fieldKey] = str_replace('public/', '', $stored);
                    }
                }

                continue;
            }

            if ($fieldType === 'checkbox' && !empty($field['options'])) {
                $values[$fieldKey] = array_values(array_filter((array) ($data[$fieldKey] ?? []), fn ($value) => $value !== ''));
                continue;
            }

            if ($fieldType === 'checkbox') {
                $values[$fieldKey] = !empty($data[$fieldKey]);
                continue;
            }

            $values[$fieldKey] = $data[$fieldKey] ?? null;
        }

        return $values;
    }

    public function getDefaultFieldDefinitions(): array
    {
        $fields = [];
        $order = 0;
        $add = function (string $fieldKey, string $fieldType, string $label, string $section, string $binding, bool $required = false, ?string $placeholder = null, ?string $helpText = null, ?array $options = null, ?array $meta = null) use (&$fields, &$order) {
            $fields[] = [
                'field_key' => $fieldKey,
                'field_type' => $fieldType,
                'label' => $label,
                'placeholder' => $placeholder,
                'help_text' => $helpText,
                'options' => $options,
                'validation' => null,
                'required' => $required,
                'order' => $order++,
                'section' => $section,
                'styles' => null,
                'default_value' => null,
                'is_system' => true,
                'system_binding' => $binding,
                'is_visible' => true,
            ] + (array) $meta;
        };

        $primarySection = '1. Sole / First Applicant';
        $bookingSetupSection = 'Booking Setup';
        $jointSection = '2. Second / Joint Applicant / Nominee';
        $unitSection = 'Booking & Unit Details';
        $docsSection = 'Documents';
        $v2 = ['requires_form_version' => 'v2'];

        $add('primary_applicant_name', 'text', 'Name', $primarySection, 'primary_applicant_details.name', true);
        $add('primary_applicant_relation_name', 'text', 'Son/Daughter/Wife Of', $primarySection, 'primary_applicant_details.relation_name', true);
        $add('primary_applicant_date_of_birth', 'date', 'Date Of Birth', $primarySection, 'primary_applicant_details.date_of_birth', true);
        $add('primary_applicant_nationality', 'text', 'Nationality', $primarySection, 'primary_applicant_details.nationality', true);
        $add('primary_applicant_occupation_service', 'checkbox', 'Service', $primarySection, 'primary_applicant_details.occupation_service');
        $add('primary_applicant_occupation_professional', 'checkbox', 'Professional', $primarySection, 'primary_applicant_details.occupation_professional');
        $add('primary_applicant_occupation_housewife', 'checkbox', 'Housewife', $primarySection, 'primary_applicant_details.occupation_housewife');
        $add('primary_applicant_occupation_business', 'checkbox', 'Business', $primarySection, 'primary_applicant_details.occupation_business');
        $add('primary_applicant_resident_indian', 'checkbox', 'Resident Indian', $primarySection, 'primary_applicant_details.resident_indian');
        $add('primary_applicant_resident_non_resident', 'checkbox', 'Non-Resident', $primarySection, 'primary_applicant_details.resident_non_resident');
        $add('primary_applicant_resident_foreign_national', 'checkbox', 'Foreign National Of Indian Origin', $primarySection, 'primary_applicant_details.resident_foreign_national');
        $add('primary_applicant_marital_status_married', 'checkbox', 'Married', $primarySection, 'primary_applicant_details.marital_status_married');
        $add('primary_applicant_marital_status_unmarried', 'checkbox', 'Unmarried', $primarySection, 'primary_applicant_details.marital_status_unmarried');
        $add('primary_applicant_pan_no', 'text', 'PAN No', $primarySection, 'primary_applicant_details.pan_no', true);
        $add('primary_applicant_aadhaar_no', 'text', 'Aadhaar No', $primarySection, 'primary_applicant_details.aadhaar_no', true);
        $add('primary_applicant_address', 'textarea', 'Permanent Address', $primarySection, 'primary_applicant_details.address', true);
        $add('primary_applicant_communication_address', 'textarea', 'Communication Address', $primarySection, 'primary_applicant_details.communication_address', true);
        $add('primary_applicant_city', 'text', 'City', $primarySection, 'primary_applicant_details.city', true);
        $add('primary_applicant_state', 'text', 'State', $primarySection, 'primary_applicant_details.state', true);
        $add('primary_applicant_pin', 'text', 'PIN Code', $primarySection, 'primary_applicant_details.pin', true);
        $add('primary_applicant_email', 'email', 'Email ID', $primarySection, 'primary_applicant_details.email', true);
        $add('primary_applicant_mobile_no', 'text', 'Contact No 1', $primarySection, 'primary_applicant_details.mobile_no', true);
        $add('primary_applicant_tel_no', 'text', 'Contact No 2', $primarySection, 'primary_applicant_details.tel_no');

        $add('booking_type', 'select', 'Booking Type', $bookingSetupSection, 'unit_details.booking_type', true, null, 'Booking type select karne ke baad unit fields usi hisab se open honge.', ['Plot', 'Villa', 'Apartment', 'Commercial'], $v2);
        $add('actual_closer_date', 'date', 'Actual Closer Date', $bookingSetupSection, 'actual_closer_date', true, null, 'Customer booking/closing actually jis date ko hui. Future date allowed nahi hai.');
        $add('actual_closer_backdate_reason', 'textarea', 'Backdate Reason', $bookingSetupSection, 'actual_closer_backdate_reason', false, null, 'Actual closer date 7 din se purani ho to reason required hai.');
        $add('booking_joint_applicant_available', 'checkbox', '2nd Applicant Available?', $bookingSetupSection, 'unit_details.joint_applicant_available', false, null, 'Tick karne par next step me 2nd applicant form open hoga.', null, $v2);

        $add('joint_applicant_name', 'text', 'Name', $jointSection, 'joint_applicant_details.name', false);
        $add('joint_applicant_relation_name', 'text', 'Son/Daughter/Wife Of', $jointSection, 'joint_applicant_details.relation_name');
        $add('joint_applicant_date_of_birth', 'date', 'Date Of Birth', $jointSection, 'joint_applicant_details.date_of_birth');
        $add('joint_applicant_nationality', 'text', 'Nationality', $jointSection, 'joint_applicant_details.nationality');
        $add('joint_applicant_occupation_service', 'checkbox', 'Service', $jointSection, 'joint_applicant_details.occupation_service');
        $add('joint_applicant_occupation_professional', 'checkbox', 'Professional', $jointSection, 'joint_applicant_details.occupation_professional');
        $add('joint_applicant_occupation_housewife', 'checkbox', 'Housewife', $jointSection, 'joint_applicant_details.occupation_housewife');
        $add('joint_applicant_occupation_business', 'checkbox', 'Business', $jointSection, 'joint_applicant_details.occupation_business');
        $add('joint_applicant_resident_indian', 'checkbox', 'Resident Indian', $jointSection, 'joint_applicant_details.resident_indian');
        $add('joint_applicant_resident_non_resident', 'checkbox', 'Non-Resident', $jointSection, 'joint_applicant_details.resident_non_resident');
        $add('joint_applicant_resident_foreign_national', 'checkbox', 'Foreign National Of Indian Origin', $jointSection, 'joint_applicant_details.resident_foreign_national');
        $add('joint_applicant_marital_status_married', 'checkbox', 'Married', $jointSection, 'joint_applicant_details.marital_status_married');
        $add('joint_applicant_marital_status_unmarried', 'checkbox', 'Unmarried', $jointSection, 'joint_applicant_details.marital_status_unmarried');
        $add('joint_applicant_pan_no', 'text', 'PAN No', $jointSection, 'joint_applicant_details.pan_no');
        $add('joint_applicant_address', 'textarea', 'Permanent Address', $jointSection, 'joint_applicant_details.address');
        $add('joint_applicant_communication_address', 'textarea', 'Communication Address', $jointSection, 'joint_applicant_details.communication_address');
        $add('joint_applicant_city', 'text', 'City', $jointSection, 'joint_applicant_details.city');
        $add('joint_applicant_state', 'text', 'State', $jointSection, 'joint_applicant_details.state');
        $add('joint_applicant_pin', 'text', 'PIN Code', $jointSection, 'joint_applicant_details.pin');
        $add('joint_applicant_email', 'email', 'Email ID', $jointSection, 'joint_applicant_details.email');
        $add('joint_applicant_mobile_no', 'text', 'Contact No 1', $jointSection, 'joint_applicant_details.mobile_no');
        $add('joint_applicant_tel_no', 'text', 'Contact No 2', $jointSection, 'joint_applicant_details.tel_no');

        $add('booking_project_name', 'text', 'Project Name', $unitSection, 'unit_details.project_name', false, null, null, null, $v2);
        $add('booking_date', 'date', 'Booking Date', $unitSection, 'unit_details.booking_date', false, null, null, null, $v2);
        $add('booking_deal_type', 'select', 'Deal Type', $unitSection, 'unit_details.deal_type', false, null, null, ['Fresh', 'Resale'], $v2);
        $add('booking_unit_type', 'select', 'Unit Type', $unitSection, 'unit_details.unit_type', false, null, 'Apartment / Commercial ke hisab se options yahan aayenge.', ['1 BHK', '2 BHK', '2 BHK+Study', '2 BHK+Store', '2 BHK+Study+Store', '3 BHK + S', '4 BHK', 'Retail Space', 'Office Space', 'Studio', 'Suites'], [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Apartment', 'Commercial']],
        ]);
        $add('booking_amount_paid', 'number', 'Booking Amount Paid', $unitSection, 'unit_details.booking_amount_paid', false, null, null, null, $v2);
        $add('booking_payment_mode', 'select', 'Payment Mode', $unitSection, 'unit_details.booking_payment_mode', false, null, null, ['Cash', 'Bank', 'UPI', 'Credit Card', 'Cheque'], $v2);
        $add('booking_payment_reference', 'text', 'Payment Reference No', $unitSection, 'unit_details.booking_payment_reference', false, 'UTR / cheque / receipt no', 'Duplicate reference warning milegi, hard block nahi.', null, $v2);
        $add('unit_details_other_charges', 'number', 'Other Charges', $unitSection, 'unit_details.other_charges', false, null, null, null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_discount', 'number', 'Discount', $unitSection, 'unit_details.discount', false, null, null, null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_discount_remark', 'text', 'Discount Remark', $unitSection, 'unit_details.discount_remark', false, null, 'Discount bharne par remark required hai.', null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_final_total', 'number', 'Final Total', $unitSection, 'unit_details.final_total', false, null, 'Auto total: BSP + PLC + Other Charges - Discount.', null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_override_total', 'checkbox', 'Manual Override Total', $unitSection, 'unit_details.override_total', false, null, null, null, $v2);
        $add('unit_details_override_reason', 'textarea', 'Override Reason', $unitSection, 'unit_details.override_reason', false, null, 'Manual total override ke liye reason mandatory hai.', null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_payment_plan', 'select', 'Payment Plan', $unitSection, 'unit_details.payment_plan', false, null, null, ['CLP', 'Flexi', 'DP', 'Special'], [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);

        $add('unit_details_unit_no', 'text', 'Unit No.', $unitSection, 'unit_details.unit_no', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_block_cluster', 'text', 'Block / Tower', $unitSection, 'unit_details.block_cluster', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Apartment', 'Commercial']],
        ]);
        $add('unit_details_floor', 'text', 'Floor', $unitSection, 'unit_details.floor', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Apartment', 'Commercial']],
        ]);
        $add('unit_details_carpet_area_sq_mt', 'text', 'Carpet Area (sq. mt.)', $unitSection, 'unit_details.carpet_area_sq_mt', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_carpet_area_sq_ft', 'text', 'Carpet Area (sq. ft.)', $unitSection, 'unit_details.carpet_area_sq_ft', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_build_up_area_sq_ft', 'text', 'Build Up Area (sq. ft.)', $unitSection, 'unit_details.build_up_area_sq_ft', false, null, null, null, [
            'requires_form_version' => 'v2',
            'visibility_condition' => ['booking_type' => ['Villa', 'Apartment']],
        ]);
        $add('unit_details_super_area_sq_mt', 'text', 'Super Area (sq. mt.)', $unitSection, 'unit_details.super_area_sq_mt', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_super_area_sq_ft', 'text', 'Super Area (sq. ft.)', $unitSection, 'unit_details.super_area_sq_ft', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_basic_sale_price', 'text', 'Basic Sale Price (Rs.) / SQFT', $unitSection, 'unit_details.basic_sale_price', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_plc_amount', 'text', 'PLC Amount (Rs.)', $unitSection, 'unit_details.plc_amount', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['Plot', 'Villa', 'Apartment', 'Commercial']],
        ]);
        $add('unit_details_car_parking_covered', 'checkbox', 'Covered Parking', $unitSection, 'unit_details.car_parking_covered', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_car_parking_open', 'checkbox', 'Open Parking', $unitSection, 'unit_details.car_parking_open', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_club_membership_charges', 'text', 'Club Membership Charges', $unitSection, 'unit_details.club_membership_charges', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_payment_plan_construction_linked', 'checkbox', 'Construction Linked', $unitSection, 'unit_details.payment_plan_construction_linked', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_payment_plan_down_payment', 'checkbox', 'Down Payment', $unitSection, 'unit_details.payment_plan_down_payment', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_payment_plan_other', 'checkbox', 'Other Payment Plan', $unitSection, 'unit_details.payment_plan_other', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);
        $add('unit_details_payment_plan_other_text', 'text', 'Payment Plan Other Details', $unitSection, 'unit_details.payment_plan_other_text', false, null, null, null, [
            'visibility_condition' => ['booking_type' => ['__never__']],
        ]);

        $add('kyc_documents', 'file', 'KYC Documents', $docsSection, 'kyc_documents');
        $add('proof_photos', 'file', 'Proof Photos', $docsSection, 'closer_request_proof_photos');
        $add('booking_payment_proofs', 'file', 'Booking Receipt / Payment Proof', $docsSection, 'booking_payment_proofs', false, null, 'Booking receipt, UTR screenshot ya PDF proof upload karo.', null, $v2);

        return $fields;
    }

    private function sectionSortOrder(?string $section): int
    {
        return match ($section) {
            '1. Sole / First Applicant' => 10,
            'Booking Setup' => 15,
            '2. Second / Joint Applicant / Nominee' => 20,
            'Booking & Unit Details', 'Details Of The Unit' => 30,
            'Documents' => 40,
            default => 50,
        };
    }

    private function fieldSortOrder(array $field): int
    {
        $fieldKey = (string) ($field['field_key'] ?? '');
        $systemOrder = $this->systemFieldSortMap()[$fieldKey] ?? null;
        if ($systemOrder !== null) {
            return $systemOrder;
        }

        return 1000 + (int) ($field['order'] ?? 0);
    }

    private function sortFields(array $fields): array
    {
        usort($fields, function (array $left, array $right) {
            $sectionCompare = $this->sectionSortOrder($left['section'] ?? null) <=> $this->sectionSortOrder($right['section'] ?? null);
            if ($sectionCompare !== 0) {
                return $sectionCompare;
            }

            $fieldCompare = $this->fieldSortOrder($left) <=> $this->fieldSortOrder($right);
            if ($fieldCompare !== 0) {
                return $fieldCompare;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $fields;
    }

    private function systemFieldSortMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $orderedKeys = [
            'primary_applicant_name',
            'primary_applicant_relation_name',
            'primary_applicant_date_of_birth',
            'primary_applicant_nationality',
            'primary_applicant_occupation_service',
            'primary_applicant_occupation_professional',
            'primary_applicant_occupation_housewife',
            'primary_applicant_occupation_business',
            'primary_applicant_resident_indian',
            'primary_applicant_resident_non_resident',
            'primary_applicant_resident_foreign_national',
            'primary_applicant_marital_status_married',
            'primary_applicant_marital_status_unmarried',
            'primary_applicant_pan_no',
            'primary_applicant_aadhaar_no',
            'primary_applicant_address',
            'primary_applicant_communication_address',
            'primary_applicant_city',
            'primary_applicant_state',
            'primary_applicant_pin',
            'primary_applicant_email',
            'primary_applicant_mobile_no',
            'primary_applicant_tel_no',
            'booking_type',
            'actual_closer_date',
            'actual_closer_backdate_reason',
            'booking_joint_applicant_available',
            'joint_applicant_name',
            'joint_applicant_relation_name',
            'joint_applicant_date_of_birth',
            'joint_applicant_nationality',
            'joint_applicant_occupation_service',
            'joint_applicant_occupation_professional',
            'joint_applicant_occupation_housewife',
            'joint_applicant_occupation_business',
            'joint_applicant_resident_indian',
            'joint_applicant_resident_non_resident',
            'joint_applicant_resident_foreign_national',
            'joint_applicant_marital_status_married',
            'joint_applicant_marital_status_unmarried',
            'joint_applicant_pan_no',
            'joint_applicant_address',
            'joint_applicant_communication_address',
            'joint_applicant_city',
            'joint_applicant_state',
            'joint_applicant_pin',
            'joint_applicant_email',
            'joint_applicant_mobile_no',
            'joint_applicant_tel_no',
            'booking_project_name',
            'booking_date',
            'booking_deal_type',
            'booking_unit_type',
            'booking_amount_paid',
            'booking_payment_mode',
            'booking_payment_reference',
            'unit_details_unit_no',
            'unit_details_block_cluster',
            'unit_details_floor',
            'unit_details_carpet_area_sq_mt',
            'unit_details_carpet_area_sq_ft',
            'unit_details_build_up_area_sq_ft',
            'unit_details_super_area_sq_mt',
            'unit_details_super_area_sq_ft',
            'unit_details_basic_sale_price',
            'unit_details_plc_amount',
            'unit_details_other_charges',
            'unit_details_discount',
            'unit_details_discount_remark',
            'unit_details_final_total',
            'unit_details_override_total',
            'unit_details_override_reason',
            'unit_details_payment_plan',
            'unit_details_car_parking_covered',
            'unit_details_car_parking_open',
            'unit_details_club_membership_charges',
            'unit_details_payment_plan_construction_linked',
            'unit_details_payment_plan_down_payment',
            'unit_details_payment_plan_other',
            'unit_details_payment_plan_other_text',
            'kyc_documents',
            'proof_photos',
            'booking_payment_proofs',
        ];

        $map = [];
        foreach ($orderedKeys as $index => $key) {
            $map[$key] = $index;
        }

        return $map;
    }

    private function sectionKey(string $section): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $section), '_'));
    }

    private function getBindingValue(SiteVisit $siteVisit, string $binding): mixed
    {
        if ($binding === '') {
            return null;
        }

        if (!str_contains($binding, '.')) {
            return $siteVisit->{$binding};
        }

        [$root, $nested] = explode('.', $binding, 2);
        $value = Arr::get((array) $siteVisit->{$root}, $nested);

        if ($this->bindingHasValue($value)) {
            return $value;
        }

        return $this->legacyBindingFallback($siteVisit, $binding);
    }

    private function bindingHasValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => $this->bindingHasValue($item))) > 0;
        }

        return filled($value);
    }

    private function legacyBindingFallback(SiteVisit $siteVisit, string $binding): mixed
    {
        return match ($binding) {
            'primary_applicant_details.name' => $siteVisit->customer_name ?: optional($siteVisit->lead)->name,
            'primary_applicant_details.date_of_birth' => optional($siteVisit->customer_dob)?->format('Y-m-d'),
            'primary_applicant_details.pan_no' => $siteVisit->pan_card,
            'primary_applicant_details.aadhaar_no' => $siteVisit->aadhaar_card_no,
            'joint_applicant_details.name' => $siteVisit->nominee_name ?: $siteVisit->second_customer_name,
            default => null,
        };
    }

    private function systemFieldRule(string $fieldKey, string $required): string
    {
        return match ($fieldKey) {
            'primary_applicant_name', 'primary_applicant_relation_name',
            'primary_applicant_nationality', 'primary_applicant_pan_no',
            'primary_applicant_aadhaar_no', 'primary_applicant_city',
            'primary_applicant_state', 'joint_applicant_name', 'joint_applicant_relation_name',
            'joint_applicant_nationality', 'joint_applicant_pan_no',
            'joint_applicant_city', 'joint_applicant_state',
            'booking_project_name', 'booking_deal_type', 'booking_type',
            'booking_unit_type', 'booking_payment_mode', 'booking_payment_reference',
            'unit_details_discount_remark',
            'unit_details_unit_no', 'unit_details_block_cluster', 'unit_details_floor' => $required . '|string|max:255',
            'primary_applicant_address', 'primary_applicant_communication_address',
            'joint_applicant_address', 'joint_applicant_communication_address',
            'unit_details_override_reason', 'actual_closer_backdate_reason' => $required . '|string|max:1000',
            'primary_applicant_date_of_birth', 'joint_applicant_date_of_birth' => $required . '|date|after_or_equal:1900-01-01|before_or_equal:today',
            'booking_date', 'actual_closer_date' => $required . '|date',
            'primary_applicant_email', 'joint_applicant_email' => $required . '|email|max:255',
            'primary_applicant_pin', 'primary_applicant_tel_no', 'primary_applicant_mobile_no',
            'joint_applicant_pin', 'joint_applicant_tel_no', 'joint_applicant_mobile_no',
            'unit_details_carpet_area_sq_mt', 'unit_details_carpet_area_sq_ft', 'unit_details_build_up_area_sq_ft',
            'unit_details_super_area_sq_mt', 'unit_details_super_area_sq_ft',
            'unit_details_basic_sale_price', 'unit_details_plc_amount',
            'unit_details_other_charges', 'unit_details_discount', 'unit_details_final_total',
            'booking_amount_paid',
            'unit_details_club_membership_charges', 'unit_details_payment_plan_other_text' => $required . '|string|max:50',
            'primary_applicant_occupation_service', 'primary_applicant_occupation_professional', 'primary_applicant_occupation_housewife',
            'primary_applicant_occupation_business', 'primary_applicant_resident_indian', 'primary_applicant_resident_non_resident',
            'primary_applicant_resident_foreign_national', 'primary_applicant_marital_status_married',
            'primary_applicant_marital_status_unmarried', 'joint_applicant_occupation_service',
            'joint_applicant_occupation_professional', 'joint_applicant_occupation_housewife',
            'joint_applicant_occupation_business', 'joint_applicant_resident_indian',
            'joint_applicant_resident_non_resident', 'joint_applicant_resident_foreign_national',
            'joint_applicant_marital_status_married', 'joint_applicant_marital_status_unmarried',
            'unit_details_car_parking_covered', 'unit_details_car_parking_open',
            'unit_details_payment_plan_construction_linked', 'unit_details_payment_plan_down_payment',
            'unit_details_payment_plan_other', 'unit_details_override_total',
            'booking_joint_applicant_available' => 'nullable|boolean',
            'kyc_documents', 'proof_photos', 'booking_payment_proofs' => 'nullable|array',
            default => 'nullable|string|max:255',
        };
    }

    private function customFieldRule(string $fieldType, string $required): string
    {
        return match ($fieldType) {
            'email' => $required . '|email|max:255',
            'number' => $required . '|numeric',
            'date' => $required . '|date',
            'textarea' => $required . '|string|max:5000',
            'checkbox' => 'nullable',
            default => $required . '|string|max:1000',
        };
    }
}
