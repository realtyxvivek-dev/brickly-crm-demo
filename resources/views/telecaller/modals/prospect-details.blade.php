<!-- Prospect Details Modal -->
<div id="prospectDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Prospect</h3>
            <button class="close-btn" onclick="closeProspectDetailsModal()">&times;</button>
        </div>
        <form id="prospectDetailsForm">
            <input type="hidden" id="prospectAssignmentId">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" id="prospectCustomerName" readonly style="background: #f3f4f6;">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="prospectCustomerPhone" readonly style="background: #f3f4f6;">
            </div>
            <div class="form-group">
                <label>Budget <span style="color: red;">*</span></label>
                <input type="number" id="prospectBudget" required>
            </div>
            <div class="form-group">
                <label>Preferred Location</label>
                <input type="text" id="prospectLocation">
            </div>
            <div class="form-group">
                <label>Size</label>
                <input type="text" id="prospectSize">
            </div>
            <div class="form-group">
                <label>Purpose</label>
                <input type="text" id="prospectPurpose">
            </div>
            <div class="form-group">
                <label>Possession</label>
                <input type="text" id="prospectPossession">
            </div>
            <div class="form-group">
                <label>Remark <span style="color: red;">*</span></label>
                <textarea id="prospectRemark" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label>Assign To (Manager)</label>
                <select id="prospectAssignTo">
                    <option value="">Select Manager</option>
                </select>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeProspectDetailsModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Create Prospect</button>
            </div>
        </form>
    </div>
</div>

