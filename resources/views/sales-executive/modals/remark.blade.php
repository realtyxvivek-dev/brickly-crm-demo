<!-- Remark Modal -->
<div id="remarkModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="remarkModalTitle">Add Remark</h3>
            <button class="close-btn" onclick="closeRemarkModal()">&times;</button>
        </div>
        <form id="remarkForm">
            <input type="hidden" id="remarkAssignmentId">
            <input type="hidden" id="remarkAction">
            <div class="form-group">
                <label>Remark <span style="color: red;">*</span></label>
                <textarea id="remarkText" rows="4" required></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeRemarkModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

