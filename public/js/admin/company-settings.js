// Company Settings JavaScript

// Tab switching
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-[#205A44]', 'text-[#205A44]');
        button.classList.add('border-transparent', 'text-[#B3B5B4]');
    });
    
    // Show selected tab content
    document.getElementById(`tab-content-${tabName}`).style.display = 'block';
    
    // Add active class to selected tab
    const activeTab = document.getElementById(`tab-${tabName}`);
    activeTab.classList.add('active', 'border-[#205A44]', 'text-[#205A44]');
    activeTab.classList.remove('border-transparent', 'text-[#B3B5B4]');
}

// Color picker functions
function updateColorPreview(name, value) {
    document.getElementById(`${name}_hex`).value = value;
    const previewId = `preview-${name}`;
    const previewElement = document.getElementById(previewId);
    if (previewElement) {
        previewElement.style.backgroundColor = value;
    }
}

function updateColorFromHex(name, hexValue) {
    if (/^#[0-9A-Fa-f]{6}$/.test(hexValue)) {
        document.getElementById(name).value = hexValue;
        const previewId = `preview-${name}`;
        const previewElement = document.getElementById(previewId);
        if (previewElement) {
            previewElement.style.backgroundColor = hexValue;
        }
    }
}

// Template definitions (matching ColorTemplateService)
const colorTemplates = {
    'royal_green': {
        primary_color: '#205A44',
        secondary_color: '#063A1C',
        accent_color: '#15803d',
        gradient_start: '#063A1C',
        gradient_end: '#205A44',
    },
    'royal_blue': {
        primary_color: '#3b82f6',
        secondary_color: '#1e3a8a',
        accent_color: '#60a5fa',
        gradient_start: '#1e3a8a',
        gradient_end: '#3b82f6',
    },
    'golden': {
        primary_color: '#f59e0b',
        secondary_color: '#92400e',
        accent_color: '#fbbf24',
        gradient_start: '#92400e',
        gradient_end: '#f59e0b',
    },
    'royal_red': {
        primary_color: '#dc2626',
        secondary_color: '#7f1d1d',
        accent_color: '#ef4444',
        gradient_start: '#7f1d1d',
        gradient_end: '#dc2626',
    },
    'ocean_blue': {
        primary_color: '#0284c7',
        secondary_color: '#0c4a6e',
        accent_color: '#38bdf8',
        gradient_start: '#0c4a6e',
        gradient_end: '#0284c7',
    },
    'sunset_orange': {
        primary_color: '#ea580c',
        secondary_color: '#9a3412',
        accent_color: '#fb923c',
        gradient_start: '#9a3412',
        gradient_end: '#ea580c',
    },
    'purple_royal': {
        primary_color: '#9333ea',
        secondary_color: '#581c87',
        accent_color: '#a855f7',
        gradient_start: '#581c87',
        gradient_end: '#9333ea',
    },
    'emerald_green': {
        primary_color: '#10b981',
        secondary_color: '#064e3b',
        accent_color: '#34d399',
        gradient_start: '#064e3b',
        gradient_end: '#10b981',
    },
    'crimson_red': {
        primary_color: '#e11d48',
        secondary_color: '#991b1b',
        accent_color: '#f43f5e',
        gradient_start: '#991b1b',
        gradient_end: '#e11d48',
    },
    'midnight_blue': {
        primary_color: '#4338ca',
        secondary_color: '#1e1b4b',
        accent_color: '#6366f1',
        gradient_start: '#1e1b4b',
        gradient_end: '#4338ca',
    },
};

// Select color template
function selectTemplate(templateName) {
    const template = colorTemplates[templateName];
    if (!template) return;

    // Update hidden input
    document.getElementById('color_template').value = templateName;

    // Update color inputs
    if (document.getElementById('primary_color')) {
        document.getElementById('primary_color').value = template.primary_color;
        updateColorPreview('primary_color', template.primary_color);
    }
    if (document.getElementById('secondary_color')) {
        document.getElementById('secondary_color').value = template.secondary_color;
        updateColorPreview('secondary_color', template.secondary_color);
    }
    if (document.getElementById('accent_color')) {
        document.getElementById('accent_color').value = template.accent_color;
        updateColorPreview('accent_color', template.accent_color);
    }

    // Update gradient inputs if they exist
    if (document.getElementById('gradient_start')) {
        document.getElementById('gradient_start').value = template.gradient_start;
    }
    if (document.getElementById('gradient_end')) {
        document.getElementById('gradient_end').value = template.gradient_end;
    }

    // Update template card selection
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('border-[#205A44]', 'ring-2', 'ring-[#205A44]');
        card.classList.add('border-gray-200');
        const cardText = card.querySelector('p:last-child');
        if (cardText) {
            cardText.innerHTML = '';
        }
    });

    const selectedCard = document.querySelector(`[data-template="${templateName}"]`);
    if (selectedCard) {
        selectedCard.classList.remove('border-gray-200');
        selectedCard.classList.add('border-[#205A44]', 'ring-2', 'ring-[#205A44]');
        const cardText = selectedCard.querySelector('p:last-child');
        if (cardText) {
            cardText.innerHTML = '<i class="fas fa-check-circle"></i> Active';
            cardText.className = 'text-xs text-center text-[#205A44] mt-1';
        }
    }

    // Apply template via AJAX
    fetch('/admin/company-settings/apply-template', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ template: templateName }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Color template applied successfully', 'success');
            // Reload page after a short delay to show updated colors
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.error || 'Failed to apply template', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while applying template', 'error');
    });
}

// File upload handler
function handleFileUpload(input, fileType) {
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('file_type', fileType);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    const progressContainer = document.getElementById(`upload-progress-${fileType}`);
    const progressBar = progressContainer.querySelector('div > div');
    
    progressContainer.classList.remove('hidden');
    progressBar.style.width = '0%';
    
    fetch('/admin/company-settings/upload-file', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(response => response.json())
    .then(data => {
        progressBar.style.width = '100%';
        
        if (data.success) {
            setTimeout(() => {
                showMessage('File uploaded successfully', 'success');
                location.reload();
            }, 500);
        } else {
            showMessage(data.error || 'Failed to upload file', 'error');
            progressContainer.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while uploading file', 'error');
        progressContainer.classList.add('hidden');
    });
}

// Delete file
function deleteFile(fileId, fileType) {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }
    
    fetch(`/admin/company-settings/file/${fileId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('File deleted successfully', 'success');
            location.reload();
        } else {
            showMessage(data.error || 'Failed to delete file', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while deleting file', 'error');
    });
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // Company Profile Form
    const companyProfileForm = document.getElementById('company-profile-form');
    if (companyProfileForm) {
        companyProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '/admin/company-settings/company-profile');
        });
    }
    
    // Branding Form
    const brandingForm = document.getElementById('branding-form');
    if (brandingForm) {
        brandingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '/admin/company-settings/branding');
        });
    }
    
    // Initialize color previews
    document.querySelectorAll('input[type="color"]').forEach(input => {
        const name = input.name;
        updateColorPreview(name, input.value);
    });
});

// Submit form via AJAX
function submitForm(form, url) {
    const formData = new FormData(form);
    
    // Handle checkbox for use_gradient - ensure it always sends a boolean value
    const useGradientCheckbox = form.querySelector('#use_gradient');
    if (useGradientCheckbox) {
        formData.set('use_gradient', useGradientCheckbox.checked ? '1' : '0');
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    
    fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    .then(response => {
        // Check if response is ok, if not parse error
        if (!response.ok) {
            return response.json().then(err => {
                throw err;
            });
        }
        return response.json();
    })
    .then(data => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        
        if (data.success) {
            showMessage(data.message || 'Settings saved successfully', 'success');
            // Reload page if branding was updated to show new colors
            if (data.reload || url.includes('branding')) {
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        } else {
            // Handle non-success response
            if (data.errors) {
                displayValidationErrors(data.errors);
            } else {
                showMessage(data.error || data.message || 'Failed to save settings', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        
        // Handle validation errors from Laravel (422 status)
        if (error.errors) {
            displayValidationErrors(error.errors);
        } else if (error.message) {
            showMessage(error.message, 'error');
        } else if (typeof error === 'string') {
            showMessage(error, 'error');
        } else {
            showMessage('An error occurred while saving settings. Please try again.', 'error');
        }
    });
}

// Show message
function showMessage(message, type) {
    const container = document.getElementById('message-container');
    const alert = document.getElementById('message-alert');
    
    if (!container || !alert) {
        console.error('Message container not found');
        return;
    }
    
    container.style.display = 'block';
    alert.className = `p-4 rounded-lg ${
        type === 'success'
            ? 'bg-green-100 border border-green-400 text-green-700' 
            : 'bg-red-100 border border-red-400 text-red-700'
    }`;
    
    // Handle both string and HTML content
    const messageContent = typeof message === 'string' ? message : JSON.stringify(message);
    alert.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${messageContent}</span>
            <button onclick="document.getElementById('message-container').style.display='none'" class="ml-4 text-lg font-bold hover:text-gray-800">&times;</button>
        </div>
    `;
    
    // Auto-hide after 5 seconds (only for success messages)
    if (type === 'success') {
        setTimeout(() => {
            container.style.display = 'none';
        }, 5000);
    }
}

// Display validation errors
function displayValidationErrors(errors) {
    let errorMessages = [];
    if (typeof errors === 'object' && errors !== null) {
        for (const [field, messages] of Object.entries(errors)) {
            if (Array.isArray(messages)) {
                errorMessages.push(`${field}: ${messages.join(', ')}`);
            } else if (typeof messages === 'string') {
                errorMessages.push(`${field}: ${messages}`);
            } else {
                errorMessages.push(`${field}: ${JSON.stringify(messages)}`);
            }
        }
    } else if (typeof errors === 'string') {
        errorMessages.push(errors);
    } else {
        errorMessages.push('Validation failed. Please check your input.');
    }
    
    showMessage(errorMessages.join('<br>'), 'error');
}
