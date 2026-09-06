@extends('layouts.app')

@section('title', 'Custom Website Integrations - ' . brand_name())
@section('page-title', 'Custom Website Integrations')

@section('header-actions')
    <a href="{{ route('integrations.website.create') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium">
        <i class="fas fa-plus mr-2"></i>
        Add Integration
    </a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Configured Websites</h2>
                <p class="text-sm text-gray-500 mt-1">Manage lead intake, mapping, preview, testing, and request logs.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($integrations->isEmpty())
            <div class="text-center py-16">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl mb-4">
                    <i class="fas fa-globe"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">No website integrations yet</h3>
                <p class="text-sm text-gray-500 mt-2 mb-6">Create your first custom website integration with editable mapping and built-in testing.</p>
                <a href="{{ route('integrations.website.create') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium">
                    Create Integration
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Endpoint</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fallback</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mappings</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($integrations as $integration)
                            @php
                                $automationRule = \App\Models\SourceAutomationRule::query()
                                    ->where('source_type', 'website')
                                    ->where('source_id', (string) $integration->id)
                                    ->where('is_active', true)
                                    ->first();
                                $automationUrl = route('admin.automation.create', [
                                    'source_type' => 'website',
                                    'source_id' => $integration->id,
                                    'source_label' => $integration->name,
                                    'return_to' => request()->fullUrl(),
                                ]);
                            @endphp
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-900">{{ $integration->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $integration->slug }}</div>
                                    @unless($automationRule)
                                        <div class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Automation setup pending</div>
                                    @endunless
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    <div class="max-w-[320px] truncate">{{ url('/api/webhooks/website-leads/' . $integration->slug) }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    @if($integration->is_active)
                                        <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Active</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    {{ \App\Models\WebsiteIntegration::fallbackOptions()[$integration->fallback_type] ?? $integration->fallback_type }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ $integration->fieldMappings->count() }} rows</td>
                                <td class="px-4 py-4 text-sm">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('integrations.website.edit', $integration) }}" class="text-emerald-700 font-medium hover:text-emerald-900">
                                            Open
                                        </a>
                                        <a href="{{ route('integrations.website.edit', $integration) }}" class="text-slate-600 font-medium hover:text-slate-900">
                                            Edit
                                        </a>
                                        <a href="{{ $automationRule ? route('admin.automation.edit', $automationRule) : $automationUrl }}" class="text-blue-700 font-medium hover:text-blue-900">
                                            Setup Automation
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
