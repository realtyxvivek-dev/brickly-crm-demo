<!-- Follow-up Modal -->
<div id="followUpModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Schedule Follow-up</h3>
            <button class="close-btn" onclick="closeFollowUpModal()">&times;</button>
        </div>
        <form id="followUpForm">
            <input type="hidden" id="followUpAssignmentId">
            <div class="form-group">
                <label>Follow-up Date <span style="color: red;">*</span></label>
                <input type="date" id="followUpDate" required min="">
            </div>
            <div class="form-group">
                <label>Follow-up Time <span style="color: red;">*</span></label>
                <input type="time" id="followUpTime" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="followUpNotes" rows="3"></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; box-shadow: 0 2px 4px rgba(21, 128, 61, 0.3);" onclick="openWhatsApp()">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFollowUpModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Follow-up</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Set minimum date to today
    document.getElementById('followUpDate').min = new Date().toISOString().split('T')[0];
</script>

