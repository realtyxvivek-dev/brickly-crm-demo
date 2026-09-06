<!-- Lead Requirement Form Modal -->
<div id="leadRequirementFormModal" class="modal">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>Lead Requirement Form</h3>
            <button class="close-btn" onclick="closeLeadRequirementFormModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="leadFormContainer">
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="display: inline-block;"></div>
                    <p style="margin-top: 15px; color: #666;">Loading form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal functions are defined in tasks.blade.php @push('scripts') section --}}
@push('styles')
<style>
#leadRequirementFormModal.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

#leadRequirementFormModal .modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

#leadRequirementFormModal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e0e0e0;
}

#leadRequirementFormModal .modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

#leadRequirementFormModal .modal-body {
    padding: 24px;
}

/* Mobile responsiveness - prevent footer overlap */
@media (max-width: 768px) {
    #leadRequirementFormModal .modal-content {
        max-height: calc(100vh - 150px) !important;
        margin-bottom: 100px !important;
        padding-bottom: 100px !important;
    }
    
    #leadRequirementFormModal .modal-body {
        padding-bottom: 100px !important;
    }
    
    /* Ensure submit button is visible */
    #leadRequirementFormModal button[type="submit"] {
        margin-bottom: 80px !important;
    }
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #205A44;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
@endpush
