<!-- Transfer Leads Modal -->
<div class="modal fade" id="transferLeadsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Leads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="transfer-leads-form">
                    <div class="mb-3">
                        <label class="form-label">From Sales Executive <span class="text-danger">*</span></label>
                        <select class="form-select" id="transfer-from-telecaller" required>
                            <option value="">Select Sales Executive...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To Sales Executive <span class="text-danger">*</span></label>
                        <select class="form-select" id="transfer-to-telecaller" required>
                            <option value="">Select Sales Executive...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lead Types</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="transfer-not-interested">
                            <label class="form-check-label" for="transfer-not-interested">
                                Not Interested Leads
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="transfer-cnp">
                            <label class="form-check-label" for="transfer-cnp">
                                CNP Leads
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="transfer-reason">Transfer Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="transfer-reason" rows="3" required placeholder="Why are these leads being transferred?"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-left-right"></i> Transfer Leads
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
