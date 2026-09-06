@extends('layouts.app')

@section('title', 'Field Type Debug Test')
@section('page-title', 'Field Type Debug Test')

@section('content')
<div class="max-w-6xl mx-auto p-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-2xl font-bold mb-6">Field Type Persistence Debug Test</h2>
        
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Test Steps:</h3>
            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                <li>Open browser console (F12)</li>
                <li>Click "Create Test Field" button below</li>
                <li>Click "Settings" on the field</li>
                <li>Change Field Type from "Number" to "Dropdown"</li>
                <li>Add options: test1, test2, test3</li>
                <li>Click "Save" in modal</li>
                <li>Check console logs for field type values</li>
                <li>Click "Get Field Data" button to see current state</li>
                <li>Click "Settings" again and check what Field Type is selected</li>
            </ol>
        </div>

        <div class="mb-6">
            <button onclick="createTestField()" class="px-4 py-2 bg-blue-600 text-white rounded-lg mb-4">
                Create Test Field
            </button>
            <button onclick="getFieldData()" class="px-4 py-2 bg-green-600 text-white rounded-lg mb-4 ml-2">
                Get Field Data
            </button>
            <button onclick="clearLogs()" class="px-4 py-2 bg-gray-600 text-white rounded-lg mb-4 ml-2">
                Clear Logs
            </button>
        </div>

        <div id="testFieldContainer" class="mb-6"></div>

        <div class="bg-gray-100 rounded-lg p-4">
            <h3 class="font-semibold mb-2">Console Logs:</h3>
            <div id="logOutput" class="bg-white rounded p-4 h-64 overflow-y-auto font-mono text-sm">
                <div class="text-gray-500">Logs will appear here...</div>
            </div>
        </div>
    </div>
</div>

<script>
const LOG_ENDPOINT = 'http://127.0.0.1:7245/ingest/34ac4437-59f3-4ffc-9e90-b42f37f9634c';

function log(message, data = {}) {
    const timestamp = new Date().toLocaleTimeString();
    const logOutput = document.getElementById('logOutput');
    const logEntry = document.createElement('div');
    logEntry.className = 'mb-2 p-2 bg-gray-50 rounded';
    logEntry.innerHTML = `<span class="text-gray-500">[${timestamp}]</span> <strong>${message}</strong><br><pre class="text-xs mt-1">${JSON.stringify(data, null, 2)}</pre>`;
    logOutput.insertBefore(logEntry, logOutput.firstChild);
    
    // Also send to debug endpoint
    fetch(LOG_ENDPOINT, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            location: 'test-page',
            message: message,
            data: data,
            timestamp: Date.now(),
            sessionId: 'debug-session',
            runId: 'test-run',
            hypothesisId: 'TEST'
        })
    }).catch(() => {});
}

function clearLogs() {
    document.getElementById('logOutput').innerHTML = '<div class="text-gray-500">Logs cleared...</div>';
}

let testFieldElement = null;
let currentEditingField = null;

function createTestField() {
    const container = document.getElementById('testFieldContainer');
    const fieldId = 'test_field_' + Date.now();
    
    testFieldElement = document.createElement('div');
    testFieldElement.className = 'form-field border-2 border-gray-300 rounded-lg p-4 mb-4';
    testFieldElement.setAttribute('data-field-id', fieldId);
    testFieldElement.setAttribute('data-field-type', 'number'); // Start as number
    testFieldElement.setAttribute('data-field-key', 'budget');
    testFieldElement.setAttribute('data-required', 'false');
    testFieldElement.setAttribute('data-options', '');
    
    testFieldElement.innerHTML = `
        <div class="flex justify-between items-center mb-2">
            <input type="text" class="field-label-input border rounded px-2 py-1" value="Budget" placeholder="Field Label">
            <div class="flex gap-2">
                <button onclick="editTestField(this)" class="px-3 py-1 bg-blue-600 text-white rounded">Settings</button>
                <button onclick="removeTestField()" class="px-3 py-1 bg-red-600 text-white rounded">Delete</button>
            </div>
        </div>
        <div class="field-preview">
            <input type="number" placeholder="Enter budget" disabled class="border rounded px-2 py-1 w-full">
        </div>
    `;
    
    container.appendChild(testFieldElement);
    log('Test field created', {
        fieldId,
        fieldType: testFieldElement.dataset.fieldType,
        fieldKey: testFieldElement.dataset.fieldKey
    });
}

function removeTestField() {
    if (testFieldElement) {
        testFieldElement.remove();
        testFieldElement = null;
        log('Test field removed');
    }
}

function editTestField(btn) {
    if (!testFieldElement) return;
    
    currentEditingField = testFieldElement;
    const fieldType = testFieldElement.dataset.fieldType;
    const label = testFieldElement.querySelector('.field-label-input').value;
    const existingKey = testFieldElement.dataset.fieldKey || '';
    const existingPlaceholder = testFieldElement.querySelector('.field-preview input, .field-preview textarea, .field-preview select')?.placeholder || '';
    const existingRequired = testFieldElement.dataset.required === 'true';
    const existingOptions = testFieldElement.dataset.options ? JSON.parse(testFieldElement.dataset.options).join('\n') : '';
    
    log('Opening field settings', {
        fieldType,
        dataFieldType: testFieldElement.dataset.fieldType,
        label,
        existingKey
    });
    
    const modal = document.createElement('div');
    modal.id = 'testModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Field Settings</h3>
                <button onclick="closeTestModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div id="testModalContent">
                <div class="mb-4">
                    <label class="block mb-2 font-semibold">Field Key *</label>
                    <input type="text" id="testFieldKey" value="${existingKey}" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block mb-2 font-semibold">Field Type *</label>
                    <select id="testFieldType" onchange="changeTestFieldType(this.value)" class="w-full border rounded px-3 py-2" required>
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
                <div class="mb-4">
                    <label class="block mb-2 font-semibold">Label *</label>
                    <input type="text" id="testFieldLabel" value="${label}" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block mb-2 font-semibold">Placeholder</label>
                    <input type="text" id="testFieldPlaceholder" value="${existingPlaceholder}" class="w-full border rounded px-3 py-2">
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="testFieldRequired" ${existingRequired ? 'checked' : ''} class="mr-2">
                        <span>Required Field</span>
                    </label>
                </div>
                <div id="testFieldOptionsGroup" class="mb-4 ${['select', 'radio', 'checkbox'].includes(fieldType) ? '' : 'hidden'}">
                    <label class="block mb-2 font-semibold">Options (one per line) *</label>
                    <textarea id="testFieldOptions" rows="5" ${['select', 'radio', 'checkbox'].includes(fieldType) ? 'required' : ''} class="w-full border rounded px-3 py-2">${existingOptions}</textarea>
                </div>
                <div class="flex gap-2">
                    <button onclick="saveTestFieldSettings()" class="flex-1 px-4 py-2 bg-green-600 text-white rounded">Save</button>
                    <button onclick="closeTestModal()" class="flex-1 px-4 py-2 bg-gray-600 text-white rounded">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    log('Modal opened', { selectedFieldType: fieldType });
}

function changeTestFieldType(newType) {
    if (!currentEditingField) return;
    
    log('Field type changed in dropdown', {
        oldType: currentEditingField.dataset.fieldType,
        newType: newType
    });
    
    currentEditingField.dataset.fieldType = newType;
    
    const optionsGroup = document.getElementById('testFieldOptionsGroup');
    const optionsTextarea = document.getElementById('testFieldOptions');
    
    if (['select', 'radio', 'checkbox'].includes(newType)) {
        optionsGroup.classList.remove('hidden');
        optionsTextarea.required = true;
    } else {
        optionsGroup.classList.add('hidden');
        optionsTextarea.required = false;
    }
    
    log('After changing type', {
        dataFieldType: currentEditingField.dataset.fieldType,
        verified: currentEditingField.dataset.fieldType === newType
    });
}

function saveTestFieldSettings() {
    if (!currentEditingField) {
        log('Error: currentEditingField is null', {});
        return;
    }
    
    const fieldKey = document.getElementById('testFieldKey').value;
    const label = document.getElementById('testFieldLabel').value;
    const placeholder = document.getElementById('testFieldPlaceholder').value;
    const required = document.getElementById('testFieldRequired').checked;
    const fieldType = document.getElementById('testFieldType').value;
    
    log('Saving field settings - BEFORE UPDATE', {
        fieldTypeFromDropdown: fieldType,
        currentDataFieldType: currentEditingField.dataset.fieldType,
        fieldKey,
        label
    });
    
    // Store reference before closing modal
    const fieldElement = currentEditingField;
    const oldType = fieldElement.dataset.fieldType;
    
    // Update field element
    fieldElement.querySelector('.field-label-input').value = label;
    fieldElement.dataset.fieldKey = fieldKey;
    fieldElement.dataset.fieldType = fieldType;
    fieldElement.dataset.required = required;
    
    log('Saving field settings - AFTER UPDATE', {
        oldType,
        newType: fieldElement.dataset.fieldType,
        verified: fieldElement.dataset.fieldType === fieldType,
        allDataAttrs: {
            fieldType: fieldElement.dataset.fieldType,
            fieldKey: fieldElement.dataset.fieldKey,
            required: fieldElement.dataset.required
        }
    });
    
    // Handle options
    if (['select', 'radio', 'checkbox'].includes(fieldType)) {
        const optionsText = document.getElementById('testFieldOptions')?.value || '';
        if (optionsText) {
            const options = optionsText.split('\n').filter(opt => opt.trim());
            fieldElement.dataset.options = JSON.stringify(options);
        } else {
            fieldElement.dataset.options = '';
        }
    } else {
        fieldElement.dataset.options = '';
    }
    
    // Update preview - pass placeholder from modal before closing
    updateTestFieldPreview(fieldType, fieldElement, placeholder);
    
    // Close modal (this sets currentEditingField to null)
    closeTestModal();
    
    // Use stored reference for final log
    log('Field settings saved - FINAL STATE', {
        dataFieldType: fieldElement.dataset.fieldType,
        previewHTML: fieldElement.querySelector('.field-preview')?.innerHTML.substring(0, 100) || 'N/A'
    });
}

function updateTestFieldPreview(fieldType, fieldElement, placeholder = '') {
    const preview = fieldElement.querySelector('.field-preview');
    if (!preview) return;
    
    // Use passed placeholder or try to get from modal, or use empty string
    if (!placeholder) {
        const placeholderInput = document.getElementById('testFieldPlaceholder');
        placeholder = placeholderInput ? placeholderInput.value : '';
    }
    
    let existingOptions = [];
    try {
        if (fieldElement.dataset.options) {
            existingOptions = JSON.parse(fieldElement.dataset.options);
        }
    } catch(e) {
        existingOptions = [];
    }
    
    if (fieldType === 'select') {
        if (existingOptions.length > 0) {
            preview.innerHTML = '<select disabled class="border rounded px-2 py-1 w-full"><option>-- Select --</option>' + 
                existingOptions.map(opt => `<option>${opt.trim()}</option>`).join('') + '</select>';
        } else {
            preview.innerHTML = '<select disabled class="border rounded px-2 py-1 w-full"><option>-- Select --</option><option>Option 1</option></select>';
        }
    } else if (fieldType === 'radio' || fieldType === 'checkbox') {
        if (existingOptions.length > 0) {
            preview.innerHTML = existingOptions.map(opt => 
                `<div><label><input type="${fieldType}" disabled> ${opt.trim()}</label></div>`
            ).join('');
        } else {
            preview.innerHTML = `<div><label><input type="${fieldType}" disabled> Option 1</label></div>`;
        }
    } else if (fieldType === 'textarea') {
        preview.innerHTML = `<textarea rows="3" placeholder="${placeholder || 'Long text'}" disabled class="border rounded px-2 py-1 w-full"></textarea>`;
    } else {
        preview.innerHTML = `<input type="${fieldType}" placeholder="${placeholder || fieldType + ' input'}" disabled class="border rounded px-2 py-1 w-full">`;
    }
}

function closeTestModal() {
    const modal = document.getElementById('testModal');
    if (modal) {
        modal.remove();
    }
    currentEditingField = null;
}

function getFieldData() {
    if (!testFieldElement) {
        log('No test field exists');
        return;
    }
    
    const data = {
        fieldId: testFieldElement.dataset.fieldId,
        fieldType: testFieldElement.dataset.fieldType,
        fieldKey: testFieldElement.dataset.fieldKey,
        required: testFieldElement.dataset.required,
        options: testFieldElement.dataset.options,
        label: testFieldElement.querySelector('.field-label-input')?.value,
        previewHTML: testFieldElement.querySelector('.field-preview')?.innerHTML,
        allDataAttributes: {}
    };
    
    // Get all data attributes
    Object.keys(testFieldElement.dataset).forEach(key => {
        data.allDataAttributes[key] = testFieldElement.dataset[key];
    });
    
    log('Current Field Data', data);
    
    // Also check what would be collected for submission
    const fieldTypeForSubmission = testFieldElement.dataset.fieldType || 'text';
    log('Field Type for Submission', {
        fieldType: fieldTypeForSubmission,
        dataAttribute: testFieldElement.dataset.fieldType,
        match: fieldTypeForSubmission === testFieldElement.dataset.fieldType
    });
}
</script>
@endsection
