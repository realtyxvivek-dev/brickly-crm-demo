@if(auth()->user()?->isAdmin() && in_array($lead->status, ['junk', 'not_interested', 'dead'], true) && !$lead->is_blocked)
@php
    $reopenUsers = app(\App\Services\LeadReopenService::class)->candidates()->get(['id', 'name']);
    $reopenOwner = $lead->currentAssignment?->assigned_to;
@endphp
<button type="button" id="reopenLeadButton" class="flex items-center justify-center gap-2 px-4 py-2 bg-white text-emerald-900 rounded-lg border border-white font-medium text-sm">
    <i class="fas fa-undo" aria-hidden="true"></i> Reopen Lead
</button>
<dialog id="reopenLeadDialog" aria-labelledby="reopenLeadTitle">
    <form id="reopenLeadForm">
        <header><h2 id="reopenLeadTitle">Reopen Lead</h2><button type="button" data-reopen-close aria-label="Close" title="Close"><i class="fas fa-times" aria-hidden="true"></i></button></header>
        <div class="reopen-fields">
            <p>Current stage: <strong>{{ ucwords(str_replace('_', ' ', $lead->status)) }}</strong></p>
            <label for="reopenSalesperson">Salesperson</label>
            <select id="reopenSalesperson" name="assigned_to" required><option value="">Select salesperson</option>
                @foreach($reopenUsers as $candidate)<option value="{{ $candidate->id }}" @selected($candidate->id == $reopenOwner)>{{ $candidate->name }} #{{ $candidate->id }}</option>@endforeach
            </select>
            <label for="reopenReason">Reopen reason</label>
            <textarea id="reopenReason" name="reason" rows="4" maxlength="2000" required></textarea>
            <p id="reopenLeadError" role="alert" hidden></p>
        </div>
        <footer><button type="button" data-reopen-close>Cancel</button><button type="submit" id="reopenLeadSubmit">Reopen as New</button></footer>
    </form>
</dialog>
@push('styles')
<style>
#reopenLeadDialog{position:fixed;inset:0;margin:auto;width:calc(100% - 32px);max-width:480px;max-height:calc(100dvh - 32px);padding:0;border:1px solid #cbd5cf;border-radius:8px;background:#fff;color:#19382c;overflow:auto;box-shadow:0 16px 60px #0003}
#reopenLeadDialog::backdrop{background:#10291f88}
#reopenLeadDialog header,#reopenLeadDialog footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px}
#reopenLeadDialog header{border-bottom:1px solid #dce5df}#reopenLeadDialog h2{font-size:20px;font-weight:600;margin:0}
#reopenLeadDialog .reopen-fields{display:grid;gap:10px;padding:20px}#reopenLeadDialog p{margin:0 0 8px;font-size:14px}
#reopenLeadDialog label{font-size:14px;font-weight:600}#reopenLeadDialog select,#reopenLeadDialog textarea{width:100%;min-width:0;padding:10px;border:1px solid #bbcfc2;border-radius:4px;font:inherit;background:white;color:#19382c}
#reopenLeadDialog footer{border-top:1px solid #dce5df;justify-content:flex-end;flex-wrap:wrap}
#reopenLeadDialog button{padding:10px 14px;border:1px solid #bbcfc2;border-radius:4px;background:white;color:#19382c;font:inherit;cursor:pointer}
#reopenLeadDialog #reopenLeadSubmit{background:#126344;color:white;border-color:#126344}#reopenLeadDialog button:disabled{opacity:.6;cursor:wait}
#reopenLeadError{color:#b42318;overflow-wrap:anywhere}#reopenLeadDialog [hidden]{display:none}
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('reopenLeadDialog');
    const form = document.getElementById('reopenLeadForm');
    const submit = document.getElementById('reopenLeadSubmit');
    const error = document.getElementById('reopenLeadError');
    let busy = false;
    document.body.appendChild(dialog);
    document.getElementById('reopenLeadButton').addEventListener('click', () => { error.hidden = true; dialog.showModal(); });
    dialog.querySelectorAll('[data-reopen-close]').forEach(button => button.addEventListener('click', () => { if (!busy) dialog.close(); }));
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        busy = true; submit.disabled = true; error.hidden = true;
        try {
            const response = await fetch(@json(route('leads.reopen', $lead)), {
                method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify({assigned_to: form.elements.assigned_to.value, reason: form.elements.reason.value, version: @json(app(\App\Services\LeadReopenService::class)->version($lead))})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to reopen lead. Please try again.');
            window.location.reload();
        } catch (failure) {
            error.textContent = failure.message || 'Unable to reopen lead. Please try again.'; error.hidden = false;
            busy = false; submit.disabled = false;
        }
    });
});
</script>
@endpush
@endif
