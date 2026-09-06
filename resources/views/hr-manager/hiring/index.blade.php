@extends('layouts.app')

@section('title', 'HR Hiring Leads')
@section('page-title', 'HR Hiring Leads')
@section('page-subtitle', 'Manage Facebook hiring candidates assigned to you.')
@section('hide-app-header', '1')

@php
    $routePrefix = $routePrefix ?? 'hr-manager.hiring';
    $perPage = $perPage ?? 15;
    $perPageOptions = $perPageOptions ?? ['15' => '15', '50' => '50', '100' => '100', '200' => '200', 'all' => 'All'];
    $statusPalette = [
        'new' => 'bg-slate-100 text-slate-700 border-slate-200',
        'contacted' => 'bg-blue-50 text-blue-700 border-blue-200',
        'interview_scheduled' => 'bg-amber-50 text-amber-700 border-amber-200',
        'interview_done' => 'bg-violet-50 text-violet-700 border-violet-200',
        'shortlisted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'offer_sent' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
        'hired' => 'bg-green-50 text-green-700 border-green-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        'not_interested' => 'bg-orange-50 text-orange-700 border-orange-200',
        'not_reachable' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'wrong_number' => 'bg-red-50 text-red-700 border-red-200',
        'duplicate' => 'bg-slate-100 text-slate-700 border-slate-200',
        'on_hold' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    ];
    $compactStatusCards = [
        'new' => ['label' => 'New', 'hint' => 'Fresh candidates'],
        'contacted' => ['label' => 'Contacted', 'hint' => 'Call done'],
        'interview_scheduled' => ['label' => 'Interview Scheduled', 'hint' => 'Upcoming interviews'],
        'shortlisted' => ['label' => 'Shortlisted', 'hint' => 'Next decision'],
        'hired' => ['label' => 'Hired', 'hint' => 'Selected'],
        'rejected' => ['label' => 'Dropped', 'hint' => 'Rejected / not interested'],
    ];
    $dropStatuses = ['rejected', 'not_interested', 'not_reachable', 'wrong_number', 'duplicate'];
@endphp

@section('content')
<div class="space-y-6 overflow-x-hidden">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Update save nahi hui.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-4 gap-2 sm:grid-cols-2 xl:grid-cols-6">
        @foreach($compactStatusCards as $key => $card)
            <a href="{{ route($routePrefix . '.index', array_filter(['status' => $key, 'search' => $search, 'per_page' => $perPage])) }}"
               class="min-w-0 rounded-2xl border bg-white p-2.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-4 {{ $selectedStatus === $key ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200' }}">
                <div class="truncate text-[8px] font-semibold uppercase tracking-[0.08em] text-slate-500 sm:text-[11px]">{{ $card['label'] }}</div>
                <div class="mt-1 text-lg font-bold text-slate-900 sm:mt-2 sm:text-2xl">{{ $counts[$key] ?? 0 }}</div>
                <div class="mt-1 hidden text-[11px] leading-4 text-slate-400 sm:block sm:text-xs">{{ $card['hint'] }}</div>
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:hidden">
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                    <input form="mobileHiringFilterForm" name="search" type="text" value="{{ $search }}" placeholder="Search candidate"
                           class="h-11 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                           onclick="event.stopPropagation()">
                </div>
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm">
                    <i class="fas fa-filter text-sm"></i>
                </span>
            </summary>

            <form id="mobileHiringFilterForm" method="GET" class="mt-3 grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-2">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <select name="status"
                        class="h-11 min-w-0 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($selectedStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm">
                    Apply
                </button>
                <a href="{{ route($routePrefix . '.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">
                    Clear
                </a>
            </form>
        </details>
    </div>

    <div class="hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:block">
        <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div>
                <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">Search candidate</label>
                <input id="search" name="search" type="text" value="{{ $search }}" placeholder="Name or phone"
                       class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
            </div>
            <div>
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($selectedStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-end">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 sm:w-auto">
                    Filter
                </button>
                <a href="{{ route($routePrefix . '.index') }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Assigned Candidates</h2>
            <p class="mt-1 text-sm text-slate-500">Only hiring candidates assigned to your HR account are shown here.</p>
        </div>

        @if($candidates->count())
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Candidate</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Phone</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Source</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Status</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Next Action</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Latest Remark</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Updated</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($candidates as $candidate)
                            @php
                                $statusClass = $statusPalette[$candidate->hiring_status ?? 'new'] ?? $statusPalette['new'];
                                $sourceType = $candidate->source_label ?? \App\Models\Lead::displaySourceLabel($candidate->source);
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">{{ $candidate->name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Created {{ optional($candidate->created_at)->format('d M Y, h:i A') }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm font-medium text-slate-800">{{ $candidate->phone }}</td>
                                <td class="px-5 py-4 text-sm text-slate-700">
                                    <div>{{ $sourceType }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <select form="hiring-row-{{ $candidate->id }}" name="hiring_status" class="w-full min-w-[160px] rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                                        @foreach($statusOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(($candidate->hiring_status ?? 'new') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                        {{ $candidate->hiring_status_label ?? 'New' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">
                                    <input form="hiring-row-{{ $candidate->id }}" type="datetime-local" name="next_followup_at" value="{{ optional($candidate->next_followup_at)->format('Y-m-d\\TH:i') }}" class="w-full min-w-[180px] rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700 max-w-xs">
                                    <input form="hiring-row-{{ $candidate->id }}" type="text" name="hr_remark" value="{{ $candidate->hr_remark }}" placeholder="Remark / reason" class="w-full min-w-[190px] rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-500">{{ optional($candidate->updated_at)->format('d M Y, h:i A') }}</td>
                                <td class="px-5 py-4 text-right">
                                    <form id="hiring-row-{{ $candidate->id }}" method="POST" action="{{ route($routePrefix . '.update', $candidate) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="return_to_index" value="1">
                                        <input type="hidden" name="current_status" value="{{ $selectedStatus }}">
                                        <input type="hidden" name="current_search" value="{{ $search }}">
                                        <input type="hidden" name="current_per_page" value="{{ $perPage }}">
                                        <div class="flex justify-end gap-2">
                                            <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                                                Save
                                            </button>
                                            <a href="{{ route($routePrefix . '.show', $candidate) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                                Open
                                            </a>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-4 p-4 lg:hidden">
                @foreach($candidates as $candidate)
                    @php
                        $statusClass = $statusPalette[$candidate->hiring_status ?? 'new'] ?? $statusPalette['new'];
                        $sourceType = $candidate->source_label ?? \App\Models\Lead::displaySourceLabel($candidate->source);
                        $candidatePhone = preg_replace('/\D+/', '', (string) $candidate->phone);
                        $callPhone = $candidatePhone;
                        if ($callPhone && strlen($callPhone) === 10) {
                            $callPhone = '+91' . $callPhone;
                        } elseif ($callPhone && strlen($callPhone) === 12 && str_starts_with($callPhone, '91')) {
                            $callPhone = '+' . $callPhone;
                        } else {
                            $callPhone = $callPhone ?: $candidate->phone;
                        }
                        $whatsappPhone = $candidatePhone;
                        if ($whatsappPhone && strlen($whatsappPhone) === 10) {
                            $whatsappPhone = '91' . $whatsappPhone;
                        }
                    @endphp
                    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="break-words text-[15px] font-bold leading-tight text-slate-900">{{ $candidate->name }}</h3>
                                <p class="mt-1 break-all text-xs font-semibold text-slate-500">{{ $candidate->phone }}</p>
                            </div>
                            <span class="inline-flex shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">
                                {{ $candidate->hiring_status_label ?? 'New' }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Source</span>
                                <span class="font-semibold text-slate-700">{{ $sourceType }}</span>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Next</span>
                                <span class="font-semibold text-slate-700">{{ optional($candidate->next_followup_at)->format('d M, h:i A') ?: 'Not set' }}</span>
                            </div>
                            <div class="col-span-2 rounded-xl bg-slate-50 px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Remark</span>
                                <span class="line-clamp-2 font-semibold text-slate-700">{{ $candidate->hr_remark ?: 'No remark yet' }}</span>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @if($callPhone)
                                <a href="tel:{{ $callPhone }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-400">
                                    <i class="fas fa-phone"></i> Call
                                </span>
                            @endif
                            @if($whatsappPhone)
                                <a href="https://wa.me/{{ $whatsappPhone }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-xs font-bold text-green-700">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-400">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route($routePrefix . '.update', $candidate) }}" class="mt-3 grid gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to_index" value="1">
                            <input type="hidden" name="current_status" value="{{ $selectedStatus }}">
                            <input type="hidden" name="current_search" value="{{ $search }}">
                            <input type="hidden" name="current_per_page" value="{{ $perPage }}">
                            <div class="grid grid-cols-2 gap-2">
                                <select name="hiring_status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800">
                                    @foreach($statusOptions as $key => $label)
                                        <option value="{{ $key }}" @selected(($candidate->hiring_status ?? 'new') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="datetime-local" name="next_followup_at" value="{{ optional($candidate->next_followup_at)->format('Y-m-d\\TH:i') }}" class="w-full rounded-xl border border-slate-300 px-2 py-2 text-xs text-slate-800">
                            </div>
                            <input type="text" name="hr_remark" value="{{ $candidate->hr_remark }}" placeholder="Remark / reason" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-800">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">Save</button>
                                <a href="{{ route($routePrefix . '.show', $candidate) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Open</a>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <form method="GET" class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="status" value="{{ $selectedStatus }}">
                        <label for="hiringPerPage" class="font-semibold text-slate-700">Rows per page</label>
                        <select id="hiringPerPage" name="per_page" onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            @foreach($perPageOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $perPage === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="min-w-0">
                        {{ $candidates->links() }}
                    </div>
                </div>
            </div>
        @else
            <div class="px-5 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <i class="fas fa-user-clock text-xl"></i>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">No hiring candidates found</h3>
                <p class="mt-2 text-sm text-slate-500">Jab hiring Facebook form se lead assign hogi, wo yahan dikh jayegi.</p>
            </div>
        @endif
    </div>
</div>
@endsection
