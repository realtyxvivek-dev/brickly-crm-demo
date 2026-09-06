<?php

namespace App\Services;

use App\Models\DynamicForm;
use Illuminate\Support\Facades\DB;

class FormReplacementService
{
    /**
     * Replace an old form with a new one
     *
     * @param int $oldFormId
     * @param int $newFormId
     * @return bool
     */
    public function replaceForm(int $oldFormId, int $newFormId): bool
    {
        try {
            DB::beginTransaction();

            $oldForm = DynamicForm::findOrFail($oldFormId);
            $newForm = DynamicForm::findOrFail($newFormId);

            // Mark old form as replaced
            $oldForm->update([
                'status' => 'draft',
                'is_active' => false,
            ]);

            // Link new form to old form
            $newForm->update([
                'replaces_form_id' => $oldFormId,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Get the active form for a location path
     *
     * @param string $locationPath
     * @return DynamicForm|null
     */
    public function getActiveFormForLocation(string $locationPath): ?DynamicForm
    {
        return DynamicForm::where('location_path', $locationPath)
            ->where('status', 'published')
            ->where('is_active', true)
            ->with(['fields' => function($query) {
                $query->orderBy('order');
            }])
            ->first();
    }

    /**
     * Check if a form is in use (has submissions)
     *
     * @param int $formId
     * @return bool
     */
    public function isFormInUse(int $formId): bool
    {
        return DynamicForm::findOrFail($formId)
            ->submissions()
            ->exists();
    }

    /**
     * Handle form replacement when publishing
     *
     * @param DynamicForm $form
     * @param string $locationPath
     * @return int|null The ID of the replaced form, or null if none
     */
    public function handlePublishReplacement(DynamicForm $form, string $locationPath): ?int
    {
        // Find existing published form at same location
        $existingForm = DynamicForm::where('location_path', $locationPath)
            ->where('status', 'published')
            ->where('is_active', true)
            ->where('id', '!=', $form->id)
            ->first();

        if ($existingForm) {
            // Mark old form as replaced
            $existingForm->update([
                'status' => 'draft',
                'is_active' => false,
            ]);

            // Link new form to old form
            $form->update([
                'replaces_form_id' => $existingForm->id,
            ]);

            return $existingForm->id;
        }

        return null;
    }
}
