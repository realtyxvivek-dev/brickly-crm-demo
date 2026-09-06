<!-- Interest Modal -->
<div id="interestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Call Action</h3>
            <button class="close-btn" onclick="closeInterestModal()">&times;</button>
        </div>
        <div id="interestModalCustomerInfo" style="margin-bottom: 20px;">
            <h4 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;" id="interestCustomerName"></h4>
            <p><a href="#" id="interestCustomerPhone" class="phone"></a></p>
        </div>
        <div class="btn-group" style="grid-template-columns: 1fr;">
            <button class="btn btn-success" onclick="handleInterested()">Interested</button>
            <button class="btn btn-warning" onclick="handleNotInterested()">Not Interested</button>
            <button class="btn btn-secondary" onclick="handleCNP()">CNP (Call Not Picked)</button>
            <button class="btn btn-primary" onclick="handleFollowUp()">Follow-up</button>
            <button class="btn" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; box-shadow: 0 2px 4px rgba(21, 128, 61, 0.3);" onclick="openWhatsApp()">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </button>
            <button class="btn btn-danger" onclick="handleBroker()">Broker (Blacklist)</button>
        </div>
    </div>
</div>

