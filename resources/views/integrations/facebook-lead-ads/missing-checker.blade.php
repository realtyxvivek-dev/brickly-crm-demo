@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $sourceAutomationRoutePrefix = request()->routeIs('ad-manager.*') ? 'ad-manager.automation.' : 'admin.automation.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Meta Missing Lead Checker - ' . brand_name())
@section('page-title', 'Meta Missing Lead Checker')

@section('header-actions')
    <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
        <i class="fas fa-arrow-left text-xs"></i>
        <span>Back to Meta Integration</span>
    </a>
@endsection

@section('content')
@php
    $stats = $report['stats'] ?? null;
    $rows = collect($report['rows'] ?? []);
    $selectedFallback = old('fallback_form_id', $report['fallback_form_id'] ?? '');
    $dateFilter = $report['date_filter'] ?? ['from' => null, 'to' => null];
    $assignableUsers = collect($assignableUsers ?? []);
    $roleFilters = $assignableUsers
        ->map(fn ($user) => ['slug' => $user->role?->slug, 'name' => $user->role?->name])
        ->filter(fn ($role) => $role['slug'] && $role['name'])
        ->unique('slug')
        ->sortBy('name')
        ->values();
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    @foreach(['warning' => 'amber', 'error' => 'red', 'success' => 'green'] as $type => $color)
        @if(session($type))
            <div class="rounded-2xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-5 py-4 text-sm text-{{ $color }}-800 shadow-sm">
                {{ session($type) }}
            </div>
        @endif
    @endforeach

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-[#0b3b1d] via-[#205A44] to-[#2e6f56] px-6 py-7 text-white sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/12 shadow-inner">
                        <i class="fas fa-file-csv text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight sm:text-3xl">Missing Meta Lead Checker</h2>
                    <p class="mt-2 max-w-2xl text-sm text-emerald-50/90 sm:text-base">
                        Meta Leads Center CSV upload karo, CRM webhook/import status turant compare hoga.
                    </p>
                </div>
                @if($stats)
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 text-sm backdrop-blur-sm">
                        <div class="text-xs uppercase tracking-[0.18em] text-emerald-50/70">Last Checked</div>
                        <div class="mt-2 font-bold">{{ $report['checked_at'] ?? '-' }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="px-6 py-6 sm:px-8">
            <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'missing-checker.preview') }}" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px_180px_180px_160px] lg:items-end">
                @csrf
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Meta CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition file:mr-4 file:rounded-xl file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Fallback Form</label>
                    <select name="fallback_form_id" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                        <option value="">Auto from CSV form ID</option>
                        @foreach($forms as $form)
                            <option value="{{ $form->id }}" @selected((string) $selectedFallback === (string) $form->id)>
                                {{ $form->form_name }} ({{ $form->form_id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">From Date</label>
                    <input type="date" name="date_from" value="{{ old('date_from', $dateFilter['from'] ?? '') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">To Date</label>
                    <input type="date" name="date_to" value="{{ old('date_to', $dateFilter['to'] ?? '') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                    <i class="fas fa-magnifying-glass text-xs"></i>
                    <span>Check CSV</span>
                </button>
            </form>

            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/70 px-5 py-4 text-sm text-amber-900">
                Date filter CSV ke submitted/created time par apply hota hai. Empty rakho to full CSV check hogi.
            </div>
        </div>
    </section>

    @if($stats)
        @if(!empty($report['csv_total_rows']) && $report['csv_total_rows'] !== $stats['total'])
            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
                Date filter applied: {{ $stats['total'] }} rows checked out of {{ $report['csv_total_rows'] }} CSV rows.
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Total</div>
                <div class="mt-2 text-3xl font-black text-slate-900">{{ $stats['total'] }}</div>
            </div>
            <div class="rounded-[22px] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">In CRM</div>
                <div class="mt-2 text-3xl font-black text-emerald-900">{{ $stats['in_crm'] }}</div>
            </div>
            <div class="rounded-[22px] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Received</div>
                <div class="mt-2 text-3xl font-black text-amber-900">{{ $stats['webhook_received'] }}</div>
            </div>
            <div class="rounded-[22px] border border-red-200 bg-red-50 p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-red-700">Failed</div>
                <div class="mt-2 text-3xl font-black text-red-900">{{ $stats['webhook_failed'] }}</div>
            </div>
            <div class="rounded-[22px] border border-orange-200 bg-orange-50 p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-orange-700">Missing</div>
                <div class="mt-2 text-3xl font-black text-orange-900">{{ $stats['webhook_missing'] }}</div>
            </div>
            <div class="rounded-[22px] border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">Importable</div>
                <div class="mt-2 text-3xl font-black text-blue-900">{{ $stats['importable'] }}</div>
            </div>
        </section>

        @if(!empty($importResults))
            <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-lg font-bold text-slate-900">Import Results</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($importResults as $result)
                        <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-sm font-bold text-slate-900">{{ $result['name'] ?: $result['leadgen_id'] }}</div>
                                <div class="text-xs text-slate-500">Leadgen ID: {{ $result['leadgen_id'] }}</div>
                            </div>
                            <div class="text-sm {{ $result['status'] === 'imported' ? 'text-emerald-700' : 'text-red-700' }}">
                                <span class="font-bold">{{ ucfirst($result['status']) }}:</span> {{ $result['message'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">CSV Compare Report</h3>
                    <p class="mt-1 text-sm text-slate-500">Rows marked importable can be recovered using existing Meta mapping.</p>
                </div>
                @if(($stats['importable'] ?? 0) > 0)
                    <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'missing-checker.import') }}" class="w-full space-y-4 lg:max-w-5xl" data-recovery-form>
                        @csrf
                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_150px_220px_220px]">
                            <select name="fallback_form_id" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                                <option value="">Use row form ID</option>
                                @foreach($forms as $form)
                                    <option value="{{ $form->id }}" @selected((string) $selectedFallback === (string) $form->id)>
                                    {{ $form->form_name }} ({{ $form->form_id }})
                                </option>
                            @endforeach
                        </select>
                            <select name="batch_limit" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100" title="Safe batch size">
                                <option value="10">10 leads</option>
                                <option value="20" selected>20 leads</option>
                                <option value="30">30 leads</option>
                                <option value="50">50 leads</option>
                            </select>
                            <button type="submit" name="import_action" value="import" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50" onclick="return confirm('Import missing leads only? Assignment will not run from this action.')">
                                <i class="fas fa-cloud-arrow-down text-xs"></i>
                                <span>Import Only</span>
                            </button>
                            <button type="submit" name="import_action" value="import_assign" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" onclick="return confirm('Import and assign missing leads with selected assignment method?')">
                                <i class="fas fa-user-check text-xs"></i>
                                <span>Import + Assign</span>
                            </button>
                        </div>
                        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs font-semibold text-blue-900">
                            Timeout avoid karne ke liye recovery batch me chalegi. Default 20 leads per click safe hai; remaining ke liye CSV dobara check/run karo.
                        </div>

                        <div class="rounded-[24px] border border-emerald-100 bg-gradient-to-br from-white via-slate-50 to-emerald-50/40 p-4 shadow-sm">
                            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Assignment Setup</div>
                                    <p class="mt-1 text-xs text-slate-500">HR, Finance aur Admin users hidden hain. Sirf sales/marketing pool assign hoga.</p>
                                </div>
                                <div class="text-xs font-bold text-slate-500">{{ $assignableUsers->count() }} eligible users</div>
                            </div>

                            <div class="grid gap-2 md:grid-cols-3 xl:grid-cols-5">
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200">
                                    <input type="radio" name="assignment_method" value="existing_rule" checked class="text-[#063A1C]">
                                    Existing Rule
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200">
                                    <input type="radio" name="assignment_method" value="single_user" class="text-[#063A1C]">
                                    Single User
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200">
                                    <input type="radio" name="assignment_method" value="round_robin" class="text-[#063A1C]">
                                    Round Robin
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200">
                                    <input type="radio" name="assignment_method" value="first_available" class="text-[#063A1C]">
                                    First Available
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200">
                                    <input type="radio" name="assignment_method" value="percentage" class="text-[#063A1C]">
                                    Percentage
                                </label>
                            </div>

                            <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                <div>
                                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Single User</label>
                                    <select name="assignee_user_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                                        <option value="">Select user</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->role?->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="create_calling_task" value="1" checked class="rounded border-slate-300 text-[#063A1C]">
                                        Create calling task
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="reassign_existing" value="1" class="rounded border-slate-300 text-[#063A1C]">
                                        Reassign existing CRM rows
                                    </label>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <button type="button" data-role-filter="all" class="rounded-full bg-[#063A1C] px-3 py-1.5 text-xs font-bold text-white">All Sales</button>
                                    @foreach($roleFilters as $role)
                                        <button type="button" data-role-filter="{{ $role['slug'] }}" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:border-emerald-200 hover:text-emerald-700">{{ $role['name'] }}</button>
                                    @endforeach
                                </div>
                                <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-100">
                                    <div class="grid grid-cols-[44px_minmax(0,1fr)_92px] border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                                    <span>Use</span>
                                    <span>User</span>
                                    <span>%</span>
                                    </div>
                                    @foreach($assignableUsers as $user)
                                        <label data-user-role="{{ $user->role?->slug }}" class="grid grid-cols-[44px_minmax(0,1fr)_92px] items-center gap-2 border-b border-slate-50 px-4 py-2 text-sm last:border-b-0">
                                            <input type="checkbox" name="assignment_user_ids[]" value="{{ $user->id }}" class="rounded border-slate-300 text-[#063A1C]">
                                            <span class="font-semibold text-slate-800">{{ $user->name }} <span class="font-normal text-slate-500">({{ $user->role?->name }})</span></span>
                                            <input type="number" name="user_percentages[{{ $user->id }}]" min="0" max="100" step="1" placeholder="0" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm outline-none focus:border-[#205A44]">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">Existing Rule safest hai. Manual methods ke liye role filter se pool shortlist karo, users tick karo, aur Percentage method me % fill karo.</p>
                        </div>
                    </form>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Lead</th>
                            <th class="px-5 py-3 text-left">Leadgen ID</th>
                            <th class="px-5 py-3 text-left">Form</th>
                            <th class="px-5 py-3 text-left">Submitted</th>
                            <th class="px-5 py-3 text-left">CRM Status</th>
                            <th class="px-5 py-3 text-left">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($rows as $row)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-900">{{ $row['name'] ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $row['phone'] ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $row['email'] ?: '-' }}</div>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-700">{{ $row['leadgen_id'] ?: '-' }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-800">{{ $row['resolved_form_name'] ?: ($row['form_name'] ?: '-') }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $row['form_id'] ?: '-' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $row['created_time'] ?: '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $row['status_class'] }}">
                                        {{ $row['status_label'] }}
                                    </span>
                                    @if(!empty($row['crm_lead_id']))
                                        <div class="mt-1 text-xs text-slate-500">CRM: #{{ $row['crm_lead_id'] }}</div>
                                    @endif
                                </td>
                                <td class="max-w-md px-5 py-4 text-xs text-slate-600">{{ $row['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-role-filter]');
    const rows = document.querySelectorAll('[data-user-role]');
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const role = button.dataset.roleFilter;
            buttons.forEach((btn) => {
                btn.classList.toggle('bg-[#063A1C]', btn === button);
                btn.classList.toggle('text-white', btn === button);
                btn.classList.toggle('bg-white', btn !== button);
                btn.classList.toggle('text-slate-600', btn !== button);
                btn.classList.toggle('border', btn !== button);
                btn.classList.toggle('border-slate-200', btn !== button);
            });
            rows.forEach((row) => {
                row.classList.toggle('hidden', role !== 'all' && row.dataset.userRole !== role);
            });
        });
    });
});
</script>
@endsection
