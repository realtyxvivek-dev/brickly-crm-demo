@extends('layouts.app')

@section('title', ($form ? 'Edit Form' : 'Create Form') . ' - Admin')
@section('page-title', $form ? 'Edit Form: ' . $form->name : 'Create New Form')
@section('page-subtitle', 'Drag and drop fields to build your form')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.css">
<style>
    .builder-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    @media (max-width: 1024px) {
        .builder-container {
            grid-template-columns: 1fr;
        }
        .field-palette {
            display: none;
        }
    }
    .field-palette {
        background: white;
        border: 1px solid #E5DED4;
        border-radius: 12px;
        padding: 20px;
        height: fit-content;
        position: sticky;
        top: 20px;
    }
    .palette-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #E5DED4;
    }
    .palette-field {
        padding: 12px;
        background: #F7F6F3;
        border: 2px dashed #E5DED4;
        border-radius: 8px;
        margin-bottom: 12px;
        cursor: move;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s;
        color: #063A1C;
    }
    .palette-field span {
        color: #063A1C;
    }
    .palette-field:hover {
        border-color: #205A44;
        background: #e8f5e9;
    }
    .palette-field i {
        font-size: 20px;
        color: #205A44;
        width: 24px;
    }
    .form-builder {
        background: white;
        border: 1px solid #E5DED4;
        border-radius: 12px;
        padding: 30px;
    }
    .form-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #E5DED4;
    }
    .form-header input {
        width: 100%;
        padding: 12px;
        font-size: 24px;
        font-weight: 600;
        border: none;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: #063A1C;
    }
    .form-header input::placeholder {
        color: #999;
    }
    .form-header input:focus {
        outline: none;
        border-bottom-color: #205A44;
        color: #063A1C;
    }
    .form-sections {
        min-height: 400px;
    }
    .form-section {
        margin-bottom: 24px;
        padding: 20px;
        border: 1px solid #E5DED4;
        border-radius: 8px;
        background: #F7F6F3;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .section-title-input {
        flex: 1;
        padding: 8px 12px;
        font-size: 16px;
        font-weight: 600;
        border: none;
        background: white;
        border-radius: 6px;
        color: #063A1C;
    }
    .section-title-input::placeholder {
        color: #999;
    }
    .form-field {
        background: white;
        border: 2px solid #E5DED4;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 16px;
        position: relative;
        cursor: move;
    }
    .form-field:hover {
        border-color: #205A44;
    }
    .form-field.dragging {
        opacity: 0.5;
    }
    .field-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .field-label-input {
        flex: 1;
        padding: 8px 12px;
        font-size: 16px;
        font-weight: 500;
        border: 1px solid #E5DED4;
        border-radius: 6px;
        color: #063A1C;
        background: white;
    }
    .field-label-input::placeholder {
        color: #999;
    }
    .field-actions {
        display: flex;
        gap: 8px;
    }
    .field-action-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }
    .btn-field-edit {
        background: #205A44;
        color: white;
    }
    .btn-field-delete {
        background: #ef4444;
        color: white;
    }
    .field-preview {
        margin-top: 12px;
    }
    .field-preview input,
    .field-preview select,
    .field-preview textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #E5DED4;
        border-radius: 6px;
        color: #063A1C;
        background: white;
    }
    .field-preview input::placeholder,
    .field-preview textarea::placeholder {
        color: #999;
    }
    .field-preview input:disabled,
    .field-preview select:disabled,
    .field-preview textarea:disabled {
        color: #666;
        background: #f5f5f5;
        cursor: not-allowed;
    }
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #E5DED4;
    }
    .btn-save-form {
        padding: 12px 24px;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-save-form:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(32, 90, 68, 0.3);
    }
    .empty-fields {
        text-align: center;
        padding: 60px 20px;
        color: #B3B5B4;
    }
    .empty-fields i {
        font-size: 48px;
        margin-bottom: 16px;
    }
    .field-settings-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    .field-settings-modal.active {
        display: flex;
    }
    .modal-content-settings {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-content-settings label {
        color: #063A1C;
    }
    .modal-content-settings input[type="text"],
    .modal-content-settings textarea,
    .modal-content-settings select {
        color: #063A1C;
    }
    .modal-content-settings input[type="text"]::placeholder,
    .modal-content-settings textarea::placeholder {
        color: #999;
    }

    /* Compact form workspace */
    .container { max-width: 1500px; }
    .builder-topbar {
        position: sticky; top: 0; z-index: 30; display: grid; grid-template-columns: minmax(240px, 1fr) minmax(220px, 360px) auto;
        align-items: center; gap: 12px; margin-bottom: 12px; padding: 12px 14px; background: rgba(255,255,255,.97);
        border: 1px solid #dfe7e2; border-radius: 9px; box-shadow: 0 8px 22px rgba(12,51,36,.06); backdrop-filter: blur(8px);
    }
    .builder-title-block { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .builder-title-block h1 { margin: 0; color: #102d22; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .builder-back { flex: none; width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid #d6dfda; border-radius: 8px; color: #174231; text-decoration: none; }
    .builder-save-state { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; color: #8a5700; font-size: .7rem; font-weight: 700; }
    .builder-save-state.is-saved { color: #17613e; }
    .builder-field-search { position: relative; display: block; }
    .builder-field-search i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #718078; }
    .builder-field-search input { width: 100%; min-height: 38px; padding: 0 12px 0 34px; border: 1px solid #d6dfda; border-radius: 8px; color: #173427; background: #fff; }
    .builder-field-search input:focus { outline: none; border-color: #17613e; box-shadow: 0 0 0 3px rgba(23,97,62,.1); }
    .builder-top-actions { display: flex; align-items: center; gap: 7px; }
    .builder-button { min-height: 38px; padding: 0 13px; border: 1px solid #d6dfda; border-radius: 8px; font-size: .78rem; font-weight: 800; cursor: pointer; white-space: nowrap; }
    .builder-button.secondary { background: #fff; color: #174231; }
    .builder-button.primary { background: #17613e; border-color: #17613e; color: #fff; }
    .builder-container { grid-template-columns: 220px minmax(0, 1fr); gap: 12px; margin-top: 0; align-items: start; }
    .field-palette { top: 76px; padding: 14px; border-radius: 9px; }
    .palette-title { margin-bottom: 10px; padding-bottom: 9px; font-size: .9rem; }
    .palette-field { min-height: 38px; margin-bottom: 7px; padding: 8px 10px; border-width: 1px; border-style: solid; font-size: .8rem; }
    .palette-field i { width: 18px; font-size: .9rem; }
    .form-builder { padding: 16px; border-radius: 9px; min-width: 0; }
    .form-header { margin-bottom: 14px; padding-bottom: 14px; border-bottom-width: 1px; }
    .form-header input { padding: 8px 10px; font-size: 1.1rem; border: 1px solid #dce4df; border-radius: 7px; }
    #formDescription { color: #62736b !important; }
    .builder-section-nav { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .builder-section-tabs { display: flex; gap: 7px; min-width: 0; overflow-x: auto; scrollbar-width: thin; padding-bottom: 2px; }
    .builder-section-tab { flex: none; min-height: 38px; padding: 0 12px; border: 1px solid #d8e1dc; border-radius: 8px; background: #f6faf7; color: #365247; font-size: .76rem; font-weight: 800; cursor: pointer; }
    .builder-section-tab.is-active { background: #17613e; border-color: #17613e; color: #fff; }
    .builder-section-tab span { margin-left: 5px; opacity: .76; }
    .builder-visible-count { flex: none; color: #718078; font-size: .72rem; }
    .form-sections { min-height: 260px; }
    .form-section { display: none; margin: 0; padding: 12px; border-color: #dfe7e2; border-radius: 8px; background: #f7faf8; }
    .form-section.is-active { display: block; }
    .section-header { margin-bottom: 10px; gap: 8px; }
    .section-title-input { min-height: 38px; padding: 7px 10px; font-size: .9rem; }
    .section-header button { flex: none; min-height: 34px; background: #fff !important; color: #9b2525 !important; border: 1px solid #efcaca !important; border-radius: 7px !important; }
    .form-field { margin-bottom: 8px; padding: 10px; border-width: 1px; border-radius: 8px; cursor: grab; }
    .form-field:last-child { margin-bottom: 0; }
    .field-header { margin-bottom: 7px; gap: 8px; }
    .field-label-input { min-width: 0; padding: 7px 9px; font-size: .86rem; font-weight: 700; }
    .field-actions { flex: none; }
    .field-action-btn { min-height: 34px; padding: 0 10px; border-radius: 7px; font-weight: 700; }
    .field-preview { margin-top: 6px; }
    .field-preview input, .field-preview select, .field-preview textarea { min-height: 36px; padding: 7px 9px; font-size: .78rem; }
    .field-preview textarea { min-height: 54px; }
    .field-settings-modal { z-index: 2400; justify-content: flex-end; background: rgba(7,25,18,.5); backdrop-filter: blur(3px); }
    #fieldSettingsModal .modal-content-settings { width: min(460px, 96vw); max-width: none; height: 100dvh; max-height: 100dvh; padding: 20px; border-radius: 0; box-shadow: -18px 0 50px rgba(0,0,0,.18); }
    #applyConfirmModal { justify-content: center; }
    #applyConfirmModal .modal-content-settings { height: auto; border-radius: 10px; }
    .builder-search-empty { display: none; padding: 30px 16px; text-align: center; color: #718078; }
    body.builder-has-unsaved #builderSaveState { color: #8a5700; }
    @media (max-width: 1024px) {
        .builder-topbar { grid-template-columns: minmax(200px,1fr) auto; }
        .builder-field-search { grid-column: 1 / -1; grid-row: 2; }
        .builder-container { grid-template-columns: 1fr; }
        .field-palette { display: none; }
    }
    @media (max-width: 640px) {
        .builder-topbar { position: static; grid-template-columns: 1fr; padding: 10px; }
        .builder-top-actions { display: grid; grid-template-columns: 1fr 1fr; }
        .builder-button { width: 100%; }
        .form-builder { padding: 10px; }
        .builder-section-nav { align-items: flex-start; flex-direction: column; }
        .builder-section-tabs { width: 100%; }
        .form-header > div:first-child { display: block !important; }
        .form-header > div:nth-child(2) { grid-template-columns: 1fr !important; }
        .field-header { align-items: flex-start; }
        .field-actions { display: grid; }
    }
</style>
@endpush

@section('content')
<div class="container">
    <form id="formBuilderForm" action="{{ $form ? route('admin.forms.update', $form->id) : route('admin.forms.store') }}" method="POST">
        @csrf
        @if($form)
            @method('PUT')
        @endif

        <input type="hidden" name="fields" id="fieldsData">
        <input type="hidden" name="settings" id="settingsData" value="{}">

        <div class="builder-topbar">
            <div class="builder-title-block">
                <a href="{{ route('admin.forms.index') }}" class="builder-back" aria-label="Back to forms"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h1>{{ $form ? $form->name : 'Create Form' }}</h1>
                    <span id="builderSaveState" class="builder-save-state is-saved"><i class="fas fa-check-circle"></i> Saved</span>
                </div>
            </div>
            <label class="builder-field-search">
                <i class="fas fa-search"></i>
                <input id="builderFieldSearch" type="search" placeholder="Search fields" autocomplete="off">
            </label>
            <div class="builder-top-actions">
                <button type="button" class="builder-button secondary" onclick="saveForm()"><i class="fas fa-save"></i> Save draft</button>
                <button type="button" class="builder-button primary" onclick="liveAndPublish()"><i class="fas fa-rocket"></i> Publish</button>
            </div>
        </div>

        <div class="builder-container">
            <!-- Field Palette -->
            <div class="field-palette">
                <div class="palette-title">Field Types</div>
                <div class="palette-field" data-type="text">
                    <i class="fas fa-font"></i>
                    <span>Text</span>
                </div>
                <div class="palette-field" data-type="email">
                    <i class="fas fa-envelope"></i>
                    <span>Email</span>
                </div>
                <div class="palette-field" data-type="number">
                    <i class="fas fa-hashtag"></i>
                    <span>Number</span>
                </div>
                <div class="palette-field" data-type="textarea">
                    <i class="fas fa-align-left"></i>
                    <span>Textarea</span>
                </div>
                <div class="palette-field" data-type="select">
                    <i class="fas fa-list"></i>
                    <span>Dropdown</span>
                </div>
                <div class="palette-field" data-type="radio">
                    <i class="fas fa-dot-circle"></i>
                    <span>Radio</span>
                </div>
                <div class="palette-field" data-type="checkbox">
                    <i class="fas fa-check-square"></i>
                    <span>Checkbox</span>
                </div>
                <div class="palette-field" data-type="date">
                    <i class="fas fa-calendar"></i>
                    <span>Date</span>
                </div>
                <div class="palette-field" data-type="file">
                    <i class="fas fa-file-upload"></i>
                    <span>File Upload</span>
                </div>
            </div>

            <!-- Form Builder -->
            <div class="form-builder">
                <!-- Form Basic Info -->
                <div class="form-header">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <input type="text" name="name" id="formName" placeholder="Untitled Form" value="{{ old('name', $form->name ?? ($existingForm['name'] ?? '')) }}" required>
                            <input type="text" name="description" id="formDescription" placeholder="Form description (optional)" value="{{ old('description', $form->description ?? ($existingForm['description'] ?? '')) }}" style="font-size: 14px; font-weight: normal; margin-top: 8px;">
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #063A1C;">Location Path</label>
                            <input type="text" name="location_path" id="locationPath" placeholder="e.g., leads/create" value="{{ old('location_path', $form->location_path ?? ($existingForm['location_path'] ?? '')) }}" required style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #063A1C;">Form Type</label>
                            <select name="form_type" id="formType" required style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
                                <option value="custom" {{ old('form_type', $form->form_type ?? ($existingForm['form_type'] ?? '')) === 'custom' ? 'selected' : '' }}>Custom</option>
                                <option value="lead" {{ old('form_type', $form->form_type ?? ($existingForm['form_type'] ?? '')) === 'lead' ? 'selected' : '' }}>Lead</option>
                                <option value="prospect" {{ old('form_type', $form->form_type ?? ($existingForm['form_type'] ?? '')) === 'prospect' ? 'selected' : '' }}>Prospect</option>
                                <option value="meeting" {{ old('form_type', $form->form_type ?? ($existingForm['form_type'] ?? '')) === 'meeting' ? 'selected' : '' }}>Meeting</option>
                                <option value="site_visit" {{ old('form_type', $form->form_type ?? ($existingForm['form_type'] ?? '')) === 'site_visit' ? 'selected' : '' }}>Site Visit</option>
                            </select>
                        </div>
                    </div>
                    @if(isset($existingForm))
                        <div style="margin-top: 12px; padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
                            <p style="margin: 0; color: #1976d2; font-size: 14px;">
                                <i class="fas fa-info-circle"></i> You are editing the live admin form for this system location. Use this screen for all future changes to this form.
                            </p>
                        </div>
                    @endif
                </div>

                @php
                    $builderSections = collect();

                    if ($form && $form->fields->count() > 0) {
                        $builderSections = $form->fields
                            ->sortBy('order')
                            ->groupBy(fn ($field) => $field->section ?: 'default');
                    } elseif (isset($detectedFields) && count($detectedFields) > 0) {
                        $builderSections = collect($detectedFields)
                            ->sortBy('order')
                            ->groupBy(fn ($field) => $field['section'] ?? 'default');
                    }
                @endphp

                <div class="builder-section-nav">
                    <div id="builderSectionTabs" class="builder-section-tabs" role="tablist" aria-label="Form sections"></div>
                    <span id="builderVisibleCount" class="builder-visible-count"></span>
                </div>

                <!-- Form Sections -->
                <div class="form-sections" id="formSections">
                    @if($builderSections->isNotEmpty())
                        @foreach($builderSections as $sectionName => $sectionFields)
                            <div class="form-section" data-section="{{ $sectionName ?: 'default' }}">
                                <div class="section-header">
                                    <input type="text" class="section-title-input" placeholder="Section Title (optional)" value="{{ $sectionName !== 'default' ? $sectionName : '' }}">
                                    <button type="button" onclick="removeSection(this)" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">Remove</button>
                                </div>
                                <div class="section-fields" data-section="{{ $sectionName ?: 'default' }}">
                                    @foreach($sectionFields as $field)
                                        @php
                                            $isSavedField = $form && $field instanceof \App\Models\DynamicFormField;
                                            $fieldType = $isSavedField ? $field->field_type : ($field['field_type'] ?? 'text');
                                            $fieldKey = $isSavedField ? $field->field_key : ($field['field_key'] ?? '');
                                            $fieldRequired = $isSavedField ? $field->required : ($field['required'] ?? false);
                                            $fieldOptions = $isSavedField ? $field->options : ($field['options'] ?? null);
                                            $fieldLabel = $isSavedField ? $field->label : ($field['label'] ?? 'Untitled Field');
                                            $fieldPlaceholder = $isSavedField ? ($field->placeholder ?? '') : ($field['placeholder'] ?? '');
                                            $fieldHelpText = $isSavedField ? ($field->help_text ?? '') : ($field['help_text'] ?? '');
                                            $fieldIsSystem = $isSavedField ? (bool) ($field->is_system ?? false) : (bool) ($field['is_system'] ?? false);
                                            $fieldSystemBinding = $isSavedField ? ($field->system_binding ?? '') : ($field['system_binding'] ?? '');
                                            $fieldVisible = $isSavedField ? ($field->is_visible !== false) : (($field['is_visible'] ?? true) !== false);
                                        @endphp
                                        <div class="form-field"
                                             @if($isSavedField) data-field-id="{{ $field->id }}" @endif
                                             data-field-type="{{ $fieldType }}"
                                             data-field-key="{{ $fieldKey }}"
                                             data-required="{{ $fieldRequired ? 'true' : 'false' }}"
                                             data-options="{{ $fieldOptions ? json_encode($fieldOptions) : '' }}"
                                             data-help-text="{{ $fieldHelpText }}"
                                             data-is-system="{{ $fieldIsSystem ? 'true' : 'false' }}"
                                             data-system-binding="{{ $fieldSystemBinding }}"
                                             data-is-visible="{{ $fieldVisible ? 'true' : 'false' }}">
                                            <div class="field-header">
                                                <input type="text" class="field-label-input" value="{{ $fieldLabel }}" placeholder="Field Label" data-field-property="label">
                                                <div class="field-actions">
                                                    @if($fieldIsSystem)
                                                        <span style="display:inline-flex;align-items:center;padding:6px 10px;background:#e8f5e9;color:#205A44;border-radius:999px;font-size:11px;font-weight:700;">System</span>
                                                    @endif
                                                    <button type="button" class="field-action-btn btn-field-edit" onclick="editField(this)">Settings</button>
                                                    @if(!$fieldIsSystem)
                                                        <button type="button" class="field-action-btn btn-field-delete" onclick="removeField(this)">Delete</button>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="field-preview">
                                                @if($isSavedField)
                                                    {!! renderFieldPreview($field) !!}
                                                @elseif($fieldType === 'textarea')
                                                    <textarea rows="3" placeholder="{{ $fieldPlaceholder }}" disabled></textarea>
                                                @elseif(in_array($fieldType, ['select', 'radio', 'checkbox']) && isset($field['options']))
                                                    @if($fieldType === 'select')
                                                        <select disabled>
                                                            <option>-- Select --</option>
                                                            @foreach($field['options'] as $option)
                                                                <option>{{ $option }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        @foreach($field['options'] as $option)
                                                            <div><label><input type="{{ $fieldType }}" disabled> {{ $option }}</label></div>
                                                        @endforeach
                                                    @endif
                                                @else
                                                    <input type="{{ $fieldType }}" placeholder="{{ $fieldPlaceholder }}" disabled>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="form-section" data-section="default">
                            <div class="section-header">
                                <input type="text" class="section-title-input" placeholder="Section Title (optional)" value="">
                                <button type="button" onclick="removeSection(this)" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">Remove</button>
                            </div>
                            <div class="section-fields" data-section="default">
                                <div class="empty-fields">
                                    <i class="fas fa-mouse-pointer"></i>
                                    <p>Drag fields from the left panel or click to add</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Form Status Indicator -->
                @if($form)
                    <div style="margin-top: 20px; padding: 12px; background: {{ $form->status === 'published' ? '#d1fae5' : '#fef3c7' }}; border-left: 4px solid {{ $form->status === 'published' ? '#065f46' : '#92400e' }}; border-radius: 4px;">
                        <p style="margin: 0; color: {{ $form->status === 'published' ? '#065f46' : '#92400e' }}; font-size: 14px; font-weight: 500;">
                            <i class="fas fa-{{ $form->status === 'published' ? 'check-circle' : 'clock' }}"></i>
                            Status: <strong>{{ ucfirst($form->status) }}</strong>
                        </p>
                    </div>
                @endif

                <!-- Hidden form status -->
                <input type="hidden" name="status" id="formStatus" value="{{ $form->status ?? 'draft' }}">
            </div>
        </div>
    </form>
</div>

<!-- Field Settings Modal -->
<div id="fieldSettingsModal" class="field-settings-modal">
    <div class="modal-content-settings">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; font-weight: 600;">Field Settings</h3>
            <button onclick="closeFieldSettings()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="fieldSettingsContent">
            <!-- Field settings will be loaded here -->
        </div>
    </div>
</div>

<!-- Apply Confirmation Modal -->
<div id="applyConfirmModal" class="field-settings-modal">
    <div class="modal-content-settings" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; font-weight: 600; color: #063A1C;">Make Form Live?</h3>
            <button onclick="closeApplyModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        <div style="padding: 20px 0;">
            <p style="color: #063A1C; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                This will publish your latest changes and make this form live for its system location. Continue?
            </p>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="button" onclick="closeApplyModal()" style="flex: 1; padding: 12px; background: #e0e0e0; color: #063A1C; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px;">
                    Cancel
                </button>
                <button type="button" onclick="confirmApply()" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px;">
                    Yes, Make Live
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    let fields = [];
    let currentEditingField = null;
    let isSubmittingViaFunction = false;
    let activeBuilderSection = 0;

    function builderSections() {
        return Array.from(document.querySelectorAll('#formSections > .form-section'));
    }

    function sectionLabel(section, index) {
        return section.querySelector('.section-title-input')?.value.trim() || `Section ${index + 1}`;
    }

    function activateBuilderSection(index) {
        const sections = builderSections();
        if (!sections.length) return;
        activeBuilderSection = Math.max(0, Math.min(index, sections.length - 1));
        sections.forEach((section, sectionIndex) => section.classList.toggle('is-active', sectionIndex === activeBuilderSection));
        document.querySelectorAll('.builder-section-tab').forEach((tab, tabIndex) => {
            const active = tabIndex === activeBuilderSection;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        updateVisibleFieldCount();
    }

    function refreshBuilderSectionTabs() {
        const tabs = document.getElementById('builderSectionTabs');
        const sections = builderSections();
        tabs.replaceChildren();
        sections.forEach((section, index) => {
            const count = section.querySelectorAll('.form-field').length;
            const button = document.createElement('button');
            const badge = document.createElement('span');
            button.type = 'button';
            button.className = 'builder-section-tab';
            button.setAttribute('role', 'tab');
            button.dataset.sectionIndex = String(index);
            button.append(document.createTextNode(sectionLabel(section, index) + ' '));
            badge.textContent = String(count);
            button.append(badge);
            tabs.append(button);
        });
        tabs.querySelectorAll('.builder-section-tab').forEach(tab => tab.addEventListener('click', () => activateBuilderSection(Number(tab.dataset.sectionIndex))));
        activateBuilderSection(activeBuilderSection);
    }

    function updateVisibleFieldCount() {
        const active = builderSections()[activeBuilderSection];
        const count = active ? Array.from(active.querySelectorAll('.form-field')).filter(field => !field.hidden).length : 0;
        document.getElementById('builderVisibleCount').textContent = `${count} field${count === 1 ? '' : 's'}`;
    }

    function filterBuilderFields() {
        const query = document.getElementById('builderFieldSearch').value.trim().toLowerCase();
        const sections = builderSections();
        let firstMatchingSection = -1;
        sections.forEach((section, sectionIndex) => {
            let matches = 0;
            section.querySelectorAll('.form-field').forEach(field => {
                const label = field.querySelector('.field-label-input')?.value.toLowerCase() || '';
                const key = (field.dataset.fieldKey || '').toLowerCase();
                field.hidden = Boolean(query) && !label.includes(query) && !key.includes(query);
                if (!field.hidden) matches++;
            });
            if (matches && firstMatchingSection < 0) firstMatchingSection = sectionIndex;
        });
        if (query && firstMatchingSection >= 0 && !sections[activeBuilderSection]?.querySelector('.form-field:not([hidden])')) {
            activateBuilderSection(firstMatchingSection);
        }
        updateVisibleFieldCount();
    }

    function markBuilderDirty() {
        if (isSubmittingViaFunction) return;
        document.body.classList.add('builder-has-unsaved');
        const state = document.getElementById('builderSaveState');
        state.classList.remove('is-saved');
        state.innerHTML = '<i class="fas fa-circle"></i> Unsaved changes';
    }

    refreshBuilderSectionTabs();
    document.getElementById('builderFieldSearch').addEventListener('input', filterBuilderFields);
    document.getElementById('formBuilderForm').addEventListener('input', event => {
        if (event.target.id === 'builderFieldSearch') return;
        markBuilderDirty();
        if (event.target.classList.contains('section-title-input')) refreshBuilderSectionTabs();
    });
    document.getElementById('formBuilderForm').addEventListener('change', markBuilderDirty);
    window.addEventListener('beforeunload', event => {
        if (!document.body.classList.contains('builder-has-unsaved') || isSubmittingViaFunction) return;
        event.preventDefault();
        event.returnValue = '';
    });

    // Initialize Sortable for sections
    const sectionsContainer = document.getElementById('formSections');
    new Sortable(sectionsContainer, {
        animation: 150,
        handle: '.section-header',
        onEnd: () => { markBuilderDirty(); refreshBuilderSectionTabs(); },
    });

    // Initialize Sortable for fields
    document.querySelectorAll('.section-fields').forEach(section => {
        new Sortable(section, {
            animation: 150,
            handle: '.form-field',
            group: 'fields',
            onEnd: () => { markBuilderDirty(); refreshBuilderSectionTabs(); },
        });
    });

    // Add field from palette
    document.querySelectorAll('.palette-field').forEach(item => {
        item.addEventListener('click', function() {
            addField(this.dataset.type);
        });
    });

    function addField(type) {
        const targetSection = builderSections()[activeBuilderSection]?.querySelector('.section-fields');
        if (!targetSection) return;

        const fieldId = 'field_' + Date.now();
        const field = {
            id: fieldId,
            type: type,
            label: 'Untitled Field',
            field_key: 'field_' + type + '_' + Date.now(),
            required: false,
        };

        const fieldHTML = createFieldHTML(field);
        targetSection.insertAdjacentHTML('beforeend', fieldHTML);
        markBuilderDirty();
        refreshBuilderSectionTabs();
    }

    function createFieldHTML(field) {
        const fieldTypes = {
            text: '<input type="text" placeholder="Text input" disabled>',
            email: '<input type="email" placeholder="Email address" disabled>',
            number: '<input type="number" placeholder="Number" disabled>',
            textarea: '<textarea rows="3" placeholder="Long text" disabled></textarea>',
            select: '<select disabled><option>Option 1</option></select>',
            radio: '<div><label><input type="radio" disabled> Option 1</label></div>',
            checkbox: '<div><label><input type="checkbox" disabled> Option 1</label></div>',
            date: '<input type="date" disabled>',
            file: '<input type="file" disabled>',
        };

        return `
            <div class="form-field" data-field-id="${field.id}" data-field-type="${field.type}" data-options="" data-help-text="" data-is-system="false" data-system-binding="" data-is-visible="true">
                <div class="field-header">
                    <input type="text" class="field-label-input" value="${field.label}" placeholder="Field Label" data-field-property="label">
                    <div class="field-actions">
                        <button type="button" class="field-action-btn btn-field-edit" onclick="editField(this)">Settings</button>
                        <button type="button" class="field-action-btn btn-field-delete" onclick="removeField(this)">Delete</button>
                    </div>
                </div>
                <div class="field-preview">
                    ${fieldTypes[field.type] || fieldTypes.text}
                </div>
            </div>
        `;
    }

    function removeField(btn) {
        if (btn.closest('.form-field')?.dataset.isSystem === 'true') {
            alert('System fields cannot be deleted. You can hide them from settings if needed.');
            return;
        }

        if (confirm('Are you sure you want to remove this field?')) {
            btn.closest('.form-field').remove();
            markBuilderDirty();
            refreshBuilderSectionTabs();
        }
    }

    function removeSection(btn) {
        const section = btn.closest('.form-section');
        if (!section) return;
        if (section.querySelector('[data-is-system="true"]')) {
            alert('A section containing system fields cannot be removed. Individual fields can be hidden from Settings.');
            return;
        }
        if (confirm('Remove this section and all of its fields?')) {
            section.remove();
            activeBuilderSection = 0;
            markBuilderDirty();
            refreshBuilderSectionTabs();
        }
    }

    function getFieldOptionsFromPreview(fieldElement) {
        const previewSelect = fieldElement.querySelector('.field-preview select');
        if (previewSelect) {
            return Array.from(previewSelect.querySelectorAll('option'))
                .map(option => option.textContent.trim())
                .filter(option => option && option !== '-- Select --');
        }

        return Array.from(fieldElement.querySelectorAll('.field-preview label'))
            .map(label => label.textContent.trim())
            .filter(Boolean);
    }

    function getStoredFieldOptions(fieldElement) {
        if (!fieldElement) return [];

        try {
            if (fieldElement.dataset.options) {
                const parsedOptions = JSON.parse(fieldElement.dataset.options);
                if (Array.isArray(parsedOptions) && parsedOptions.length > 0) {
                    return parsedOptions;
                }
            }
        } catch (e) {
            // Fall back to preview-derived options below
        }

        return getFieldOptionsFromPreview(fieldElement);
    }

    function setStoredFieldOptions(fieldElement, options) {
        if (!fieldElement) return;

        const normalizedOptions = Array.isArray(options)
            ? options.map(option => option.trim()).filter(Boolean)
            : [];

        fieldElement.dataset.options = normalizedOptions.length > 0
            ? JSON.stringify(normalizedOptions)
            : '';
    }

    function editField(btn) {
        const fieldElement = btn.closest('.form-field');
        currentEditingField = fieldElement;
        
        const fieldType = fieldElement.dataset.fieldType;
        const label = fieldElement.querySelector('.field-label-input').value;
        const isSystemField = fieldElement.dataset.isSystem === 'true';
        
        const existingKey = fieldElement.dataset.fieldKey || '';
        const existingPlaceholder = fieldElement.querySelector('.field-preview input, .field-preview textarea, .field-preview select')?.placeholder || '';
        const existingRequired = fieldElement.dataset.required === 'true';
        const existingOptions = getStoredFieldOptions(fieldElement).join('\n');
        const existingVisible = fieldElement.dataset.isVisible !== 'false';
        const existingBinding = fieldElement.dataset.systemBinding || '';
        const existingHelpText = fieldElement.dataset.helpText || '';
        
        const settingsHTML = `
            ${isSystemField ? `
            <div style="margin-bottom: 16px; padding: 10px 12px; background: #ecfdf5; color: #166534; border-radius: 8px; font-size: 13px;">
                This is a protected system field. Label, required flag, section, placeholder, and visibility can be changed, but key/type/binding stay locked.
            </div>
            ` : ''}
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Field Key (Database Column) *</label>
                <input type="text" id="fieldKey" value="${existingKey}" placeholder="e.g., customer_name, phone" required ${isSystemField ? 'disabled' : ''} style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
                <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">This should match your database column name</small>
            </div>
            <input type="hidden" id="fieldSystemBinding" value="${existingBinding}">
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Field Type *</label>
                <select id="fieldType" onchange="changeFieldType(this.value)" required ${isSystemField ? 'disabled' : ''} style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
                    <option value="text" ${fieldType === 'text' ? 'selected' : ''}>Text</option>
                    <option value="email" ${fieldType === 'email' ? 'selected' : ''}>Email</option>
                    <option value="number" ${fieldType === 'number' ? 'selected' : ''}>Number</option>
                    <option value="textarea" ${fieldType === 'textarea' ? 'selected' : ''}>Textarea</option>
                    <option value="select" ${fieldType === 'select' ? 'selected' : ''}>Dropdown</option>
                    <option value="radio" ${fieldType === 'radio' ? 'selected' : ''}>Radio</option>
                    <option value="checkbox" ${fieldType === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                    <option value="date" ${fieldType === 'date' ? 'selected' : ''}>Date</option>
                    <option value="file" ${fieldType === 'file' ? 'selected' : ''}>File Upload</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Label *</label>
                <input type="text" id="fieldLabel" value="${label}" required style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Placeholder</label>
                <input type="text" id="fieldPlaceholder" value="${existingPlaceholder}" placeholder="Enter placeholder text" style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Help Text</label>
                <textarea id="fieldHelpText" rows="3" placeholder="Optional helper text shown below the field" style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">${existingHelpText}</textarea>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; color: #063A1C;">
                    <input type="checkbox" id="fieldRequired" ${existingRequired ? 'checked' : ''}>
                    <span>Required Field</span>
                </label>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; color: #063A1C;">
                    <input type="checkbox" id="fieldVisible" ${existingVisible ? 'checked' : ''}>
                    <span>Visible In Form</span>
                </label>
            </div>
            <div id="fieldOptionsGroup" class="form-group" style="margin-bottom: 16px; ${['select', 'radio', 'checkbox'].includes(fieldType) ? '' : 'display: none;'}">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #063A1C;">Options (one per line) *</label>
                <textarea id="fieldOptions" rows="5" placeholder="Option 1&#10;Option 2&#10;Option 3" ${['select', 'radio', 'checkbox'].includes(fieldType) ? 'required' : ''} style="width: 100%; padding: 8px; border: 1px solid #E5DED4; border-radius: 6px; color: #063A1C; background: white;">${existingOptions}</textarea>
                <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">Enter each option on a new line</small>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="button" onclick="saveFieldSettings()" style="flex: 1; padding: 10px; background: #205A44; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Save</button>
                <button type="button" onclick="closeFieldSettings()" style="flex: 1; padding: 10px; background: #e0e0e0; color: #063A1C; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
            </div>
        `;
        
        document.getElementById('fieldSettingsContent').innerHTML = settingsHTML;
        document.getElementById('fieldSettingsModal').classList.add('active');
    }

    function changeFieldType(newType) {
        if (!currentEditingField) return;
        
        // Update data attribute
        currentEditingField.dataset.fieldType = newType;
        
        // Show/hide options section
        const optionsGroup = document.getElementById('fieldOptionsGroup');
        const optionsTextarea = document.getElementById('fieldOptions');
        
        if (['select', 'radio', 'checkbox'].includes(newType)) {
            // Show options section
            if (optionsGroup) optionsGroup.style.display = 'block';
            if (optionsTextarea) {
                optionsTextarea.required = true;
                // If options are empty and switching to select/radio/checkbox, show placeholder
                if (!optionsTextarea.value && !currentEditingField.dataset.options) {
                    optionsTextarea.value = '';
                }
            }
        } else {
            // Hide options section
            if (optionsGroup) optionsGroup.style.display = 'none';
            if (optionsTextarea) {
                optionsTextarea.required = false;
                // Clear options data when switching away from select/radio/checkbox
                setStoredFieldOptions(currentEditingField, []);
            }
        }
        
        // Update preview immediately
        updateFieldPreview(newType, currentEditingField);
    }

    function updateFieldPreview(fieldType, fieldElement) {
        const preview = fieldElement.querySelector('.field-preview');
        if (!preview) return;
        
        // Get placeholder from input field if available
        let placeholder = '';
        const placeholderInput = document.getElementById('fieldPlaceholder');
        if (placeholderInput) {
            placeholder = placeholderInput.value || '';
        } else {
            // Fallback to existing placeholder
            const existingInput = fieldElement.querySelector('.field-preview input, .field-preview textarea, .field-preview select');
            if (existingInput && existingInput.placeholder) {
                placeholder = existingInput.placeholder;
            }
        }
        
        const existingOptions = getStoredFieldOptions(fieldElement);
        
        const fieldTypes = {
            text: `<input type="text" placeholder="${placeholder || 'Text input'}" disabled>`,
            email: `<input type="email" placeholder="${placeholder || 'Email address'}" disabled>`,
            number: `<input type="number" placeholder="${placeholder || 'Number'}" disabled>`,
            textarea: `<textarea rows="3" placeholder="${placeholder || 'Long text'}" disabled></textarea>`,
            date: `<input type="date" disabled>`,
            file: `<input type="file" disabled>`,
        };
        
        if (fieldType === 'select') {
            if (existingOptions.length > 0) {
                preview.innerHTML = '<select disabled><option>' + (placeholder || '-- Select --') + '</option>' + 
                    existingOptions.map(opt => `<option>${opt.trim()}</option>`).join('') + '</select>';
            } else {
                preview.innerHTML = '<select disabled><option>' + (placeholder || '-- Select --') + '</option><option>Option 1</option></select>';
            }
        } else if (fieldType === 'radio' || fieldType === 'checkbox') {
            if (existingOptions.length > 0) {
                preview.innerHTML = existingOptions.map(opt => 
                    `<div><label><input type="${fieldType}" disabled> ${opt.trim()}</label></div>`
                ).join('');
            } else {
                preview.innerHTML = `<div><label><input type="${fieldType}" disabled> Option 1</label></div>`;
            }
        } else {
            preview.innerHTML = fieldTypes[fieldType] || fieldTypes.text;
        }
    }

    function saveFieldSettings() {
        if (!currentEditingField) return;
        
        const fieldKey = document.getElementById('fieldKey').value;
        const label = document.getElementById('fieldLabel').value;
        const placeholder = document.getElementById('fieldPlaceholder').value;
        const helpText = document.getElementById('fieldHelpText').value;
        const required = document.getElementById('fieldRequired').checked;
        const visible = document.getElementById('fieldVisible').checked;
        const fieldType = document.getElementById('fieldType').value; // Get from dropdown
        
        // Update field element
        currentEditingField.querySelector('.field-label-input').value = label;
        currentEditingField.dataset.fieldKey = fieldKey;
        currentEditingField.dataset.fieldType = fieldType; // Update type
        currentEditingField.dataset.required = required;
        currentEditingField.dataset.isVisible = visible;
        currentEditingField.dataset.helpText = helpText;
        
        // Handle options for select/radio/checkbox
        if (['select', 'radio', 'checkbox'].includes(fieldType)) {
            const optionsText = document.getElementById('fieldOptions')?.value || '';
            if (optionsText) {
                const options = optionsText.split('\n').filter(opt => opt.trim());
                setStoredFieldOptions(currentEditingField, options);
            } else {
                // Clear options if empty
                setStoredFieldOptions(currentEditingField, []);
            }
        } else {
            // Clear options if changing from select/radio/checkbox to other type
            setStoredFieldOptions(currentEditingField, []);
        }
        
        // Update preview with new type and placeholder
        updateFieldPreview(fieldType, currentEditingField);
        markBuilderDirty();
        refreshBuilderSectionTabs();
        closeFieldSettings();
    }

    function closeFieldSettings() {
        document.getElementById('fieldSettingsModal').classList.remove('active');
        currentEditingField = null;
    }

    // Save Form (as draft)
    function saveForm() {
        document.getElementById('formStatus').value = 'draft';
        submitForm();
    }

    // Live and Publish
    function liveAndPublish() {
        document.getElementById('applyConfirmModal').classList.add('active');
    }

    // Confirm Apply
    function confirmApply() {
        document.getElementById('formStatus').value = 'published';
        closeApplyModal();
        submitForm();
    }

    // Close Apply Modal
    function closeApplyModal() {
        document.getElementById('applyConfirmModal').classList.remove('active');
    }

    // Submit Form
    function submitForm() {
        const fieldSettingsModal = document.getElementById('fieldSettingsModal');
        if (fieldSettingsModal && fieldSettingsModal.classList.contains('active') && currentEditingField) {
            saveFieldSettings();
        }

        const form = document.getElementById('formBuilderForm');
        
        // Ensure form action is correct (for edit vs create)
        @if($form)
        const formId = {{ $form->id }};
        // This is an edit - ensure we're using PUT method and correct action
        const updateUrl = '{{ route("admin.forms.update", $form->id) }}';
        form.action = updateUrl;
        // Ensure _method field exists for PUT
        let methodField = form.querySelector('input[name="_method"]');
        if (!methodField) {
            methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'PUT';
            form.appendChild(methodField);
        } else {
            methodField.value = 'PUT';
        }
        console.log('Submitting form for UPDATE - Form ID:', formId, 'Action:', form.action);
        @else
        const formId = null;
        console.log('Submitting form for CREATE - Action:', form.action);
        @endif
        
        const formData = {
            name: document.getElementById('formName').value,
            description: document.getElementById('formDescription').value,
            location_path: document.getElementById('locationPath').value,
            form_type: document.getElementById('formType').value,
            status: document.getElementById('formStatus').value,
            fields: [],
        };
        
        // Collect all fields
        document.querySelectorAll('.form-field').forEach((fieldElement, index) => {
            // Get field type - ensure we use the data attribute which is the source of truth
            const fieldType = fieldElement.dataset.fieldType || 'text';
            
            // Get placeholder from preview element or empty string
            let placeholder = '';
            const previewInput = fieldElement.querySelector('.field-preview input, .field-preview textarea');
            if (previewInput) {
                placeholder = previewInput.placeholder || '';
            } else {
                // For select, check first option text
                const selectElement = fieldElement.querySelector('.field-preview select');
                if (selectElement) {
                    const firstOption = selectElement.querySelector('option:first-child');
                    if (firstOption && firstOption.text !== '-- Select --') {
                        placeholder = firstOption.text;
                    }
                }
            }
            
            const field = {
                field_key: fieldElement.dataset.fieldKey || 'field_' + fieldType + '_' + index,
                field_type: fieldType, // Use the explicitly retrieved field type
                label: fieldElement.querySelector('.field-label-input')?.value || 'Untitled Field',
                placeholder: placeholder,
                help_text: fieldElement.dataset.helpText || '',
                required: fieldElement.dataset.required === 'true',
                order: index,
                section: fieldElement.closest('.form-section')?.querySelector('.section-title-input')?.value || 'default',
                is_system: fieldElement.dataset.isSystem === 'true',
                system_binding: fieldElement.dataset.systemBinding || null,
                is_visible: fieldElement.dataset.isVisible !== 'false',
            };
            
            // Add options if available
            if (['select', 'radio', 'checkbox'].includes(fieldType)) {
                const options = getStoredFieldOptions(fieldElement);
                if (options.length > 0) {
                    field.options = options;
                }
            }
            
            formData.fields.push(field);
        });
        
        if (formData.fields.length === 0) {
            alert('Please add at least one field to the form');
            return;
        }
        
        const fieldsJson = JSON.stringify(formData.fields);
        document.getElementById('fieldsData').value = fieldsJson;
        document.getElementById('settingsData').value = JSON.stringify(formData.settings || {});
        
        // Final check - ensure _method field is present before submission
        @if($form)
        let finalMethodField = form.querySelector('input[name="_method"]');
        if (!finalMethodField) {
            finalMethodField = document.createElement('input');
            finalMethodField.type = 'hidden';
            finalMethodField.name = '_method';
            finalMethodField.value = 'PUT';
            form.appendChild(finalMethodField);
        } else {
            finalMethodField.value = 'PUT';
        }
        console.log('Before submit - Method field:', finalMethodField ? finalMethodField.value : 'NOT FOUND', 'Form action:', form.action);
        @endif
        
        // Set flag to allow form submission
        isSubmittingViaFunction = true;
        form.submit();
    }

    // Form submission - Prevent direct form submission, use submitForm() instead
    document.getElementById('formBuilderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // All form submission should go through submitForm() function
        // which properly handles _method field for PUT requests
        return false;
    });
</script>
@endpush

@endsection

@php
function renderFieldPreview($field) {
    $html = '';
    switch($field->field_type) {
        case 'text':
        case 'email':
        case 'number':
        case 'date':
            $html = '<input type="' . $field->field_type . '" placeholder="' . ($field->placeholder ?? '') . '" disabled>';
            break;
        case 'textarea':
            $html = '<textarea rows="3" placeholder="' . ($field->placeholder ?? '') . '" disabled></textarea>';
            break;
        case 'select':
            $options = $field->options ?? [];
            $html = '<select disabled>';
            foreach($options as $option) {
                $html .= '<option>' . $option . '</option>';
            }
            $html .= '</select>';
            break;
        case 'radio':
        case 'checkbox':
            $options = $field->options ?? [];
            foreach($options as $option) {
                $html .= '<div><label><input type="' . $field->field_type . '" disabled> ' . $option . '</label></div>';
            }
            break;
        case 'file':
            $html = '<input type="file" disabled>';
            break;
        default:
            $html = '<input type="text" placeholder="' . ($field->placeholder ?? '') . '" disabled>';
    }
    return $html;
}
@endphp
