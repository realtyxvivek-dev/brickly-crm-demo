<?php

namespace App\Services;

use App\Models\GoogleSheetsColumnMapping;
use App\Models\GoogleSheetsConfig;
use Illuminate\Support\Str;

class FieldMappingService
{
    /**
     * Map fields from payload using configuration
     */
    public function mapFields(array $payload, GoogleSheetsConfig $config): array
    {
        $mappedData = [];
        $columnMappings = $config->columnMappings;

        // Build mapping array: sheet_column => lead_field_key
        $mappingArray = [];
        foreach ($columnMappings as $mapping) {
            $mappingArray[$mapping->sheet_column] = $mapping->lead_field_key;
        }

        // Map each field from payload
        foreach ($payload as $key => $value) {
            // Try to find mapping by column letter (if key is column letter)
            if (isset($mappingArray[$key])) {
                $mappedData[$mappingArray[$key]] = $value;
            } else {
                // Try alias matching
                $mappedKey = $this->detectFieldName($key, array_values($mappingArray));
                if ($mappedKey) {
                    $mappedData[$mappedKey] = $value;
                } else {
                    // Store in notes if not mapped
                    if (!isset($mappedData['notes'])) {
                        $mappedData['notes'] = '';
                    }
                    $mappedData['notes'] .= ($mappedData['notes'] ? "\n" : '') . ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
                }
            }
        }

        return $mappedData;
    }

    /**
     * Map fields from Google Apps Script payload (field names, not column letters)
     */
    public function mapFieldsFromPayload(array $payload, GoogleSheetsConfig $config): array
    {
        $mappedData = [];
        $columnMappings = $config->columnMappings;

        // Build reverse mapping: sheet column header => lead_field_key
        $mappingArray = [];
        foreach ($columnMappings as $mapping) {
            // Use field_label as key (this is the sheet column header)
            $mappingArray[strtolower($mapping->field_label ?? '')] = $mapping->lead_field_key;
        }

        // Also try direct field name matching
        foreach ($payload as $key => $value) {
            $normalizedKey = $this->normalizeFieldName($key);
            
            // Try direct mapping
            if (isset($mappingArray[$normalizedKey])) {
                $mappedData[$mappingArray[$normalizedKey]] = $value;
            } else {
                // Try alias matching
                $mappedKey = $this->detectFieldName($key, array_values($mappingArray));
                if ($mappedKey) {
                    $mappedData[$mappedKey] = $value;
                } else {
                    // Store in notes
                    if (!isset($mappedData['notes'])) {
                        $mappedData['notes'] = '';
                    }
                    $mappedData['notes'] .= ($mappedData['notes'] ? "\n" : '') . ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
                }
            }
        }

        return $mappedData;
    }

    /**
     * Auto-detect field name by value or common aliases
     */
    public function detectFieldName(string $fieldName, array $possibleNames): ?string
    {
        $normalized = $this->normalizeFieldName($fieldName);

        // Common field name aliases
        $aliases = [
            'name' => ['full_name', 'customer_name', 'contact_name', 'client_name', 'name'],
            'phone' => ['phone_number', 'mobile', 'contact_number', 'phone', 'mobile_number', 'whatsapp_number'],
            'email' => ['email_address', 'email', 'e_mail'],
            'city' => ['city', 'location', 'are_u_from_lucknow'],
            'state' => ['state', 'province'],
            'property_type' => ['what_kind_of_property', 'property_type', 'property'],
            'budget' => ['budget_approx', 'budget', 'price_range'],
            'requirements' => ['great_what_are_you_looking_for', 'requirements', 'requirement', 'needs'],
            'notes' => ['notes', 'additional_info', 'comments'],
        ];

        // Check aliases
        foreach ($aliases as $standardField => $aliasList) {
            foreach ($aliasList as $alias) {
                if (stripos($normalized, $alias) !== false || stripos($alias, $normalized) !== false) {
                    if (in_array($standardField, $possibleNames)) {
                        return $standardField;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Normalize field name for matching
     */
    public function normalizeFieldName(string $fieldName): string
    {
        return strtolower(trim(str_replace(['_', '-', ' ', '?', '??'], '_', $fieldName)));
    }

    /**
     * Get standard field mappings
     */
    public function getStandardMappings(): array
    {
        return [
            'name' => ['required' => true, 'label' => 'Customer Name'],
            'phone' => ['required' => true, 'label' => 'Phone Number'],
            'email' => ['required' => false, 'label' => 'Email Address'],
            'city' => ['required' => false, 'label' => 'City'],
            'state' => ['required' => false, 'label' => 'State'],
            'property_type' => ['required' => false, 'label' => 'Property Type'],
            'budget' => ['required' => false, 'label' => 'Budget'],
            'requirements' => ['required' => false, 'label' => 'Requirements'],
            'notes' => ['required' => false, 'label' => 'Notes'],
            'source' => ['required' => false, 'label' => 'Lead Source'],
            'preferred_location' => ['required' => false, 'label' => 'Preferred Location'],
            'preferred_size' => ['required' => false, 'label' => 'Preferred Size'],
            'use_end_use' => ['required' => false, 'label' => 'End Use'],
            'possession_status' => ['required' => false, 'label' => 'Possession Status'],
        ];
    }

    /**
     * Get pre-configured form template
     */
    public function getFormTemplate(string $formType): array
    {
        $templates = [
            'website_default_json' => [
                'name' => 'name',
                'email' => 'email',
                'phoneNumber' => 'phone',
                'reason' => 'notes',
                'source.$oid' => 'notes',
                'createdAt.$date' => 'notes',
                'updatedAt.$date' => 'notes',
                '__v' => null,
            ],
            'basic_lead_form' => [
                'name' => 'name',
                'phone' => 'phone',
                'email' => 'email',
                'message' => 'notes',
            ],
            'real_estate_form' => [
                'full_name' => 'name',
                'phone_number' => 'phone',
                'email_id' => 'email',
                'property_type' => 'notes',
                'budget' => 'requirements',
                'city' => 'city',
            ],
            'meta_facebook' => [
                'full_name' => 'name',
                'phone_number' => 'phone',
                'whatsapp_number' => 'notes', // Store in notes
                'email' => 'email',
                'are_u_from_lucknow_??' => 'city',
                'great_,_what_are_you_looking_for' => 'requirements',
                'purpose_for_purchase' => 'use_end_use',
                'what_kind_of_property' => 'property_type',
                'budget_approx_?' => 'budget',
                'when_to_buy' => 'possession_status',
                'meeting_time_to_discuss_in_details' => 'notes', // Store in notes
                'job_title' => 'notes', // Store in notes
            ],
            'google_forms' => [
                'Name' => 'name',
                'Email' => 'email',
                'Phone' => 'phone',
                'Mobile' => 'phone',
            ],
        ];

        return $templates[$formType] ?? [];
    }

    public function getWebsiteIntegrationTemplates(): array
    {
        return [
            'website_default_json' => [
                'label' => 'Website Default JSON',
                'sample_payload' => $this->getWebsiteDefaultSamplePayload(),
                'mappings' => [
                    ['incoming_field' => 'name', 'crm_field' => 'name', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'email', 'crm_field' => 'email', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'phoneNumber', 'crm_field' => 'phone', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'reason', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'source.$oid', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'createdAt.$date', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'updatedAt.$date', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => '__v', 'crm_field' => null, 'is_required' => false, 'default_value' => null, 'is_ignored' => true],
                ],
            ],
            'basic_lead_form' => [
                'label' => 'Basic Lead Form',
                'sample_payload' => [
                    'name' => 'Rahul Kumar',
                    'phone' => '9876543210',
                    'email' => 'rahul@example.com',
                    'message' => 'Interested in callback',
                ],
                'mappings' => [
                    ['incoming_field' => 'name', 'crm_field' => 'name', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'phone', 'crm_field' => 'phone', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'email', 'crm_field' => 'email', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'message', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                ],
            ],
            'real_estate_form' => [
                'label' => 'Real Estate Form',
                'sample_payload' => [
                    'full_name' => 'Priya Singh',
                    'phone_number' => '9876543210',
                    'email_id' => 'priya@example.com',
                    'property_type' => '2 BHK',
                    'budget' => '75 Lakh',
                    'city' => 'Noida',
                ],
                'mappings' => [
                    ['incoming_field' => 'full_name', 'crm_field' => 'name', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'phone_number', 'crm_field' => 'phone', 'is_required' => true, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'email_id', 'crm_field' => 'email', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'property_type', 'crm_field' => 'notes', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'budget', 'crm_field' => 'requirements', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                    ['incoming_field' => 'city', 'crm_field' => 'city', 'is_required' => false, 'default_value' => null, 'is_ignored' => false],
                ],
            ],
        ];
    }

    public function getWebsiteDefaultSamplePayload(): array
    {
        return [
            'name' => 'Ashutosh Dwivedi',
            'email' => 'gal.ashutosh@gmail.com',
            'phoneNumber' => 9560386672,
            'source' => [
                '$oid' => '68ad48960c4bbe906fe97505',
            ],
            'reason' => 'Download',
            'createdAt' => [
                '$date' => '2025-09-27T22:09:20.021Z',
            ],
            'updatedAt' => [
                '$date' => '2025-09-27T22:09:20.021Z',
            ],
            '__v' => 0,
        ];
    }

    public function getWebsiteTargetFields(): array
    {
        return [
            'name' => ['required' => true, 'label' => 'Lead Name', 'type' => 'string', 'storage' => 'lead'],
            'phone' => ['required' => true, 'label' => 'Phone', 'type' => 'phone', 'storage' => 'lead'],
            'email' => ['required' => false, 'label' => 'Email', 'type' => 'email', 'storage' => 'lead'],
            'address' => ['required' => false, 'label' => 'Address', 'type' => 'string', 'storage' => 'lead'],
            'city' => ['required' => false, 'label' => 'City', 'type' => 'string', 'storage' => 'lead'],
            'state' => ['required' => false, 'label' => 'State', 'type' => 'string', 'storage' => 'lead'],
            'pincode' => ['required' => false, 'label' => 'Pincode', 'type' => 'string', 'storage' => 'lead'],
            'property_type' => ['required' => false, 'label' => 'Property Type', 'type' => 'string', 'storage' => 'lead'],
            'budget' => ['required' => false, 'label' => 'Budget', 'type' => 'string', 'storage' => 'lead'],
            'budget_min' => ['required' => false, 'label' => 'Budget Min', 'type' => 'string', 'storage' => 'lead'],
            'budget_max' => ['required' => false, 'label' => 'Budget Max', 'type' => 'string', 'storage' => 'lead'],
            'requirements' => ['required' => false, 'label' => 'Requirements', 'type' => 'string', 'storage' => 'lead'],
            'notes' => ['required' => false, 'label' => 'Notes', 'type' => 'string', 'storage' => 'lead'],
            'preferred_location' => ['required' => false, 'label' => 'Preferred Location', 'type' => 'string', 'storage' => 'lead'],
            'preferred_size' => ['required' => false, 'label' => 'Preferred Size', 'type' => 'string', 'storage' => 'lead'],
            'use_end_use' => ['required' => false, 'label' => 'End Use', 'type' => 'string', 'storage' => 'lead'],
            'possession_status' => ['required' => false, 'label' => 'Possession Status', 'type' => 'string', 'storage' => 'lead'],
            'page_url' => ['required' => false, 'label' => 'Page URL', 'type' => 'string', 'storage' => 'meta'],
            'referrer' => ['required' => false, 'label' => 'Referrer', 'type' => 'string', 'storage' => 'meta'],
            'utm_source' => ['required' => false, 'label' => 'UTM Source', 'type' => 'string', 'storage' => 'meta'],
            'utm_medium' => ['required' => false, 'label' => 'UTM Medium', 'type' => 'string', 'storage' => 'meta'],
            'utm_campaign' => ['required' => false, 'label' => 'UTM Campaign', 'type' => 'string', 'storage' => 'meta'],
            'utm_term' => ['required' => false, 'label' => 'UTM Term', 'type' => 'string', 'storage' => 'meta'],
            'utm_content' => ['required' => false, 'label' => 'UTM Content', 'type' => 'string', 'storage' => 'meta'],
        ];
    }

    public function flattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $fullKey));
                continue;
            }

            $result[$fullKey] = $value;
        }

        return $result;
    }

    public function previewWebsiteMappings(array $rawPayload, array $mappings): array
    {
        $flattened = $this->flattenPayload($rawPayload);
        $targetFields = $this->getWebsiteTargetFields();
        $mappedPayload = [];
        $metaPayload = [];
        $ignoredFields = [];
        $defaultsApplied = [];
        $usedIncoming = [];
        $duplicateWarnings = [];

        $seenTargets = [];
        foreach ($mappings as $mapping) {
            $incomingField = trim((string) ($mapping['incoming_field'] ?? ''));
            $crmField = trim((string) ($mapping['crm_field'] ?? ''));
            $isIgnored = (bool) ($mapping['is_ignored'] ?? false);
            $defaultValue = $mapping['default_value'] ?? null;

            if ($incomingField === '') {
                continue;
            }

            if ($isIgnored || $crmField === '') {
                if (array_key_exists($incomingField, $flattened)) {
                    $ignoredFields[$incomingField] = $flattened[$incomingField];
                    $usedIncoming[] = $incomingField;
                }
                continue;
            }

            if (isset($seenTargets[$crmField]) && !in_array($crmField, ['notes', 'requirements'], true)) {
                $duplicateWarnings[] = "Target field '{$crmField}' is mapped more than once.";
            }
            $seenTargets[$crmField] = true;

            $hasIncomingValue = array_key_exists($incomingField, $flattened);
            $value = $hasIncomingValue ? $flattened[$incomingField] : $defaultValue;

            if ($hasIncomingValue) {
                $usedIncoming[] = $incomingField;
            }

            if (!$hasIncomingValue && $defaultValue !== null && $defaultValue !== '') {
                $defaultsApplied[$crmField] = $defaultValue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $storage = $targetFields[$crmField]['storage'] ?? 'lead';
            $formattedValue = $this->formatMappedValue($incomingField, $crmField, $value);

            if ($storage === 'meta') {
                $metaPayload[$crmField] = is_scalar($value) ? $value : json_encode($value);
                continue;
            }

            if (in_array($crmField, ['notes', 'requirements'], true) && isset($mappedPayload[$crmField]) && $mappedPayload[$crmField] !== '') {
                $mappedPayload[$crmField] .= "\n" . $formattedValue;
            } else {
                $mappedPayload[$crmField] = $formattedValue;
            }
        }

        $missingRequired = [];
        foreach ($targetFields as $field => $config) {
            if (($config['required'] ?? false) && blank($mappedPayload[$field] ?? null)) {
                $missingRequired[] = $field;
            }
        }

        $unmappedIncoming = [];
        foreach ($flattened as $incomingField => $value) {
            if (!in_array($incomingField, $usedIncoming, true)) {
                $unmappedIncoming[$incomingField] = $value;
            }
        }

        $fieldErrors = $this->validateMappedWebsitePayload($mappedPayload, $mappings);

        return [
            'flattened_payload' => $flattened,
            'mapped_payload' => $mappedPayload,
            'meta_payload' => $metaPayload,
            'ignored_fields' => $ignoredFields,
            'defaults_applied' => $defaultsApplied,
            'missing_required' => $missingRequired,
            'field_errors' => $fieldErrors,
            'duplicate_warnings' => $duplicateWarnings,
            'unmapped_incoming' => $unmappedIncoming,
        ];
    }

    public function validateWebsiteMappings(array $mappings, array $samplePayload = []): array
    {
        $errors = [];
        $criticalTargets = [];

        foreach ($mappings as $index => $mapping) {
            $incomingField = trim((string) ($mapping['incoming_field'] ?? ''));
            $crmField = trim((string) ($mapping['crm_field'] ?? ''));
            $isIgnored = (bool) ($mapping['is_ignored'] ?? false);

            if ($incomingField === '') {
                $errors["mappings.{$index}.incoming_field"] = 'Incoming field is required.';
                continue;
            }

            if (!$isIgnored && $crmField === '') {
                $errors["mappings.{$index}.crm_field"] = 'Choose a CRM target or mark the row as ignored.';
            }

            if (!$isIgnored && in_array($crmField, ['name', 'phone', 'email'], true)) {
                if (isset($criticalTargets[$crmField])) {
                    $errors["mappings.{$index}.crm_field"] = "Critical field '{$crmField}' cannot be mapped more than once.";
                }

                $criticalTargets[$crmField] = true;
            }
        }

        $preview = $this->previewWebsiteMappings($samplePayload, $mappings);
        foreach ($preview['missing_required'] as $field) {
            $errors["required.{$field}"] = "Required CRM field '{$field}' is not mapped.";
        }

        foreach ($preview['field_errors'] as $field => $message) {
            $errors["field.{$field}"] = $message;
        }

        return [
            'errors' => $errors,
            'preview' => $preview,
        ];
    }

    public function validateMappedWebsitePayload(array $mappedPayload, array $mappings = []): array
    {
        $errors = [];

        $phone = trim((string) ($mappedPayload['phone'] ?? ''));
        if ($phone !== '') {
            $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
            if ($digits === '' || strlen($digits) < 10 || strlen($digits) > 15) {
                $errors['phone'] = 'Phone must be numeric and between 10 and 15 digits.';
            }
        }

        $email = trim((string) ($mappedPayload['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        return $errors;
    }

    private function formatMappedValue(string $incomingField, string $crmField, mixed $value): string
    {
        $stringValue = is_scalar($value) ? trim((string) $value) : trim((string) json_encode($value));

        if (!in_array($crmField, ['notes', 'requirements'], true)) {
            return $stringValue;
        }

        if (in_array($incomingField, ['notes', 'message', 'reason', 'requirements'], true)) {
            return $stringValue;
        }

        return $incomingField . ': ' . $stringValue;
    }
}
