<!-- Lead Requirement Form Modal -->
<div id="leadRequirementFormModal" class="modal">
    <div class="modal-content lead-requirement-sheet" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
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
    border-radius: 14px;
    box-shadow: 0 24px 70px rgba(6, 58, 28, 0.25);
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

#leadRequirementFormModal .lead-form-section {
    margin-bottom: 22px;
}

#leadRequirementFormModal .lead-form-section-title {
    font-size: 16px;
    font-weight: 800;
    color: #063A1C;
    margin-bottom: 14px;
    padding-bottom: 9px;
    border-bottom: 1px solid #dce7e0;
}

#leadRequirementFormModal .lead-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

#leadRequirementFormModal .lead-form-field label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #263b31;
    margin-bottom: 7px;
}

#leadRequirementFormModal .lead-form-field input,
#leadRequirementFormModal .lead-form-field select {
    width: 100%;
    min-width: 0;
    height: 46px;
    padding: 10px 12px;
    border: 1.5px solid #d9e4dd;
    border-radius: 12px;
    background: #ffffff;
    color: #10231b;
    font-size: 14px;
    font-weight: 700;
    box-sizing: border-box;
}

#leadRequirementFormModal .lead-form-field input:focus,
#leadRequirementFormModal .lead-form-field select:focus {
    outline: none;
    border-color: #0b6b48;
    box-shadow: 0 0 0 3px rgba(11, 107, 72, 0.12);
}

#leadRequirementFormModal .lead-form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: stretch;
    padding-top: 18px;
    border-top: 1px solid #dce7e0;
    margin-top: 22px;
}

#leadRequirementFormModal .lead-form-cancel-btn,
#leadRequirementFormModal .lead-form-submit-btn {
    min-height: 46px;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 900;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.18;
    margin-bottom: 0 !important;
}

#leadRequirementFormModal .lead-form-cancel-btn {
    border: 1px solid #d9e4dd;
    background: #ffffff;
    color: #23382d;
}

#leadRequirementFormModal .lead-form-submit-btn {
    border: none;
    background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
    color: #ffffff;
    box-shadow: 0 10px 22px rgba(6, 58, 28, 0.18);
}

/* Mobile responsiveness - prevent footer overlap */
@media (max-width: 768px) {
    #leadRequirementFormModal.modal.active {
        align-items: flex-end;
        justify-content: center;
        padding: 0;
    }

    #leadRequirementFormModal .modal-content {
        width: 100% !important;
        max-width: 100% !important;
        height: calc(100vh - 142px) !important;
        max-height: calc(100vh - 142px) !important;
        margin: 0 !important;
        margin-bottom: 82px !important;
        padding: 0 !important;
        border-radius: 18px 18px 0 0 !important;
        overflow: hidden !important;
    }

    #leadRequirementFormModal .modal-header {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 16px 18px;
        background: #ffffff;
    }

    #leadRequirementFormModal .modal-header h3 {
        font-size: 18px;
        font-weight: 900;
    }

    #leadRequirementFormModal .modal-body {
        height: calc(100% - 66px);
        overflow-y: auto;
        padding: 16px 16px 120px !important;
        box-sizing: border-box;
    }

    #leadRequirementFormModal .lead-form-grid {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }

    #leadRequirementFormModal .lead-form-section {
        margin-bottom: 18px;
    }

    #leadRequirementFormModal .lead-form-section-title {
        font-size: 15px;
        margin-bottom: 12px;
    }

    #leadRequirementFormModal .lead-form-field input,
    #leadRequirementFormModal .lead-form-field select {
        height: 48px;
        font-size: 15px;
    }

    #leadRequirementFormModal .lead-form-actions {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 22px 0 0;
        padding: 14px 0 0;
        background: #ffffff;
        border-top: 1px solid #dce7e0;
        box-shadow: none;
    }

    #leadRequirementFormModal .lead-form-cancel-btn,
    #leadRequirementFormModal .lead-form-submit-btn {
        width: 100%;
        min-height: 48px;
        padding: 10px 8px !important;
        font-size: 14px;
        white-space: normal;
        line-height: 1.2;
        margin-bottom: 0 !important;
    }

    #leadRequirementFormModal .lead-form-submit-btn i {
        margin-right: 6px !important;
        font-size: 13px;
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
