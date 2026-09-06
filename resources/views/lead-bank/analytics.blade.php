@extends('layouts.app')

@section('title', 'Lead Bank Analytics - ' . brand_name())
@section('page-title', 'Lead Bank Analytics')
@section('page-subtitle', 'Inventory health, request fulfillment, allocation lifecycle, and tag performance')

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('lead-bank.requests.index') }}" class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors duration-200 text-sm font-medium">
            Lead Requests
        </a>
        <a href="{{ route('lead-bank.index') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
            Inventory
        </a>
    </div>
@endsection

@section('content')
    @php
        $inventory = $analytics['inventory'];
        $requests = $analytics['requests'];
        $allocations = $analytics['allocations'];
    @endphp

    <div class="space-y-6">
        @if($analytics['migration_required'])
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Lead Bank request/allocation tables are not available yet. Run <span class="font-semibold">php artisan migrate</span> to enable full analytics.
            </div>
        @endif

        <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Available Inventory</p>
                <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($inventory['available']) }}</p>
                <p class="mt-1 text-xs text-gray-500">of {{ number_format($inventory['total']) }} total leads</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                <p class="mt-2 text-3xl font-bold text-amber-600">{{ number_format($requests['pending']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($requests['total']) }} total requests</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Active Allocations</p>
                <p class="mt-2 text-3xl font-bold text-indigo-700">{{ number_format($allocations['active']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($allocations['total']) }} lifetime allocations</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Fulfillment Rate</p>
                <p class="mt-2 text-3xl font-bold text-blue-700">{{ $requests['fulfillment_rate'] }}%</p>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($requests['allocated_leads']) }} / {{ number_format($requests['requested_leads']) }} leads</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Recall Rate</p>
                <p class="mt-2 text-3xl font-bold text-rose-700">{{ $allocations['recall_rate'] }}%</p>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($allocations['recalled'] + $allocations['expired']) }} recalled/expired</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Protected</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($allocations['protected']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Converted</p>
                <p class="mt-2 text-2xl font-bold text-[#205A44]">{{ number_format($allocations['converted']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Expiring 24h</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($allocations['expiring_24h']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-medium text-gray-500">Expiring 72h</p>
                <p class="mt-2 text-2xl font-bold text-orange-700">{{ number_format($allocations['expiring_72h']) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Available Inventory Mix</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-3">Cities</p>
                        <div class="space-y-2">
                            @forelse($analytics['city_mix'] as $label => $total)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">{{ $label }}</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($total) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No available city mix.</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-3">Sources</p>
                        <div class="space-y-2">
                            @forelse($analytics['source_mix'] as $label => $total)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">{{ $label }}</span>
                                    <span class="font-semibold text-gray-900">{{ number_format($total) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No available source mix.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Manager Performance</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Active</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Retained</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Recall</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($analytics['manager_performance'] as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->assignedTo->name ?? 'User #' . $row->assigned_to }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($row->total) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($row->active_total) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-emerald-700">{{ number_format($row->protected_total + $row->converted_total) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-rose-700">{{ number_format($row->recalled_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No allocation performance yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tag Performance</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tag</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Leads</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Retained</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($analytics['tag_performance'] as $tag)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $tag->color ?: '#CBD5E1' }}"></span>
                                            {{ $tag->name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($tag->leads_count) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($tag->allocation_total) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-emerald-700">{{ number_format($tag->retained_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No tag performance yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Expiry Risk</h3>
                <div class="space-y-3">
                    @forelse($analytics['expiry_risk'] as $allocation)
                        <div class="rounded-lg border border-gray-100 p-4 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $allocation->lead->name ?? 'Lead #' . $allocation->lead_id }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $allocation->lead->phone ?? '-' }} · {{ $allocation->assignedTo->name ?? 'User #' . $allocation->assigned_to }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold {{ $allocation->expires_at && $allocation->expires_at->isPast() ? 'text-rose-700' : 'text-amber-700' }}">
                                    {{ optional($allocation->expires_at)->format('d M Y') }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">{{ $allocation->expires_at ? $allocation->expires_at->diffForHumans() : '-' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No allocations expiring in next 72 hours.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Requests</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requester</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allocated</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($analytics['recent_requests'] as $request)
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">#{{ $request->id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $request->requestedBy->name ?? 'User #' . $request->requested_by }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($request->quantity) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ number_format($request->allocatable_count) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ ucwords(str_replace('_', ' ', $request->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
