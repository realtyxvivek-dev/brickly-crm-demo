@php
    $mcubeLeadId = $leadId ?? null;
    $mcubeTaskId = $taskId ?? null;
    $mcubePhone = $phone ?? null;
    $mcubeLabel = $label ?? 'MCUBE Call';
    $mcubeClass = $class ?? 'inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700';
@endphp

@if($mcubeLeadId && $mcubePhone)
    <button type="button"
        class="{{ $mcubeClass }}"
        data-mcube-click-call
        data-lead-id="{{ $mcubeLeadId }}"
        data-task-id="{{ $mcubeTaskId }}">
        <i class="fas fa-phone-volume"></i>
        {{ $mcubeLabel }}
    </button>
@endif

@once
@push('scripts')
<script>
(() => {
    if (window.__mcubeClickCallBound) return;
    window.__mcubeClickCallBound = true;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function dialerPhone(phone) {
        const digits = String(phone || '').replace(/\D/g, '');
        if (digits.length === 10) return '+91' + digits;
        if (digits.length === 12 && digits.startsWith('91')) return '+' + digits;
        return digits ? '+' + digits : '';
    }

    function notify(message, success = false) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, success ? 'success' : 'warning');
            return;
        }
        alert(message);
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mcube-click-call]');
        if (!button) return;

        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calling...';
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        };
        if (window.API_TOKEN) {
            headers.Authorization = 'Bearer ' + window.API_TOKEN;
        }

        try {
            const response = await fetch('/api/mcube/outbound-call', {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({
                    lead_id: Number(button.dataset.leadId),
                    task_id: button.dataset.taskId ? Number(button.dataset.taskId) : null,
                    phone_slot: 'primary',
                }),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                notify(result.message || 'Call initiated via MCube.', true);
                return;
            }

            notify(result.message || 'MCUBE call failed.');
        } catch (error) {
            notify('MCUBE call failed.');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    });
})();
</script>
@endpush
@endonce
