<?php

namespace App\Services;

/**
 * Apply mapping_json (FB field name => CRM field key) to Meta field_data array.
 * Used to build structured data for fb_leads (standalone) and later for CRM leads.
 */
class FacebookLeadMappingService
{
    /** Known FB field names => suggested CRM key */
    protected static array $suggestedMap = [
        'full_name' => 'name',
        'name' => 'name',
        'first_name' => 'name',
        'last_name' => 'name',
        'customer_name' => 'name',
        'client_name' => 'name',
        'your_name' => 'name',
        'enter_your_name' => 'name',
        'contact_name' => 'name',
        'email' => 'email',
        'email_address' => 'email',
        'phone_number' => 'phone',
        'phone' => 'phone',
        'mobile' => 'phone',
        'mobile_number' => 'phone',
        'contact_number' => 'phone',
        'whatsapp' => 'phone',
        'whatsapp_number' => 'phone',
        'whatsapp_no' => 'phone',
        'wa_number' => 'phone',
        'phone_no' => 'phone',
        'number' => 'phone',
        'city' => 'city',
        'state' => 'state',
        'country' => 'meta',
        'zip_code' => 'pincode',
        'zip' => 'pincode',
        'postal_code' => 'pincode',
        'street_address' => 'address',
        'address' => 'address',
        'location' => 'address',
        'locality' => 'address',
        'requirement' => 'requirements',
        'requirements' => 'requirements',
        'message' => 'requirements',
        'remarks' => 'notes',
        'comment' => 'notes',
        'budget' => 'meta',
        'property_type' => 'meta',
        'project' => 'meta',
        'project_name' => 'meta',
    ];

    /**
     * Suggest mapping for a list of FB field names (for UI defaults).
     *
     * @param string[] $fbFieldNames
     * @return array<string, string> fb_field_name => crm_key
     */
    public static function suggestMapping(array $fbFieldNames): array
    {
        $out = [];
        foreach ($fbFieldNames as $name) {
            $normalized = self::normalizeFieldKey($name);
            $out[$name] = self::$suggestedMap[$normalized] ?? self::$suggestedMap[$name] ?? 'meta';
        }
        return $out;
    }

    public static function normalizeFieldKey(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9_]/', '_', $value), '_'));
    }

    public static function prettifyFieldName(string $value): string
    {
        $clean = trim(preg_replace('/[_\-\s]+/', ' ', preg_replace('/[^A-Za-z0-9_\-\s!?&()]/', ' ', $value)));
        $clean = preg_replace('/\s+/', ' ', $clean ?? '');
        $clean = trim((string) $clean);

        return $clean !== '' ? ucwords(strtolower($clean)) : $value;
    }

    public static function fallbackMappingForFieldData(array $fieldData): array
    {
        $fieldNames = [];

        foreach ($fieldData as $item) {
            $name = $item['name'] ?? $item['key'] ?? null;

            if ($name !== null) {
                $fieldNames[] = (string) $name;
            }
        }

        return self::suggestMapping(array_values(array_unique($fieldNames)));
    }

    /**
     * Get CRM field keys allowed in mapping (for dropdown).
     * Includes standard keys + custom keys from fb_custom_mapping_fields.
     */
    public static function getCrmFieldKeys(): array
    {
        $standard = [
            'name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'pincode',
            'requirements',
            'notes',
            'meta',
        ];

        $custom = \App\Models\FbCustomMappingField::orderBy('field_key')->pluck('field_key')->all();

        return array_values(array_unique(array_merge($standard, $custom)));
    }

    /**
     * Apply mapping to Meta field_data.
     * field_data from Meta is array of { name, values }.
     *
     * @param array $fieldData Meta field_data (list of { name, values })
     * @param array $mappingJson fb field name => crm_key
     * @return array [ 'mapped' => key => value, 'meta' => extra key => value ]
     */
    public function applyMapping(array $fieldData, array $mappingJson): array
    {
        $fbByName = [];
        foreach ($fieldData as $item) {
            $name = $item['name'] ?? $item['key'] ?? null;
            $val = $item['values'][0] ?? $item['value'] ?? null;
            if ($name !== null) {
                $fbByName[$name] = $val;
            }
        }

        $mapped = [];
        $meta = [];
        $nameParts = [];
        foreach ($fbByName as $fbName => $value) {
            $crmKey = $mappingJson[$fbName] ?? 'meta';
            if ($crmKey === 'meta') {
                $meta[$fbName] = $value;
            } elseif ($crmKey === 'name') {
                $nameParts[] = $value;
            } else {
                $mapped[$crmKey] = $value;
            }
        }
        if (!empty($nameParts)) {
            $mapped['name'] = trim(implode(' ', $nameParts));
        }
        if (!empty($meta)) {
            $mapped['meta'] = $meta;
        }
        return $mapped;
    }

    /**
     * Convert Meta field_data to flat key=>value for storage in fb_leads.field_data_json.
     */
    public function fieldDataToFlat(array $fieldData): array
    {
        $flat = [];
        foreach ($fieldData as $item) {
            $name = $item['name'] ?? $item['key'] ?? null;
            $val = $item['values'][0] ?? $item['value'] ?? null;
            if ($name !== null) {
                $flat[$name] = $val;
            }
        }
        return $flat;
    }
}
