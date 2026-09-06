<?php

use App\Services\KycFormSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schemaService = app(KycFormSchemaService::class);
        $defaultFields = collect($schemaService->getDefaultFieldDefinitions())->keyBy('field_key');
        $allowedKeys = $defaultFields->keys()->all();
        $now = now();

        $forms = DB::table('dynamic_forms')
            ->where('location_path', KycFormSchemaService::LOCATION_PATH)
            ->get();

        foreach ($forms as $form) {
            DB::table('dynamic_form_fields')
                ->where('form_id', $form->id)
                ->whereNotIn('field_key', $allowedKeys)
                ->delete();

            foreach ($defaultFields as $fieldKey => $field) {
                $payload = [
                    'field_type' => $field['field_type'],
                    'label' => $field['label'],
                    'placeholder' => $field['placeholder'],
                    'help_text' => $field['help_text'],
                    'options' => isset($field['options']) ? json_encode($field['options']) : null,
                    'validation' => isset($field['validation']) ? json_encode($field['validation']) : null,
                    'required' => (bool) ($field['required'] ?? false),
                    'order' => (int) ($field['order'] ?? 0),
                    'section' => $field['section'],
                    'styles' => isset($field['styles']) ? json_encode($field['styles']) : null,
                    'default_value' => $field['default_value'],
                    'is_system' => true,
                    'system_binding' => $field['system_binding'],
                    'is_visible' => true,
                    'updated_at' => $now,
                ];

                $existing = DB::table('dynamic_form_fields')
                    ->where('form_id', $form->id)
                    ->where('field_key', $fieldKey)
                    ->first();

                if ($existing) {
                    DB::table('dynamic_form_fields')
                        ->where('id', $existing->id)
                        ->update($payload);

                    continue;
                }

                DB::table('dynamic_form_fields')->insert($payload + [
                    'form_id' => $form->id,
                    'field_key' => $fieldKey,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: restoring removed builder fields would reintroduce old KYC UI.
    }
};
