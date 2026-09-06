@extends('layouts.app')

@section('title', '99acres Integration - ' . brand_name())
@section('page-title', '99acres Integration')

@section('content')
@php
    $automationRule = \App\Models\SourceAutomationRule::query()
        ->where('source_type', '99acres')
        ->where(function ($query) {
            $query->whereNull('source_id')->orWhere('source_id', '');
        })
        ->where('is_active', true)
        ->first();
    $automationUrl = route('admin.automation.create', [
        'source_type' => '99acres',
        'source_label' => '99acres Lead Receiver',
        'return_to' => request()->fullUrl(),
    ]);
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">99acres Lead Receiver</h2>
                <p class="text-sm text-gray-500 mt-1">Push webhook for receiving 99acres leads directly in CRM.</p>
                @unless($automationRule)
                    <div class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Automation setup pending</div>
                @endunless
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $automationRule ? route('admin.automation.edit', $automationRule) : $automationUrl }}" class="px-4 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm font-semibold hover:bg-blue-100">Setup Automation</a>
                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $settings->is_enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                    {{ $settings->is_enabled ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Webhook URL</label>
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                        <input readonly value="POST {{ $webhookUrl }}" class="w-full px-3 py-2 text-sm bg-gray-50 text-gray-800">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Header</label>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                        X-API-Key: {{ $settings->api_key }}
                    </div>
                </div>

                <form method="POST" action="{{ route('integrations.99acres.regenerate-key') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800">
                        Regenerate API Key
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('integrations.99acres.update') }}" class="space-y-4">
                @csrf
                <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                    <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled) class="rounded border-gray-300">
                    Enable 99acres lead receiver
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Source</label>
                    <input readonly value="99acres" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Status</label>
                    <input readonly value="new" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fallback</label>
                    <select name="fallback_type" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                        @foreach($fallbackOptions as $value => $label)
                            <option value="{{ $value }}" @selected($settings->fallback_type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fallback User</label>
                    <select name="fallback_user_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                        <option value="">None</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((int) $settings->fallback_user_id === (int) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="px-4 py-2 rounded-lg bg-[#006BA6] text-white text-sm font-medium hover:bg-[#005985]">
                    Save Settings
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Sample Payload</h3>
            <form method="POST" action="{{ route('integrations.99acres.test') }}" class="space-y-3">
                @csrf
                <textarea name="payload" rows="13" class="w-full px-3 py-2 rounded-lg border border-gray-300 font-mono text-xs">{{ old('payload', $samplePayload) }}</textarea>
                <button type="submit" class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800">
                    Test Import
                </button>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Partner Handoff</h3>
            <div class="space-y-3 text-sm text-gray-700">
                <div>
                    <div class="font-semibold text-gray-900">URL</div>
                    <code class="block mt-1 rounded bg-gray-50 border border-gray-200 px-3 py-2">POST {{ $webhookUrl }}</code>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">Headers</div>
                    <code class="block mt-1 rounded bg-gray-50 border border-gray-200 px-3 py-2">Content-Type: application/json<br>X-API-Key: {{ $settings->api_key }}</code>
                </div>
                <div>
                    <div class="font-semibold text-gray-900">Required</div>
                    <p class="mt-1">Phone number is mandatory. Name is optional and falls back to 99acres Lead.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Recent Requests</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Lead</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">External ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->created_at?->format('d M Y h:i A') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ in_array($log->status, ['success', 'duplicate'], true) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ str_replace('_', ' ', $log->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $log->phone ?: '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if($log->lead)
                                    <a href="{{ route('leads.show', $log->lead) }}" class="text-[#006BA6] hover:underline">#{{ $log->lead->id }} {{ $log->lead->name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $log->external_lead_id ?: '-' }}</td>
                            <td class="px-4 py-3 text-sm text-red-700">{{ $log->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No 99acres requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
