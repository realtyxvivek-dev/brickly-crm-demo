<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicFormController extends Controller
{
    /**
     * Get form by slug or ID
     */
    public function getForm(Request $request, $identifier)
    {
        $form = DynamicForm::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->with(['fields' => function($query) {
                $query->orderBy('order');
            }])
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return response()->json([
                'success' => false,
                'message' => 'Form not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $form,
        ]);
    }

    /**
     * Render form HTML (for preview)
     */
    public function renderForm(Request $request, $identifier)
    {
        $form = DynamicForm::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->with(['fields' => function($query) {
                $query->orderBy('order');
            }])
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return response()->json([
                'success' => false,
                'message' => 'Form not found',
            ], 404);
        }

        // Return form data for rendering on frontend
        return response()->json([
            'success' => true,
            'data' => [
                'form' => $form,
                'fields' => $form->fields,
            ],
        ]);
    }

    /**
     * Submit form data
     */
    public function submitForm(Request $request, $identifier)
    {
        $form = DynamicForm::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->with('fields')
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return response()->json([
                'success' => false,
                'message' => 'Form not found',
            ], 404);
        }

        // Build validation rules from form fields
        $rules = [];
        $fieldKeys = [];

        foreach ($form->fields as $field) {
            $fieldKeys[] = $field->field_key;
            $fieldRules = $field->getValidationRules();
            
            if (!empty($fieldRules)) {
                $rules[$field->field_key] = implode('|', $fieldRules);
            }
        }

        $validated = $request->validate($rules);

        // Store submission
        $submission = $form->submissions()->create([
            'user_id' => $request->user()?->id,
            'data' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form submitted successfully',
            'submission_id' => $submission->id,
        ], 201);
    }
}
