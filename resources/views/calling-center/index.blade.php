@extends('layouts.app')

@section('title', 'Calling Center - ' . brand_name())
@section('page-title', 'Calling Center')

@push('styles')
<style>
    .cc-page {
        --cc-green: #063A1C;
        --cc-green-soft: #205A44;
        --cc-border: #DDE7E0;
        --cc-muted: #64748b;
    }
    .cc-top-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }
    .cc-stat-card,
    .cc-panel {
        border: 1px solid var(--cc-border);
        background: #ffffff;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }
    .cc-stat-card {
        min-height: 104px;
        border-radius: 16px;
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        overflow: hidden;
        position: relative;
    }
    .cc-stat-card::after {
        content: "";
        position: absolute;
        inset: auto -28px -34px auto;
        width: 96px;
        height: 96px;
        border-radius: 999px;
        background: rgba(32, 90, 68, 0.08);
    }
    .cc-stat-label,
    .cc-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #526478;
    }
    .cc-stat-value {
        margin-top: 6px;
        font-size: 32px;
        font-weight: 900;
        line-height: 1;
        color: var(--cc-green);
    }
    .cc-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #EEF8F2;
        color: var(--cc-green-soft);
        position: relative;
        z-index: 1;
    }
    .cc-panel {
        border-radius: 18px;
        padding: 20px;
    }
    .cc-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
    }
    .cc-panel-title {
        margin: 0;
        font-size: 20px;
        font-weight: 900;
        color: var(--cc-green);
    }
    .cc-panel-copy {
        margin-top: 4px;
        font-size: 13px;
        color: var(--cc-muted);
    }
    .cc-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px 16px;
    }
    .cc-field {
        min-width: 0;
    }
    .cc-field-wide {
        grid-column: span 2;
    }
    .cc-input {
        width: 100%;
        min-height: 44px;
        border: 1px solid #CBD8D0;
        border-radius: 11px;
        background: #ffffff;
        padding: 10px 12px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .cc-input:focus {
        border-color: var(--cc-green-soft);
        box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.12);
    }
    .cc-btn {
        min-height: 42px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 15px;
        font-size: 13px;
        font-weight: 800;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }
    .cc-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }
    .cc-btn-primary {
        background: var(--cc-green);
        color: #ffffff;
    }
    .cc-btn-primary:hover {
        background: var(--cc-green-soft);
    }
    .cc-btn-outline {
        border: 1px solid var(--cc-green-soft);
        color: var(--cc-green);
        background: #ffffff;
    }
    .cc-btn-soft {
        background: #EEF3F0;
        color: #24384f;
    }
    .cc-preview-panel {
        margin-top: 18px;
        border: 1px solid var(--cc-border);
        border-radius: 16px;
        background: linear-gradient(180deg, #F8FBF9 0%, #ffffff 100%);
        padding: 16px;
    }
    .cc-preview-table {
        margin-top: 14px;
        overflow-x: auto;
        border: 1px solid #E5ECE8;
        border-radius: 12px;
        background: #ffffff;
    }
    .cc-table {
        min-width: 100%;
        font-size: 13px;
    }
    .cc-table thead {
        background: #F3F7F5;
        color: #526478;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .cc-table th,
    .cc-table td {
        padding: 11px 12px;
    }
    .cc-guide-card {
        border: 1px solid var(--cc-border);
        border-radius: 14px;
        padding: 16px;
        background: #ffffff;
    }
    .cc-guide-card h3 {
        color: var(--cc-green);
    }
    @media (max-width: 1180px) {
        .cc-top-grid,
        .cc-form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 720px) {
        .cc-top-grid,
        .cc-form-grid {
            grid-template-columns: 1fr;
        }
        .cc-field-wide {
            grid-column: span 1;
        }
        .cc-panel {
            padding: 15px;
        }
        .cc-panel-head {
            align-items: stretch;
            flex-direction: column;
        }
        .cc-btn {
            width: 100%;
        }
        .cc-preview-panel .flex {
            align-items: stretch;
        }
    }
</style>
@endpush

@section('content')
<div class="cc-page space-y-6">
    @if(session('success')) <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</div> @endif
    @if(session('info')) <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">{{ session('info') }}</div> @endif

    <div class="cc-top-grid">
        @foreach([
            ['label' => 'Campaigns', 'value' => $campaigns->total(), 'icon' => 'fa-bullhorn'],
            ['label' => 'Running', 'value' => $campaigns->getCollection()->where('status', 'running')->count(), 'icon' => 'fa-play'],
            ['label' => 'Paused', 'value' => $campaigns->getCollection()->where('status', 'paused')->count(), 'icon' => 'fa-pause'],
            ['label' => 'My Active Call', 'value' => $activeItem ? 1 : 0, 'icon' => 'fa-phone-volume'],
        ] as $metric)
            <div class="cc-stat-card">
                <div>
                    <p class="cc-stat-label">{{ $metric['label'] }}</p>
                    <p class="cc-stat-value">{{ $metric['value'] }}</p>
                </div>
                <div class="cc-stat-icon">
                    <i class="fas {{ $metric['icon'] }}"></i>
                </div>
            </div>
        @endforeach
    </div>

    @if(auth()->user()->canUseCallingCenter('calling_center.create_campaign'))
    <div class="cc-panel">
        <div class="cc-panel-head">
            <div>
                <h2 class="cc-panel-title">Create Calling Campaign</h2>
                <p class="cc-panel-copy">Pick a Lead Bank folder, preview matching leads, then assign the queue to one caller.</p>
            </div>
            <a href="{{ route('calling-center.queue') }}" class="cc-btn cc-btn-outline">
                <i class="fas fa-list-check"></i> My Queue
            </a>
        </div>
        <form method="POST" action="{{ route('calling-center.store') }}" class="cc-form-grid" onsubmit="ccDisableCreateButton(event)">
            @csrf
            <div id="ccSelectedLeadInputs"></div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Campaign Name</label>
                <input name="name" required class="cc-input" placeholder="July calling batch">
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Agent / Telecaller</label>
                <select name="assigned_to" required class="cc-input">
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} - {{ $agent->phone ?: 'No phone' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Lead Bank Folder</label>
                <select id="ccFolderChoice" class="cc-input" onchange="ccSyncFolderChoice()">
                    <option value="system:all">All Lead Bank</option>
                    <option value="system:unassigned">Unassigned</option>
                    <option value="tag:folder">Tag Folder</option>
                </select>
                <input type="hidden" id="ccFolderType" name="folder_type" value="system">
                <input type="hidden" name="folder_key" value="all">
            </div>
            <div id="ccFolderTag" class="cc-field" style="display:none">
                <label class="cc-label mb-1 block">Tag Folder</label>
                <select name="folder_tag_id" class="cc-input">
                    <option value="">Select folder</option>
                    @foreach($folderTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">City</label>
                <select name="city" class="cc-input">
                    <option value="">Any</option>
                    @foreach($cities as $city)<option value="{{ $city }}">{{ $city }}</option>@endforeach
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Source</label>
                <select name="source" class="cc-input">
                    <option value="">Any</option>
                    @foreach($sources as $source)<option value="{{ $source }}">{{ $source }}</option>@endforeach
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Status</label>
                <select name="status" class="cc-input">
                    <option value="">Any valid</option>
                    @foreach($statuses as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Search</label>
                <input name="search" class="cc-input" placeholder="Name, phone, email">
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Quantity</label>
                <input type="number" name="quantity" min="1" max="1000" value="100" class="cc-input">
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Next Call Delay</label>
                <select name="delay_seconds" class="cc-input">
                    <option value="120">2 min</option>
                    <option value="180">3 min</option>
                    <option value="300">5 min</option>
                    <option value="0">Immediate after outcome</option>
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Retry Policy</label>
                <select name="retry_policy" class="cc-input">
                    <option value="none">No retry</option>
                    <option value="cnp_no_answer">Retry CNP / no answer</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="cc-field">
                <label class="cc-label mb-1 block">Max Retries</label>
                <input type="number" name="max_retries" min="0" max="10" value="0" class="cc-input">
            </div>
            <div class="flex items-end">
                <button id="ccCreateCampaignBtn" class="cc-btn cc-btn-primary w-full">
                    <i class="fas fa-plus"></i> Create Campaign
                </button>
            </div>
        </form>
        <div class="cc-preview-panel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-black text-[#063A1C]">Lead Preview</h3>
                    <p id="ccPreviewSummary" class="mt-1 text-sm text-slate-600">Preview matching Lead Bank leads before creating the campaign.</p>
                    <p id="ccSelectedSummary" class="mt-1 hidden text-sm font-bold text-[#205A44]">Selected leads only: 0</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="ccPreviewLeads()" class="cc-btn cc-btn-outline"><i class="fas fa-eye"></i> Preview Leads</button>
                    <button type="button" onclick="ccSelectPreviewLeads(true)" class="cc-btn cc-btn-soft">Select All Preview</button>
                    <button type="button" onclick="ccSelectPreviewLeads(false)" class="cc-btn cc-btn-soft">Clear</button>
                </div>
            </div>
            <div class="cc-preview-table">
                <table class="cc-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Select</th>
                            <th class="px-3 py-2 text-left">Lead</th>
                            <th class="px-3 py-2 text-left">Phone</th>
                            <th class="px-3 py-2 text-left">City</th>
                            <th class="px-3 py-2 text-left">Source</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="ccPreviewBody">
                        <tr><td colspan="6" class="px-3 py-5 text-center text-slate-500">No preview loaded.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="cc-panel">
        <div class="cc-panel-head">
            <div>
                <h2 class="cc-panel-title">Calling Center Guide</h2>
                <p class="cc-panel-copy">Use this module to call Lead Bank numbers one by one through MCube and capture clean outcomes.</p>
            </div>
            <a href="{{ route('calling-center.queue') }}" class="cc-btn cc-btn-soft"><i class="fas fa-arrow-right"></i> Open My Queue</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="cc-guide-card">
                <h3 class="font-black text-[#063A1C]">1. Create a Campaign</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Select a Lead Bank folder, choose filters, set quantity, assign an agent, preview leads, then create the campaign.</p>
            </div>
            <div class="cc-guide-card">
                <h3 class="font-black text-[#063A1C]">2. Start Calling</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Open the campaign and click Start / Resume. MCube sends one call to the assigned agent, then waits for outcome.</p>
            </div>
            <div class="cc-guide-card">
                <h3 class="font-black text-[#063A1C]">3. Submit Outcome</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">The agent submits Interested, Follow Up, CNP, or Not Interested. The next call starts after the selected delay.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="font-black text-[#063A1C]">Main Features</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li><b>Lead Bank only:</b> Campaigns use Lead Bank imported leads, not normal CRM leads.</li>
                    <li><b>Lead Bank folders:</b> Create campaigns from All Lead Bank, Unassigned, or tag folders.</li>
                    <li><b>Delay control:</b> Use 2 min, 3 min, 5 min, or immediate next-call timing.</li>
                    <li><b>Clean queue:</b> Duplicate phones, dead leads, junk leads, and not-interested leads are skipped.</li>
                    <li><b>MCube logging:</b> Each outbound request is saved with request, response, and refid.</li>
                    <li><b>Reports:</b> Campaign pages show total, pending, completed, failed, skipped, and outcome counts.</li>
                </ul>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="font-black text-[#063A1C]">Role Rules</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li><b>Admin:</b> Can create, start, pause, cancel, view reports, and export.</li>
                    <li><b>Telecaller:</b> Can use My Calling Queue, submit outcomes, pause own queue, and view own calls.</li>
                    <li><b>Future access:</b> CRM or managers can get access by adding Calling Center permissions to their role.</li>
                    <li><b>Restricted data:</b> Telecaller does not get Lead Bank inventory, imports, integrations, user management, or team reports by default.</li>
                </ul>
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
            <b>Important:</b> Bulk campaign calls are MCube-only. Normal browser phone dialer fallback is only for single manual call buttons. If MCube outbound token or API settings are missing, campaign calls will fail and stay logged for review. Run bulk calling only after DND/NDNC clearance is confirmed.
        </div>
    </div>

    <div class="cc-panel p-0">
        <div class="border-b border-[#E5DED4] px-5 py-4">
            <h2 class="cc-panel-title">Campaigns</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="cc-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Campaign</th>
                        <th class="px-4 py-3 text-left">Agent</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Done</th>
                        <th class="px-4 py-3 text-right">Pending</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5DED4]">
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td class="px-4 py-3 font-bold text-[#063A1C]">{{ $campaign->name }}</td>
                            <td class="px-4 py-3">{{ $campaign->assignedTo?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ strtoupper($campaign->status) }}</span></td>
                            <td class="px-4 py-3 text-right">{{ $campaign->items_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $campaign->completed_items_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $campaign->pending_items_count }}</td>
                            <td class="px-4 py-3 text-right"><a class="font-bold text-[#205A44]" href="{{ route('calling-center.show', $campaign) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No campaigns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4">{{ $campaigns->links() }}</div>
    </div>
</div>
<script>
function ccSyncFolderChoice() {
    const value = document.getElementById('ccFolderChoice')?.value || 'system:all';
    const parts = value.split(':');
    const type = parts[0] || 'system';
    const key = parts[1] || 'all';
    document.getElementById('ccFolderType').value = type;
    document.querySelector('input[name="folder_key"]').value = key === 'folder' ? 'all' : key;
    document.getElementById('ccFolderTag').style.display = type === 'tag' ? 'block' : 'none';
}

function ccCampaignForm() {
    return document.querySelector('form[action="{{ route('calling-center.store') }}"]');
}

function ccCurrentPreviewPayload() {
    const form = ccCampaignForm();
    const data = new FormData(form);
    return {
        folder_type: data.get('folder_type') || 'system',
        folder_key: data.get('folder_key') || 'all',
        folder_tag_id: data.get('folder_tag_id') || '',
        city: data.get('city') || '',
        source: data.get('source') || '',
        status: data.get('status') || '',
        search: data.get('search') || '',
        quantity: data.get('quantity') || 100,
    };
}

function ccEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

async function ccPreviewLeads() {
    const summary = document.getElementById('ccPreviewSummary');
    const body = document.getElementById('ccPreviewBody');
    summary.textContent = 'Loading matching leads...';
    body.innerHTML = '<tr><td colspan="6" class="px-3 py-5 text-center text-slate-500">Loading...</td></tr>';

    try {
        const response = await fetch('{{ route('calling-center.preview') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(ccCurrentPreviewPayload()),
        });
        const result = await response.json();
        const leads = result.leads || [];
        summary.textContent = `${result.total || leads.length} lead(s) matched. Select rows to lock exact leads for this campaign.`;

        if (!leads.length) {
            body.innerHTML = '<tr><td colspan="6" class="px-3 py-5 text-center text-slate-500">No matching leads.</td></tr>';
            ccSyncSelectedLeadInputs();
            return;
        }

        body.innerHTML = leads.map((lead) => `
            <tr class="border-t border-[#E5DED4] bg-white">
                <td class="px-3 py-2"><input type="checkbox" class="cc-preview-lead" value="${lead.id}" onchange="ccSyncSelectedLeadInputs()"></td>
                <td class="px-3 py-2 font-bold text-[#063A1C]">${ccEscape(lead.name || 'Lead #' + lead.id)}</td>
                <td class="px-3 py-2">${ccEscape(lead.phone || '--')}</td>
                <td class="px-3 py-2">${ccEscape(lead.city || '--')}</td>
                <td class="px-3 py-2">${ccEscape(lead.source || '--')}</td>
                <td class="px-3 py-2">${ccEscape(lead.status || '--')}</td>
            </tr>
        `).join('');
        ccSyncSelectedLeadInputs();
    } catch (error) {
        summary.textContent = 'Preview failed.';
        body.innerHTML = '<tr><td colspan="6" class="px-3 py-5 text-center text-red-600">Unable to preview leads.</td></tr>';
    }
}

function ccSelectPreviewLeads(selected) {
    document.querySelectorAll('.cc-preview-lead').forEach((input) => {
        input.checked = selected;
    });
    ccSyncSelectedLeadInputs();
}

function ccSyncSelectedLeadInputs() {
    const wrap = document.getElementById('ccSelectedLeadInputs');
    const selected = Array.from(document.querySelectorAll('.cc-preview-lead:checked')).map((input) => input.value);
    const summary = document.getElementById('ccSelectedSummary');
    wrap.innerHTML = selected.map((id) => `<input type="hidden" name="lead_ids[]" value="${id}">`).join('');
    if (summary) {
        summary.classList.toggle('hidden', selected.length === 0);
        summary.textContent = `Selected leads only: ${selected.length}`;
    }
}

function ccDisableCreateButton(event) {
    const button = document.getElementById('ccCreateCampaignBtn');
    if (!button) return true;
    button.disabled = true;
    button.classList.add('opacity-60', 'cursor-not-allowed');
    button.textContent = 'Creating...';
    return true;
}
</script>
@endsection
