<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadFormField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadFormBuilderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fields = LeadFormField::orderBy('display_order')->get();
        $fieldsByLevel = $fields->groupBy('field_level');
        
        return view('admin.lead-form-builder.index', compact('fields', 'fieldsByLevel'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $existingFields = LeadFormField::pluck('field_key')->toArray();
        return view('admin.lead-form-builder.create', compact('existingFields'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:255|unique:lead_form_fields,field_key',
            'field_label' => 'required|string|max:255',
            'field_type' => 'required|in:text,textarea,select,date,time,datetime,number,email,tel',
            'field_level' => 'required|in:telecaller,sales_executive,sales_manager',
            'options' => 'nullable|array',
            'options.*' => 'nullable|string',
            'is_required' => 'boolean',
            'validation_rules' => 'nullable|array',
            'dependent_field' => 'nullable|string|exists:lead_form_fields,field_key',
            'dependent_conditions' => 'nullable|array',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'default_value' => 'nullable|string',
            'placeholder' => 'nullable|string',
            'help_text' => 'nullable|string',
        ]);

        // If select type and no options provided, set empty array
        if ($validated['field_type'] === 'select' && empty($validated['options'])) {
            $validated['options'] = [];
        }

        // Auto-calculate display_order if not provided
        if (!isset($validated['display_order'])) {
            $maxOrder = LeadFormField::max('display_order') ?? 0;
            $validated['display_order'] = $maxOrder + 1;
        }

        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active') ? true : false;

        LeadFormField::create($validated);

        return redirect()->route('admin.lead-form-builder.index')
            ->with('success', 'Form field created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(LeadFormField $leadFormField)
    {
        return view('admin.lead-form-builder.show', compact('leadFormField'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LeadFormField $leadFormField)
    {
        $existingFields = LeadFormField::where('id', '!=', $leadFormField->id)
            ->pluck('field_key')
            ->toArray();
        
        return view('admin.lead-form-builder.edit', compact('leadFormField', 'existingFields'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeadFormField $leadFormField)
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:255|unique:lead_form_fields,field_key,' . $leadFormField->id,
            'field_label' => 'required|string|max:255',
            'field_type' => 'required|in:text,textarea,select,date,time,datetime,number,email,tel',
            'field_level' => 'required|in:telecaller,sales_executive,sales_manager',
            'options' => 'nullable|array',
            'options.*' => 'nullable|string',
            'is_required' => 'boolean',
            'validation_rules' => 'nullable|array',
            'dependent_field' => 'nullable|string|exists:lead_form_fields,field_key',
            'dependent_conditions' => 'nullable|array',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'default_value' => 'nullable|string',
            'placeholder' => 'nullable|string',
            'help_text' => 'nullable|string',
        ]);

        // Handle options for select fields
        if ($validated['field_type'] === 'select') {
            if (isset($validated['options']) && is_array($validated['options'])) {
                // Filter out empty options
                $validated['options'] = array_filter(array_map('trim', $validated['options']));
            } else {
                $validated['options'] = [];
            }
        } else {
            $validated['options'] = null;
        }

        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active') ? true : false;

        $leadFormField->update($validated);

        return redirect()->route('admin.lead-form-builder.index')
            ->with('success', 'Form field updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeadFormField $leadFormField)
    {
        // Check if field is being used in any lead
        $usageCount = DB::table('lead_form_field_values')
            ->where('field_key', $leadFormField->field_key)
            ->count();

        if ($usageCount > 0) {
            return redirect()->route('admin.lead-form-builder.index')
                ->with('error', "Cannot delete field. It is being used in {$usageCount} lead(s). You can deactivate it instead.");
        }

        $leadFormField->delete();

        return redirect()->route('admin.lead-form-builder.index')
            ->with('success', 'Form field deleted successfully!');
    }

    /**
     * Reorder form fields
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:lead_form_fields,id',
            'fields.*.display_order' => 'required|integer',
        ]);

        foreach ($request->fields as $field) {
            LeadFormField::where('id', $field['id'])
                ->update(['display_order' => $field['display_order']]);
        }

        return response()->json(['success' => true, 'message' => 'Fields reordered successfully!']);
    }

    /**
     * Toggle field active status
     */
    public function toggleActive(LeadFormField $leadFormField)
    {
        $leadFormField->is_active = !$leadFormField->is_active;
        $leadFormField->save();

        $status = $leadFormField->is_active ? 'activated' : 'deactivated';
        
        return redirect()->route('admin.lead-form-builder.index')
            ->with('success', "Form field {$status} successfully!");
    }
}
