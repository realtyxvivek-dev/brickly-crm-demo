document.addEventListener('DOMContentLoaded', function() {
    // Handle dependent fields (e.g., TYPE depends on CATEGORY)
    handleDependentFields();
    
    // Handle conditional fields (e.g., follow-up date/time when status = 'Follow Up')
    handleConditionalFields();
});

/**
 * Handle dependent fields - when parent field changes, update child field options
 */
function handleDependentFields() {
    // Find all fields with dependent_field attribute
    const dependentFields = document.querySelectorAll('[data-dependent-field]');
    
    dependentFields.forEach(function(dependentField) {
        const parentFieldKey = dependentField.getAttribute('data-dependent-field');
        const parentField = document.getElementById('field_' + parentFieldKey);
        
        if (parentField && dependentField.tagName === 'SELECT') {
            // Disable dependent field initially if parent has no value
            if (!parentField.value) {
                dependentField.disabled = true;
                dependentField.value = '';
            }
            
            // Listen to parent field changes
            parentField.addEventListener('change', function() {
                if (this.value) {
                    dependentField.disabled = false;
                    updateDependentField(dependentField, this.value);
                } else {
                    dependentField.disabled = true;
                    dependentField.value = '';
                    // Reset to default options
                    const defaultOptions = dependentField.getAttribute('data-default-options');
                    if (defaultOptions) {
                        const options = JSON.parse(defaultOptions);
                        populateSelectOptions(dependentField, options);
                    }
                }
            });
            
            // Trigger on page load if parent already has value
            if (parentField.value) {
                dependentField.disabled = false;
                updateDependentField(dependentField, parentField.value);
            }
        }
    });
}

/**
 * Update dependent field options based on parent value
 */
function updateDependentField(dependentField, parentValue) {
    const conditionsJson = dependentField.getAttribute('data-dependent-conditions');
    
    if (!conditionsJson) return;
    
    try {
        const conditions = JSON.parse(conditionsJson);
        let newOptions = [];
        
        // Check if conditions is an object with keys matching parent values
        if (typeof conditions === 'object' && !Array.isArray(conditions)) {
            if (conditions[parentValue]) {
                newOptions = conditions[parentValue];
            } else if (conditions['show_when'] && conditions['show_when'].includes(parentValue)) {
                // This is for conditional visibility, not option updates
                return;
            }
        }
        
        // Update select options
        if (dependentField.tagName === 'SELECT' && newOptions.length > 0) {
            const currentValue = dependentField.value;
            const label = dependentField.closest('.form-field-group')?.querySelector('label')?.textContent?.replace('*', '').trim() || 'Type';
            dependentField.innerHTML = '<option value="">-- Select ' + label + ' --</option>';
            
            newOptions.forEach(function(option) {
                const optionElement = document.createElement('option');
                optionElement.value = option;
                optionElement.textContent = option;
                if (currentValue === option) {
                    optionElement.selected = true;
                }
                dependentField.appendChild(optionElement);
            });
            
            // Reset value if current value is not in new options
            if (!newOptions.includes(currentValue)) {
                dependentField.value = '';
            }
        }
    } catch (e) {
        console.error('Error parsing dependent conditions:', e);
    }
}

/**
 * Populate select field with options
 */
function populateSelectOptions(selectElement, options) {
    const currentValue = selectElement.value;
    const label = selectElement.closest('.form-field-group')?.querySelector('label')?.textContent?.replace('*', '').trim() || 'Field';
    selectElement.innerHTML = '<option value="">-- Select ' + label + ' --</option>';
    
    options.forEach(function(option) {
        const optionElement = document.createElement('option');
        optionElement.value = option;
        optionElement.textContent = option;
        if (currentValue === option) {
            optionElement.selected = true;
        }
        selectElement.appendChild(optionElement);
    });
}

/**
 * Handle conditional fields - show/hide fields based on another field's value
 */
function handleConditionalFields() {
    // Find all fields with dependent_field attribute that have show_when conditions
    const conditionalFields = document.querySelectorAll('[data-dependent-field]');
    
    conditionalFields.forEach(function(conditionalField) {
        const parentFieldKey = conditionalField.getAttribute('data-dependent-field');
        const parentField = document.getElementById('field_' + parentFieldKey);
        const fieldGroup = conditionalField.closest('.form-field-group');
        
        if (parentField && fieldGroup) {
            const conditionsJson = conditionalField.getAttribute('data-dependent-conditions');
            
            if (conditionsJson) {
                try {
                    const conditions = JSON.parse(conditionsJson);
                    
                    if (conditions['show_when'] && Array.isArray(conditions['show_when'])) {
                        // Check visibility on page load
                        checkConditionalVisibility(fieldGroup, parentField.value, conditions['show_when']);
                        
                        // Listen to parent field changes
                        parentField.addEventListener('change', function() {
                            checkConditionalVisibility(fieldGroup, parentField.value, conditions['show_when']);
                        });
                    }
                } catch (e) {
                    console.error('Error parsing conditional conditions:', e);
                }
            }
        }
    });
}

/**
 * Show or hide field based on condition
 */
function checkConditionalVisibility(fieldGroup, parentValue, showWhenValues) {
    if (showWhenValues.includes(parentValue)) {
        fieldGroup.style.display = 'block';
        // Make required fields actually required when visible
        const requiredFields = fieldGroup.querySelectorAll('[required]');
        requiredFields.forEach(function(field) {
            field.setAttribute('required', 'required');
        });
    } else {
        fieldGroup.style.display = 'none';
        // Remove required attribute when hidden
        const requiredFields = fieldGroup.querySelectorAll('[required]');
        requiredFields.forEach(function(field) {
            field.removeAttribute('required');
            field.value = ''; // Clear value when hidden
        });
    }
}

/**
 * Form validation before submit
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('centralizedLeadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Additional validation can be added here
            // For now, let browser handle HTML5 validation
            
            // Ensure conditional required fields are validated
            const visibleRequiredFields = form.querySelectorAll('.form-field-group:not([style*="display: none"]) [required]');
            let isValid = true;
            
            visibleRequiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields.');
                return false;
            }
        });
    }
});
