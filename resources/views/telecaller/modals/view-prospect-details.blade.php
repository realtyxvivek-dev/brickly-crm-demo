<!-- View Prospect Details Modal -->
<div id="viewProspectDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Prospect Details</h3>
            <button class="close-btn" onclick="closeViewProspectDetailsModal()">&times;</button>
        </div>
        <div style="padding: 20px 0;">
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Customer Name</label>
                <p id="prospectDetailName" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Phone</label>
                <p style="margin-top: 4px;">
                    <a href="#" id="prospectDetailPhone" class="phone" style="color: #205A44; text-decoration: none;">-</a>
                </p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Email</label>
                <p id="prospectDetailEmail" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Budget</label>
                <p id="prospectDetailBudget" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Preferred Location</label>
                <p id="prospectDetailLocation" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Size</label>
                <p id="prospectDetailSize" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Purpose</label>
                <p id="prospectDetailPurpose" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Possession</label>
                <p id="prospectDetailPossession" style="margin-top: 4px; color: #666;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Remark</label>
                <p id="prospectDetailRemark" style="margin-top: 4px; color: #666; white-space: pre-wrap;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Verification Status</label>
                <p id="prospectDetailStatus" style="margin-top: 4px;">-</p>
            </div>
            <div class="form-group">
                <label style="font-weight: 600; color: #333;">Created At</label>
                <p id="prospectDetailCreated" style="margin-top: 4px; color: #666;">-</p>
            </div>
        </div>
        <div class="btn-group" style="grid-template-columns: 1fr;">
            <button type="button" class="btn btn-secondary" onclick="closeViewProspectDetailsModal()">Close</button>
        </div>
    </div>
</div>

