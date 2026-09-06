@extends('layouts.app')

@section('title', 'Lead Duplicate Report')
@section('page-title', 'Lead Duplicate Report')

@section('content')
<div class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ([['Remaining groups', $remainingGroupCount, 'text-amber-700'], ['Merged records', $mergedCount, 'text-emerald-700'], ['Failed groups', $failedCount, 'text-red-700']] as [$label, $value, $color])
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold {{ $color }}">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="flex gap-2">
            <input name="phone" value="{{ request('phone') }}" class="w-full max-w-sm rounded-md border-slate-300" placeholder="Search phone number">
            <button class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">Search</button>
        </form>
    </div>

    @if ($groups->isNotEmpty())
        <div class="overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 font-semibold text-amber-900">Open duplicate groups</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Master</th><th class="px-4 py-3">Duplicates</th><th class="px-4 py-3">Sources</th><th class="px-4 py-3">Statuses</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($groups as $group)
                        <tr><td class="px-4 py-3 font-medium">{{ substr($group['normalized_phone'], -10) }}</td><td class="px-4 py-3">#{{ $group['master_lead_id'] }}</td><td class="px-4 py-3">{{ collect($group['duplicate_lead_ids'])->map(fn ($id) => '#'.$id)->join(', ') }}</td><td class="px-4 py-3">{{ implode(', ', $group['sources']) }}</td><td class="px-4 py-3">{{ implode(', ', $group['statuses']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-900">Merge history</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Old lead</th><th class="px-4 py-3">Master</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Details</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($audits as $audit)
                    <tr><td class="whitespace-nowrap px-4 py-3">{{ optional($audit->merged_at ?? $audit->created_at)->format('d M Y h:i A') }}</td><td class="px-4 py-3">#{{ $audit->duplicate_lead_id }}</td><td class="px-4 py-3">#{{ $audit->master_lead_id }}</td><td class="px-4 py-3 font-semibold {{ $audit->status === 'failed' ? 'text-red-700' : 'text-emerald-700' }}">{{ ucfirst($audit->status) }}</td><td class="max-w-xl px-4 py-3 text-slate-600">{{ $audit->failure_details ?: $audit->remark }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No merge history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $audits->links() }}</div>
    </div>
</div>
@endsection
