@php($leadPhoneMaskedForViewer = app(\App\Services\PhonePrivacyService::class)->shouldMask(auth()->user()))
@once
<style>
    .lead-call-sheet-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.48);
        padding: 16px;
    }
    .lead-call-sheet-backdrop.show {
        display: flex;
    }
    .lead-call-sheet {
        width: min(420px, 100%);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
        border: 1px solid #dbe5df;
        overflow: hidden;
    }
    .lead-call-sheet-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 18px 14px;
        border-bottom: 1px solid #edf2ee;
    }
    .lead-call-sheet-title {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
    }
    .lead-call-sheet-phone {
        margin-top: 4px;
        font-size: 13px;
        color: #64748b;
        word-break: break-word;
    }
    .lead-call-sheet-close {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        cursor: pointer;
    }
    .lead-call-sheet-body {
        padding: 16px 18px 18px;
    }
    .lead-call-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .lead-call-option {
        width: 100%;
        min-height: 78px;
        border: 1px solid #dbe5df;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        text-align: center;
        cursor: pointer;
        font-weight: 700;
        line-height: 1.2;
        transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }
    .lead-call-option:hover:not(:disabled) {
        transform: translateY(-1px);
        background: #f8fafc;
    }
    .lead-call-option:disabled {
        opacity: 0.58;
        cursor: not-allowed;
    }
    .lead-call-option.primary {
        background: #063a1c;
        border-color: #063a1c;
        color: #ffffff;
    }
    .lead-call-option.primary:hover:not(:disabled) {
        background: #0f5a30;
    }
    .lead-call-option i {
        width: 24px;
        height: 24px;
        font-size: 20px;
        line-height: 24px;
        text-align: center;
    }
    .lead-call-option span {
        display: block;
        font-size: 14px;
    }
    .lead-call-status {
        display: none;
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 9px;
        font-size: 13px;
        line-height: 1.45;
    }
    .lead-call-status.show {
        display: block;
    }
    .lead-call-status.success {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .lead-call-status.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .lead-call-toast {
        position: fixed;
        top: 22px;
        right: 22px;
        z-index: 10001;
        max-width: min(420px, calc(100vw - 32px));
        padding: 12px 14px;
        border-radius: 10px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        font-size: 14px;
        font-weight: 700;
        opacity: 0;
        transform: translateY(-8px);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .lead-call-toast.show {
        opacity: 1;
        transform: translateY(0);
    }
    .lead-call-toast.success {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .lead-call-toast.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    @media (max-width: 640px) {
        .lead-call-sheet-backdrop {
            align-items: flex-end;
            padding: 0;
        }
        .lead-call-sheet {
            width: 100%;
            border-radius: 16px 16px 0 0;
        }
        .lead-call-toast {
            top: 12px;
            right: 12px;
            left: 12px;
            max-width: none;
        }
    }
</style>

<div id="leadCallSheet" class="lead-call-sheet-backdrop" aria-hidden="true">
    <div class="lead-call-sheet" role="dialog" aria-modal="true" aria-labelledby="leadCallSheetTitle">
        <div class="lead-call-sheet-head">
            <div>
                <h3 id="leadCallSheetTitle" class="lead-call-sheet-title">Call Lead</h3>
                <div id="leadCallSheetPhone" class="lead-call-sheet-phone">-</div>
            </div>
            <button type="button" class="lead-call-sheet-close" onclick="closeLeadCallMenu()" aria-label="Close call menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="lead-call-sheet-body">
            <div class="lead-call-options">
                <button type="button" id="leadCloudCallBtn" class="lead-call-option primary" onclick="startCloudCall()">
                    <i class="fas fa-cloud"></i>
                    <span>Cloud Call</span>
                </button>
                <button type="button" id="leadDialerCallBtn" class="lead-call-option" onclick="openLeadPhoneDialer()" @if($leadPhoneMaskedForViewer) style="display:none" @endif>
                    <i class="fas fa-phone"></i>
                    <span>Phone Dialer</span>
                </button>
            </div>
            <div id="leadCallStatus" class="lead-call-status"></div>
        </div>
    </div>
</div>

<script>
    window.leadCallMenuState = window.leadCallMenuState || {
        leadId: null,
        phone: '',
        taskId: null,
        dialerPhone: '',
        isCalling: false,
        afterCall: null,
        fallbackEndpoint: null,
        phoneMasked: @json($leadPhoneMaskedForViewer),
    };

    function normalizeLeadCallPhone(phone) {
        let digits = String(phone || '').replace(/\D/g, '');
        if (digits.startsWith('00')) digits = digits.slice(2);
        if (digits.length === 11 && digits.startsWith('0')) digits = digits.slice(1);
        if (digits.length === 12 && digits.startsWith('91')) return `+${digits}`;
        if (digits.length === 10) return `+91${digits}`;
        return digits ? `+${digits}` : '';
    }

    function getLeadCallToken() {
        if (typeof window.getToken === 'function') {
            const token = window.getToken();
            if (token) return token;
        }
        if (typeof window.getManagerApiToken === 'function') {
            const token = window.getManagerApiToken();
            if (token) return token;
        }
        if (window.API_TOKEN) return window.API_TOKEN;

        const metaToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
        if (metaToken) return metaToken.trim();

        return localStorage.getItem('crm_token')
            || localStorage.getItem('sales-executive_token')
            || localStorage.getItem('telecaller_token')
            || localStorage.getItem('api_token')
            || '';
    }

    function showLeadCallToast(message, type = 'success', duration = 3000) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type, duration);
            return;
        }
        if (typeof window.showLeadDetailAlert === 'function') {
            window.showLeadDetailAlert(message, type, duration);
            return;
        }
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, type, duration);
            return;
        }

        document.querySelectorAll('.lead-call-toast').forEach((toast) => toast.remove());
        const toast = document.createElement('div');
        toast.className = `lead-call-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => toast.remove(), 220);
        }, duration);
    }

    function setLeadCallStatus(message = '', type = 'success') {
        const status = document.getElementById('leadCallStatus');
        if (!status) return;
        status.className = `lead-call-status ${type}${message ? ' show' : ''}`;
        status.textContent = message;
    }

    function setLeadCallLoading(isLoading) {
        window.leadCallMenuState.isCalling = isLoading;
        const button = document.getElementById('leadCloudCallBtn');
        if (!button) return;
        button.disabled = isLoading || !window.leadCallMenuState.leadId;
        button.innerHTML = isLoading
            ? '<i class="fas fa-spinner fa-spin"></i><span>Requesting...</span>'
            : '<i class="fas fa-cloud"></i><span>Cloud Call</span>';
    }

    function openLeadCallMenu(leadId, phone, taskId = null, options = {}) {
        const dialerPhone = normalizeLeadCallPhone(phone);
        window.leadCallMenuState = {
            leadId,
            phone: phone || '',
            taskId: taskId || null,
            dialerPhone,
            isCalling: false,
            afterCall: typeof options.afterCall === 'function' ? options.afterCall : null,
            fallbackEndpoint: null,
            phoneMasked: @json($leadPhoneMaskedForViewer),
        };

        const sheet = document.getElementById('leadCallSheet');
        const phoneLabel = document.getElementById('leadCallSheetPhone');
        const dialerButton = document.getElementById('leadDialerCallBtn');
        const cloudButton = document.getElementById('leadCloudCallBtn');

        if (phoneLabel) phoneLabel.textContent = phone || 'No phone number';
        if (dialerButton) {
            dialerButton.disabled = !dialerPhone;
            dialerButton.style.display = window.leadCallMenuState.phoneMasked ? 'none' : '';
        }
        if (cloudButton) cloudButton.disabled = !leadId;
        setLeadCallStatus('');
        setLeadCallLoading(false);

        if (sheet) {
            sheet.classList.add('show');
            sheet.setAttribute('aria-hidden', 'false');
        }
    }

    function closeLeadCallMenu() {
        if (window.leadCallMenuState?.isCalling) return;
        hideLeadCallSheet();
        setLeadCallStatus('');
    }

    function hideLeadCallSheet() {
        const sheet = document.getElementById('leadCallSheet');
        if (sheet) {
            sheet.classList.remove('show');
            sheet.setAttribute('aria-hidden', 'true');
        }
    }

    async function openLeadPhoneDialer() {
        let phone = window.leadCallMenuState?.dialerPhone || normalizeLeadCallPhone(window.leadCallMenuState?.phone);
        const fallbackEndpoint = window.leadCallMenuState?.fallbackEndpoint;
        if (fallbackEndpoint) {
            try {
                const response = await fetch(fallbackEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        ...(getLeadCallToken() ? { 'Authorization': `Bearer ${getLeadCallToken()}` } : {}),
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.success) throw new Error(result.message || 'Dialer fallback is unavailable.');
                phone = result.dialer_phone || '';
            } catch (error) {
                setLeadCallStatus(error.message || 'Dialer fallback is unavailable.', 'error');
                return;
            }
        }
        if (!phone) {
            setLeadCallStatus('Phone number not available for this lead.', 'error');
            showLeadCallToast('Phone number not available for this lead.', 'error', 3000);
            return;
        }
        hideLeadCallSheet();
        window.location.href = `tel:${phone}`;
        runLeadCallAfterAction('dialer');
    }

    function runLeadCallAfterAction(source, result = null) {
        const callback = window.leadCallMenuState?.afterCall;
        if (typeof callback !== 'function') return;

        window.setTimeout(() => {
            try {
                callback({ source, result });
            } catch (error) {
                console.error('Lead call after-action failed:', error);
            }
        }, 500);
    }

    async function startCloudCall() {
        const state = window.leadCallMenuState || {};
        if (!state.leadId) {
            setLeadCallStatus('Lead not selected.', 'error');
            return;
        }

        const token = getLeadCallToken();
        setLeadCallLoading(true);
        setLeadCallStatus('');

        try {
            const payload = {
                lead_id: Number(state.leadId),
                phone_slot: 'primary',
            };
            if (state.taskId && String(state.taskId).startsWith('mt_')) {
                const managerTaskId = Number(String(state.taskId).slice(3));
                if (Number.isInteger(managerTaskId) && managerTaskId > 0) {
                    payload.task_id = managerTaskId;
                }
            }

            const response = await fetch(@json(url('/api/mcube/outbound-call')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => ({
                success: false,
                message: `HTTP ${response.status}`,
            }));

            if (response.ok && result.success) {
                const refText = result.refid ? ` Ref: ${result.refid}` : '';
                const message = 'Cloud call requested successfully.' + refText;
                setLeadCallStatus(message, 'success');
                showLeadCallToast('Cloud call requested successfully.', 'success', 3000);
                if (!state.phoneMasked && result.dialer_phone) {
                    window.leadCallMenuState.dialerPhone = result.dialer_phone;
                }
                hideLeadCallSheet();
                runLeadCallAfterAction('cloud', result);
                return;
            }

            const message = result.message || 'MCube outbound call failed.';
            setLeadCallStatus(message, 'error');
            showLeadCallToast(message, 'error', 4500);
            if (result.dialer_phone) {
                window.leadCallMenuState.dialerPhone = result.dialer_phone;
                const dialerButton = document.getElementById('leadDialerCallBtn');
                if (dialerButton) dialerButton.disabled = false;
            }
            if (result.fallback_available && result.fallback_endpoint) {
                window.leadCallMenuState.fallbackEndpoint = result.fallback_endpoint;
                const dialerButton = document.getElementById('leadDialerCallBtn');
                if (dialerButton) {
                    dialerButton.disabled = false;
                    dialerButton.style.display = '';
                    dialerButton.querySelector('span').textContent = 'Open Phone Dialer';
                }
            }
        } catch (error) {
            const message = error.message || 'Network error while requesting cloud call.';
            setLeadCallStatus(message, 'error');
            showLeadCallToast(message, 'error', 4500);
        } finally {
            setLeadCallLoading(false);
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-lead-call-trigger]');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            openLeadCallMenu(
                trigger.dataset.leadId,
                trigger.dataset.leadPhone || '',
                trigger.dataset.taskId || null
            );
            return;
        }

        const sheet = document.getElementById('leadCallSheet');
        if (sheet && event.target === sheet) {
            closeLeadCallMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLeadCallMenu();
        }
    });
</script>
@endonce
