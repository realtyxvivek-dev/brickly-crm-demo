@extends('layouts.app')
@section('title', 'Rule History - ' . $rule->name)
@section('page-title', 'Assignment History')

@push('styles')
<style>
.ah-header { background:#fff;border-radius:14px;border:1px solid #e5e7eb;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 4px rgba(0,0,0,.05); }
.ah-rule-badge { display:inline-flex;align-items:center;gap:8px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600; }
.ah-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px; }
.ah-stat { background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:16px 20px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05); }
.ah-stat-val { font-size:24px;font-weight:700;color:#111827; }
.ah-stat-lbl { font-size:12px;color:#6b7280;margin-top:2px; }
.ah-table-wrap { background:#fff;border-radius:14px;border:1px solid #e5e7eb;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05); }
.ah-table { width:100%;border-collapse:collapse; }
.ah-table th { background:#f9fafb;padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb; }
.ah-table td { padding:12px 16px;font-size:13.5px;color:#374151;border-bottom:1px solid #f3f4f6;vertical-align:middle; }
.ah-table tr:last-child td { border-bottom:none; }
.ah-table tr:hover td { background:#f9fafb; }
.method-badge { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600; }
.user-chip { display:inline-flex;align-items:center;gap:6px;font-weight:600;color:#111827; }
.user-avatar { width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;background:linear-gradient(135deg,#063A1C,#205A44);flex-shrink:0; }
.ah-btn { padding:9px 16px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:7px;border:0;cursor:pointer; }
.ah-btn-green { background:linear-gradient(135deg,#063A1C,#205A44);color:#fff; }
.ah-btn-light { background:#f3f4f6;color:#374151; }
.ah-modal { position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.55);padding:16px; }
.ah-modal.show { display:flex; }
.ah-modal-card { width:100%;max-width:520px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.24);overflow:hidden; }
.ah-modal-head { background:linear-gradient(135deg,#063A1C,#205A44);color:#fff;padding:18px 20px;display:flex;justify-content:space-between;gap:12px; }
.ah-modal-body { padding:20px; }
.ah-field { display:block;margin-bottom:12px; }
.ah-field span { display:block;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#64748b;margin-bottom:6px; }
.ah-field input { width:100%;padding:10px 12px;border:1px solid #dbe3ef;border-radius:10px;background:#f8fafc;outline:none;font-size:14px; }
.ah-callback-status { display:none;margin-bottom:14px;border-radius:12px;border:1px solid #bbf7d0;background:#ecfdf5;color:#065f46;padding:11px 12px;font-size:13px;font-weight:700;line-height:1.5; }
.ah-callback-status.error { border-color:#fecaca;background:#fef2f2;color:#b91c1c; }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="ah-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,#063A1C,#205A44);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-history" style="color:#fff;font-size:18px;"></i>
        </div>
        <div>
            <div style="font-size:18px;font-weight:700;color:#111827;">{{ $rule->name }}</div>
            <div class="ah-rule-badge" style="margin-top:4px;">
                <i class="fas fa-random" style="font-size:10px;"></i>
                {{ ucwords(str_replace('_',' ', $rule->assignment_method)) }} •
                {{ ucwords(str_replace('_',' ', $rule->source)) }}
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
        @if($facebookCallbackForm)
            <button type="button" class="ah-btn ah-btn-green" onclick="openAutomationCallbackModal()">
                <i class="fas fa-cloud-arrow-down"></i> Import Old Leads
            </button>
        @endif
        <a href="{{ route('admin.automation.index') }}" class="ah-btn ah-btn-light">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

@if($facebookCallbackForm)
<div id="automationCallbackModal" class="ah-modal">
    <div class="ah-modal-card">
        <div class="ah-modal-head">
            <div style="min-width:0;">
                <div style="font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#bbf7d0;">Old Meta Leads</div>
                <div style="margin-top:4px;font-size:18px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $facebookCallbackForm->form_name ?: $facebookCallbackForm->form_id }}</div>
                <div style="margin-top:4px;font-size:13px;color:#d1fae5;">Meta se old leads fetch hoke CRM me import/assign honge. Existing leads skip rahenge.</div>
            </div>
            <button type="button" onclick="closeAutomationCallbackModal()" style="width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#fff;cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="automationCallbackForm" class="ah-modal-body" data-url="{{ route('integrations.facebook-lead-ads.forms.callback', $facebookCallbackForm) }}">
            <div id="automationCallbackStatus" class="ah-callback-status"></div>
            <label class="ah-field">
                <span>From</span>
                <input type="date" name="since">
            </label>
            <label class="ah-field">
                <span>To</span>
                <input type="date" name="until">
            </label>
            <label class="ah-field">
                <span>Limit</span>
                <input type="number" name="limit" min="1" max="50" value="50">
            </label>
            <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:10px 12px;font-size:12px;line-height:1.5;margin-bottom:16px;">
                Form disabled/token missing hua to import stop hoga. Ye webhook URL ya live lead flow change nahi karta.
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="closeAutomationCallbackModal()" class="ah-btn ah-btn-light">Cancel</button>
                <button id="automationCallbackSubmit" type="submit" class="ah-btn ah-btn-green">
                    <i class="fas fa-cloud-arrow-down"></i> Import Leads
                </button>
                <button id="automationCallbackRefresh" type="button" onclick="window.location.reload()" class="ah-btn ah-btn-light" style="display:none;">
                    <i class="fas fa-rotate"></i> Refresh History
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Stats --}}
<div class="ah-stats">
    <div class="ah-stat">
        <div class="ah-stat-val">{{ $totalAssignments }}</div>
        <div class="ah-stat-lbl">Total Assignments</div>
    </div>
    <div class="ah-stat">
        <div class="ah-stat-val" style="color:#065f46;">{{ $todayAssignments }}</div>
        <div class="ah-stat-lbl">Today</div>
    </div>
    <div class="ah-stat">
        <div class="ah-stat-val" style="color:#1d4ed8;">{{ $uniqueUsers }}</div>
        <div class="ah-stat-lbl">Users Assigned To</div>
    </div>
    <div class="ah-stat">
        <div class="ah-stat-val" style="color:#b45309;">{{ $thisWeek }}</div>
        <div class="ah-stat-lbl">This Week</div>
    </div>
</div>

{{-- Table --}}
{{-- Search Bar --}}
<div style="background:#fff;border-radius:14px;border:1px solid #e5e7eb;padding:14px 20px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
    <form method="GET" action="{{ request()->url() }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;position:relative;">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;"></i>
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Lead name, phone, user se search karo..."
                style="width:100%;padding:9px 12px 9px 36px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:13.5px;outline:none;background:#f9fafb;"
                onfocus="this.style.borderColor='#205A44'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div style="min-width:140px;">
            <select name="assigned_to" style="width:100%;padding:9px 14px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:13px;background:#f9fafb;outline:none;">
                <option value="">All Users</option>
                @foreach($ruleUsers as $ru)
                <option value="{{ $ru->id }}" {{ request('assigned_to') == $ru->id ? 'selected' : '' }}>{{ $ru->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="min-width:140px;">
            <select name="date_filter" style="width:100%;padding:9px 14px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:13px;background:#f9fafb;outline:none;">
                <option value="">All Time</option>
                <option value="today" {{ request('date_filter')=='today' ? 'selected' : '' }}>Today</option>
                <option value="week" {{ request('date_filter')=='week' ? 'selected' : '' }}>This Week</option>
                <option value="month" {{ request('date_filter')=='month' ? 'selected' : '' }}>This Month</option>
            </select>
        </div>
        <button type="submit" style="padding:9px 20px;background:linear-gradient(135deg,#063A1C,#205A44);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;">
            <i class="fas fa-filter"></i> Filter
        </button>
        @if(request('search') || request('assigned_to') || request('date_filter'))
        <a href="{{ request()->url() }}" style="padding:9px 16px;background:#f3f4f6;color:#374151;border-radius:9px;font-size:13px;font-weight:500;text-decoration:none;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-times"></i> Clear
        </a>
        @endif
    </form>
</div>

<div class="ah-table-wrap">
    <table class="ah-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Lead</th>
                <th>Phone</th>
                <th>Assigned To</th>
                <th>Assigned By</th>
                <th>Method</th>
                <th>Date & Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $a)
            <tr>
                <td style="color:#9ca3af;font-size:12px;">{{ $a->id }}</td>
                <td>
                    @if($a->lead)
                    <a href="{{ route('leads.show', $a->lead_id) }}"
                       style="color:#1d4ed8;font-weight:600;text-decoration:none;">
                        {{ $a->lead->name }}
                    </a>
                    @else
                    <span style="color:#9ca3af;">Lead #{{ $a->lead_id }}</span>
                    @endif
                </td>
                <td style="color:#6b7280;">{{ $a->lead->phone ?? '—' }}</td>
                <td>
                    @if($a->assignedTo)
                    <div class="user-chip">
                        <div class="user-avatar">{{ strtoupper(substr($a->assignedTo->name,0,1)) }}</div>
                        {{ $a->assignedTo->name }}
                    </div>
                    @else — @endif
                </td>
                <td style="color:#6b7280;font-size:12.5px;">{{ $a->assignedBy->name ?? 'System' }}</td>
                <td>
                    <span class="method-badge" style="background:#eff6ff;color:#1d4ed8;">
                        <i class="fas fa-random" style="font-size:9px;"></i>
                        {{ ucwords(str_replace('_',' ',$a->assignment_method)) }}
                    </span>
                </td>
                <td style="color:#6b7280;font-size:12.5px;">
                    {{ $a->assigned_at ? \Carbon\Carbon::parse($a->assigned_at)->format('d M Y, h:i A') : $a->created_at->format('d M Y, h:i A') }}
                </td>
                <td>
                    @if($a->is_active)
                    <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Active</span>
                    @else
                    <span style="background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Inactive</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;padding:50px;color:#9ca3af;">
                    <i class="fas fa-history" style="font-size:32px;opacity:.3;margin-bottom:12px;display:block;"></i>
                    Koi assignment history nahi mili
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($assignments->hasPages())
    <div style="padding:16px 20px;border-top:1px solid #f3f4f6;">
        {{ $assignments->links() }}
    </div>
    @endif
</div>
@if($facebookCallbackForm)
<script>
function setAutomationCallbackStatus(message, type = 'success') {
    const status = document.getElementById('automationCallbackStatus');
    if (!status) return;
    if (!message) {
        status.style.display = 'none';
        status.textContent = '';
        status.classList.remove('error');
        return;
    }
    status.textContent = message;
    status.style.display = 'block';
    status.classList.toggle('error', type === 'error');
}

function automationImportConfirmation(summary) {
    const imported = Number(summary.imported || 0);
    const alreadyPresent = Number(summary.already_present || 0);
    const failed = Number(summary.failed || 0);
    const fetched = Number(summary.fetched || 0);

    if (imported > 0) {
        return `${imported} lead CRM me import ho gaye. ${alreadyPresent} lead pehle se CRM me the, ${failed} failed. Refresh History click karke assignment history update dekho.`;
    }

    if (alreadyPresent > 0 && failed === 0) {
        return `New import 0 hai kyunki ${alreadyPresent} lead pehle se CRM me maujood hain. Duplicate create nahi hua. Refresh History click karke existing assignment history dekho.`;
    }

    if (fetched === 0) {
        return 'Selected date range me Meta se koi old lead nahi mila. Date range badha kar phir import try karo.';
    }

    return `Import complete. Imported: ${imported}, already in CRM: ${alreadyPresent}, failed: ${failed}.`;
}

function openAutomationCallbackModal() {
    const modal = document.getElementById('automationCallbackModal');
    const form = document.getElementById('automationCallbackForm');
    const today = new Date();
    const since = new Date();
    since.setDate(today.getDate() - 30);
    if (form) {
        form.reset();
        form.elements.since.value = since.toISOString().slice(0, 10);
        form.elements.until.value = today.toISOString().slice(0, 10);
        form.elements.limit.value = 50;
    }
    const refresh = document.getElementById('automationCallbackRefresh');
    if (refresh) {
        refresh.style.display = 'none';
    }
    setAutomationCallbackStatus('');
    modal?.classList.add('show');
}

function closeAutomationCallbackModal() {
    document.getElementById('automationCallbackModal')?.classList.remove('show');
}

document.getElementById('automationCallbackForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const button = document.getElementById('automationCallbackSubmit');
    const oldHtml = button?.innerHTML;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calling back...';
    }

    fetch(this.dataset.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            since: this.elements.since.value,
            until: this.elements.until.value,
            limit: this.elements.limit.value || 50,
        }),
    })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(result => {
            if (!result.ok || !result.data.success) {
                throw new Error(result.data.message || 'Meta callback failed.');
            }
            const s = result.data.summary || {};
            setAutomationCallbackStatus(automationImportConfirmation(s), 'success');
            const refresh = document.getElementById('automationCallbackRefresh');
            if (refresh) {
                refresh.style.display = 'inline-flex';
            }
        })
        .catch(error => setAutomationCallbackStatus(error.message || 'Meta callback failed.', 'error'))
        .finally(() => {
            if (button) {
                button.disabled = false;
                button.innerHTML = oldHtml;
            }
        });
});

document.getElementById('automationCallbackModal')?.addEventListener('click', function (event) {
    if (event.target === this) {
        closeAutomationCallbackModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeAutomationCallbackModal();
    }
});
</script>
@endif
@endsection
