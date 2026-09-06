@extends('layouts.app')

@section('title', 'Calling Campaign - ' . brand_name())
@section('page-title', $campaign->name)

@section('content')
@php
    $canViewFullCallingPhone = auth()->user()->isAdmin() || auth()->user()->isCrm();
    $maskCallingPhone = function ($phone) use ($canViewFullCallingPhone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($canViewFullCallingPhone || strlen($digits) < 4) {
            return $phone ?: '--';
        }
        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    };
@endphp
<div class="space-y-6">
    @if(session('success')) <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</div> @endif
    @if(session('info')) <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">{{ session('info') }}</div> @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-500">Assigned to {{ $campaign->assignedTo?->name ?? 'N/A' }}</p>
            <h1 class="text-2xl font-black text-[#063A1C]">{{ $campaign->name }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('calling-center.index') }}" class="rounded-lg border border-[#DCD3C6] px-4 py-2 text-sm font-bold text-slate-700">Back</a>
            @if(auth()->user()->canUseCallingCenter('calling_center.export_reports'))
                <a href="{{ route('calling-center.export', $campaign) }}" class="rounded-lg border border-[#205A44] px-4 py-2 text-sm font-bold text-[#205A44]">Export CSV</a>
            @endif
            @if(auth()->user()->canUseCallingCenter('calling_center.create_campaign'))
                <form method="POST" action="{{ route('calling-center.start', $campaign) }}">@csrf<button class="rounded-lg bg-[#205A44] px-4 py-2 text-sm font-bold text-white">Start / Resume</button></form>
                <form method="POST" action="{{ route('calling-center.pause', $campaign) }}">@csrf<button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-white">Pause</button></form>
                <form method="POST" action="{{ route('calling-center.cancel', $campaign) }}">@csrf<button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white">Cancel</button></form>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-6">
        @foreach($stats as $label => $value)
            <div class="rounded-lg border border-[#E5DED4] bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</p>
                <p class="mt-2 text-2xl font-black text-[#063A1C]">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-[#E5DED4] bg-white shadow-sm">
        <div class="border-b border-[#E5DED4] px-5 py-4">
            <h2 class="text-lg font-black text-[#063A1C]">Call Items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Lead</th>
                        <th class="px-4 py-3 text-left">Phone</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Call</th>
                        <th class="px-4 py-3 text-left">Outcome</th>
                        <th class="px-4 py-3 text-left">Retry</th>
                        <th class="px-4 py-3 text-left">MCube</th>
                        <th class="px-4 py-3 text-left">Next</th>
                        <th class="px-4 py-3 text-left">Recording</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5DED4]">
                    @foreach($campaign->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-bold text-[#063A1C]">{{ $item->lead?->name ?? 'Lead #' . $item->lead_id }}</div>
                                <div class="text-xs text-slate-500">#{{ $item->lead_id }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $maskCallingPhone($item->phone) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ $item->status }}</span></td>
                            <td class="px-4 py-3">{{ $item->call_status ?: '--' }}</td>
                            <td class="px-4 py-3">{{ $item->outcome ?: '--' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-bold text-slate-700">{{ $item->attempt_count }} attempt(s)</div>
                                @if(data_get($item->meta, 'retry_scheduled'))
                                    <div class="mt-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Retry: {{ data_get($item->meta, 'last_retry_reason') }}</div>
                                @else
                                    <div class="mt-1 text-xs text-slate-400">No retry</div>
                                @endif
                                @if(!empty(data_get($item->meta, 'outcome_history')))
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-bold text-[#205A44]">History</summary>
                                        <div class="mt-2 space-y-1 text-xs text-slate-600">
                                            @foreach(data_get($item->meta, 'outcome_history', []) as $history)
                                                <div class="rounded bg-slate-50 px-2 py-1">
                                                    <b>{{ $history['outcome'] ?? '--' }}</b>
                                                    <span>Attempt {{ $history['attempt_count'] ?? '--' }}</span>
                                                    <span>{{ $history['submitted_at'] ?? '' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($item->mcubeOutboundAttempt)
                                    <div class="text-xs font-bold {{ $item->mcubeOutboundAttempt->status === 'success' ? 'text-emerald-700' : 'text-red-700' }}">{{ ucfirst($item->mcubeOutboundAttempt->status) }}</div>
                                    <div class="mt-1 max-w-[220px] truncate text-xs text-slate-500" title="{{ $item->mcubeOutboundAttempt->error_message ?: data_get($item->mcubeOutboundAttempt->response_payload, 'message', '') }}">
                                        {{ $item->mcubeOutboundAttempt->error_message ?: data_get($item->mcubeOutboundAttempt->response_payload, 'message', '--') }}
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">--</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ optional($item->next_call_at)->format('d M, h:i A') ?: '--' }}</td>
                            <td class="px-4 py-3">
                                @if($item->callLog?->recording_url)
                                    <a href="{{ $item->callLog->recording_url }}" target="_blank" class="font-bold text-[#205A44]">Recording</a>
                                @else
                                    --
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
