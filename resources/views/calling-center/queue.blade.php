@extends('layouts.app')

@section('title', 'My Calling Queue - ' . brand_name())
@section('page-title', 'My Calling Queue')

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

    <div class="grid gap-4 lg:grid-cols-4">
        @foreach($myStats as $label => $value)
            <div class="rounded-lg border border-[#E5DED4] bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</p>
                <p class="mt-2 text-2xl font-black text-[#063A1C]">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-[#E5DED4] bg-white shadow-sm">
        <div class="border-b border-[#E5DED4] px-5 py-4">
            <h2 class="text-lg font-black text-[#063A1C]">Current Call</h2>
        </div>
        <div class="p-5">
            @if($activeItem)
                <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
                    <div>
                        <p class="text-sm font-bold text-slate-500">{{ $activeItem->campaign?->name }}</p>
                        <h3 class="mt-1 text-2xl font-black text-[#063A1C]">{{ $activeItem->lead?->name ?? 'Lead #' . $activeItem->lead_id }}</h3>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                            <div><b>Phone:</b> {{ $maskCallingPhone($activeItem->phone) }}</div>
                            <div><b>Status:</b> {{ $activeItem->status }}</div>
                            <div><b>Call:</b> {{ $activeItem->call_status ?: 'initiated' }}</div>
                            <div><b>Started:</b> {{ optional($activeItem->call_started_at)->format('d M, h:i A') ?: '--' }}</div>
                            <div><b>MCube:</b> {{ ucfirst($activeItem->mcubeOutboundAttempt?->status ?? 'pending') }}</div>
                            <div class="md:col-span-2"><b>Message:</b> {{ $activeItem->mcubeOutboundAttempt?->error_message ?: data_get($activeItem->mcubeOutboundAttempt?->response_payload, 'message', '--') }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('calling-center.items.outcome', $activeItem) }}" class="rounded-lg border border-[#E5DED4] p-4">
                        @csrf
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Outcome</label>
                        <select name="outcome" required class="mb-3 w-full rounded-lg border border-[#DCD3C6] px-3 py-2" onchange="document.getElementById('followUpAtWrap').style.display=this.value==='follow_up'?'block':'none'">
                            <option value="">Select outcome</option>
                            <option value="interested">Interested</option>
                            <option value="not_interested">Not Interested</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="cnp">CNP</option>
                            <option value="junk">Junk</option>
                            <option value="meeting_request">Meeting Request</option>
                            <option value="site_visit_request">Site Visit Request</option>
                        </select>
                        <div id="followUpAtWrap" style="display:none">
                            <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Follow-up Date/Time</label>
                            <input type="datetime-local" name="next_action_at" class="mb-3 w-full rounded-lg border border-[#DCD3C6] px-3 py-2">
                        </div>
                        <label class="mb-1 block text-xs font-bold uppercase text-slate-500">Remark</label>
                        <textarea name="remark" rows="3" class="mb-3 w-full rounded-lg border border-[#DCD3C6] px-3 py-2" placeholder="Call summary"></textarea>
                        <button class="w-full rounded-lg bg-[#205A44] px-4 py-2.5 text-sm font-black text-white">Submit Outcome</button>
                    </form>
                </div>
            @else
                <div class="rounded-lg bg-slate-50 p-6 text-center">
                    <p class="font-bold text-slate-700">No active call waiting for outcome.</p>
                    <form method="POST" action="{{ route('calling-center.process') }}" class="mt-4">
                        @csrf
                        <button class="rounded-lg bg-[#205A44] px-4 py-2 text-sm font-bold text-white">Check Next Call</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-[#E5DED4] bg-white shadow-sm">
        <div class="border-b border-[#E5DED4] px-5 py-4">
            <h2 class="text-lg font-black text-[#063A1C]">Upcoming Calls</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Campaign</th>
                        <th class="px-4 py-3 text-left">Lead</th>
                        <th class="px-4 py-3 text-left">Phone</th>
                        <th class="px-4 py-3 text-left">Retry</th>
                        <th class="px-4 py-3 text-left">Scheduled</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5DED4]">
                    @forelse($upcomingItems as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->campaign?->name }}</td>
                            <td class="px-4 py-3 font-bold text-[#063A1C]">{{ $item->lead?->name ?? 'Lead #' . $item->lead_id }}</td>
                            <td class="px-4 py-3">{{ $maskCallingPhone($item->phone) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold text-slate-700">{{ $item->attempt_count }} attempt(s)</span>
                                @if(data_get($item->meta, 'retry_scheduled'))
                                    <div class="mt-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Retry</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ optional($item->next_call_at)->format('d M, h:i A') ?: 'Ready' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No upcoming calls.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
