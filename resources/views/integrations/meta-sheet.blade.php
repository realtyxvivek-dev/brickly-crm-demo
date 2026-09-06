@extends('layouts.app')

@section('title', 'Meta Sheet Configuration - ' . brand_name())
@section('page-title', 'Meta Sheet Configuration')

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('integrations.google-sheet-import-monitor') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
            <i class="fas fa-heartbeat text-xs"></i>
            <span>Import Monitor</span>
        </a>
    </div>
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-[#0b3b1d] via-[#205A44] to-[#2e6f56] px-6 py-7 text-white sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/12 shadow-inner">
                        <i class="fab fa-facebook text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight sm:text-3xl">Meta Sheet Integrations</h2>
                    <p class="mt-2 text-sm text-emerald-50/90 sm:text-base">Manage Meta/Facebook sheet imports.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('integrations.meta-sheet.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#0b3b1d] shadow-sm transition hover:bg-emerald-50">
                            <i class="fas fa-plus text-xs"></i>
                            <span>Add New Meta Sheet</span>
                        </a>
                    </div>
                </div>
                <div class="grid w-full gap-3 sm:grid-cols-3 lg:max-w-xl">
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                        <div class="text-xs uppercase tracking-[0.18em] text-emerald-50/70">Total Sheets</div>
                        <div class="mt-2 text-3xl font-black">{{ $configs->count() }}</div>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                        <div class="text-xs uppercase tracking-[0.18em] text-emerald-50/70">Active</div>
                        <div class="mt-2 text-3xl font-black">{{ $configs->where('is_active', true)->where('is_draft', false)->count() }}</div>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                        <div class="text-xs uppercase tracking-[0.18em] text-emerald-50/70">Draft</div>
                        <div class="mt-2 text-3xl font-black">{{ $configs->where('is_draft', true)->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($configs->isEmpty())
        <a href="{{ route('integrations.meta-sheet.create') }}" class="flex w-full flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm transition hover:border-[#205A44] hover:shadow-md">
            <div class="flex h-16 w-16 items-center justify-center rounded-[22px] bg-emerald-50 text-[#205A44]">
                <i class="fas fa-plus text-2xl"></i>
            </div>
            <div class="mt-5 text-2xl font-black text-slate-900">Add Your First Meta Sheet</div>
            <div class="mt-2 max-w-xl text-sm text-slate-500">Create a new Meta/Facebook sheet integration.</div>
        </a>
    @else
        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($configs as $config)
                <article class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50/80 px-5 py-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                                    <i class="fab fa-facebook-f text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">{{ $config->sheet_name }}</h3>
                                    <div class="mt-1 text-xs text-slate-500">Google Sheet based sync</div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($config->is_draft)
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Draft</span>
                                @elseif($config->is_active)
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-5">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Mappings</div>
                                <div class="mt-2 text-2xl font-black text-slate-900">{{ $config->columnMappings && $config->columnMappings->count() > 0 ? $config->columnMappings->count() : 0 }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Last Sync</div>
                                <div class="mt-2 text-sm font-bold text-slate-900">{{ $config->last_sync_at ? $config->last_sync_at->diffForHumans() : 'Never' }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Mode</div>
                                <div class="mt-2 text-sm font-bold text-slate-900">{{ $config->is_draft ? 'Pending' : 'Ready' }}</div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            @if($config->is_draft)
                                <a href="{{ route('integrations.meta-sheet.step' . $config->resume_step, $config->id) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                                    <i class="fas fa-play text-xs"></i>
                                    <span>Resume Setup</span>
                                </a>
                            @else
                                <a href="{{ route('integrations.meta-sheet.step2', $config->id) }}" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                                    <i class="fas fa-pen text-xs"></i>
                                    <span>Edit</span>
                                </a>
                                <button type="button" class="js-meta-test inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" data-config-id="{{ $config->id }}">
                                    <i class="fas fa-vial text-xs"></i>
                                    <span>Test</span>
                                </button>
                                <button type="button" class="js-meta-sync inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" data-config-id="{{ $config->id }}">
                                    <i class="fas fa-sync-alt text-xs"></i>
                                    <span>Sync</span>
                                </button>
                                <button type="button" class="js-meta-toggle inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" data-config-id="{{ $config->id }}">
                                    <i class="fas fa-toggle-{{ $config->is_active ? 'on' : 'off' }} text-xs"></i>
                                    <span>{{ $config->is_active ? 'Disable' : 'Enable' }}</span>
                                </button>
                            @endif
                            <button type="button" class="js-meta-delete inline-flex items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50" data-config-id="{{ $config->id }}">
                                <i class="fas fa-trash text-xs"></i>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach

            <a href="{{ route('integrations.meta-sheet.create') }}" class="flex min-h-[320px] flex-col items-center justify-center rounded-[26px] border border-dashed border-slate-300 bg-white px-6 py-8 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#205A44] hover:shadow-md">
                <div class="flex h-16 w-16 items-center justify-center rounded-[22px] bg-emerald-50 text-[#205A44]">
                    <i class="fas fa-plus text-2xl"></i>
                </div>
                <div class="mt-5 text-lg font-bold text-slate-900">Add New Meta Sheet</div>
                <div class="mt-2 max-w-sm text-sm text-slate-500">Create another Meta sheet integration.</div>
            </a>
        </div>
    @endif
</div>

@push('scripts')
<script>
const META_SHEET_CSRF_TOKEN = '{{ csrf_token() }}';

function testIntegration(id) {
    if (!confirm('This will send a test lead to CRM. Continue?')) return;

    fetch(`/integrations/meta-sheet/test/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': META_SHEET_CSRF_TOKEN,
            'Content-Type': 'application/json',
        },
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test successful! Lead ID: ' + (data.lead_id || 'N/A'));
                return;
            }
            alert('Test failed: ' + (data.message || 'Unknown error'));
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Test failed: ' + error.message);
        });
}

function toggleIntegration(id) {
    fetch(`/integrations/meta-sheet/toggle/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': META_SHEET_CSRF_TOKEN,
            'Content-Type': 'application/json',
        },
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
                return;
            }
            alert('Failed to toggle integration');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to toggle integration');
        });
}

function syncLeads(id, btnEl) {
    if (!confirm('Sync leads from this sheet now?')) return;

    const originalHtml = btnEl.innerHTML;
    btnEl.disabled = true;
    btnEl.classList.add('opacity-50');
    btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing';

    fetch(`/integrations/meta-sheet/sync/${id}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': META_SHEET_CSRF_TOKEN,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Sync failed');
            }

            alert(
                'Sync complete!\n\n' +
                `New leads synced: ${data.imported}\n` +
                `Already existed: ${data.already_exists}\n` +
                `Already synced rows: ${data.already_synced}\n` +
                `Missing name/phone: ${data.missing_required}\n` +
                `Errors: ${data.errors}`
            );

            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Sync failed: ' + error.message);
        })
        .finally(() => {
            btnEl.disabled = false;
            btnEl.classList.remove('opacity-50');
            btnEl.innerHTML = originalHtml;
        });
}

function deleteConfig(id, btnEl) {
    if (!confirm('Delete this sheet integration? This cannot be undone.')) return;

    btnEl.disabled = true;

    fetch(`/integrations/meta-sheet/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': META_SHEET_CSRF_TOKEN,
            'Accept': 'application/json',
        },
    })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Delete failed');
            }
            location.reload();
        })
        .catch(error => {
            console.error('Delete error:', error);
            alert(error.message || 'Delete failed');
            btnEl.disabled = false;
        });
}

document.querySelectorAll('.js-meta-test').forEach(btn => {
    btn.addEventListener('click', function () {
        testIntegration(this.dataset.configId);
    });
});

document.querySelectorAll('.js-meta-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
        toggleIntegration(this.dataset.configId);
    });
});

document.querySelectorAll('.js-meta-sync').forEach(btn => {
    btn.addEventListener('click', function () {
        syncLeads(this.dataset.configId, this);
    });
});

document.querySelectorAll('.js-meta-delete').forEach(btn => {
    btn.addEventListener('click', function () {
        deleteConfig(this.dataset.configId, this);
    });
});
</script>
@endpush
@endsection
