@extends('layouts.app')

@section('title', 'Click2API WhatsApp Webhook - ' . brand_name())
@section('page-title', 'Click2API WhatsApp Webhook')

@section('header-actions')
    <a href="{{ route('integrations.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Integrations
    </a>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="rounded-xl border border-teal-100 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">
                    <i class="fas fa-check-circle"></i>
                    CRM endpoint ready
                </div>
                <h2 class="mt-4 text-2xl font-black text-gray-900">Click2API Webhook Setup</h2>
                <p class="mt-2 text-sm text-gray-600">Use this webhook in the Click2API channel panel for WhatsApp number {{ $whatsappNumber }}.</p>
            </div>
            <button type="button" onclick="testChallenge()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white hover:bg-teal-800">
                <i class="fas fa-vial"></i>
                Test Challenge
            </button>
        </div>
    </div>

    <div id="challengeResult" class="hidden rounded-xl border p-4 text-sm font-semibold"></div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900">Copy Into Click2API</h3>
            <div class="mt-5 space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Title</label>
                    <div class="flex gap-2">
                        <input id="webhookTitle" type="text" readonly value="{{ $recommendedTitle }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                        <button type="button" onclick="copyField('webhookTitle')" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-bold text-white hover:bg-gray-800">Copy</button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Webhook URL</label>
                    <div class="flex gap-2">
                        <input id="webhookUrl" type="text" readonly value="{{ $webhookUrl }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                        <button type="button" onclick="copyField('webhookUrl')" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-bold text-white hover:bg-gray-800">Copy</button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Challenge Test URL</label>
                    <div class="flex gap-2">
                        <input id="challengeUrl" type="text" readonly value="{{ $webhookUrl }}?challange=test123" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                        <button type="button" onclick="copyField('challengeUrl')" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-bold text-white hover:bg-gray-800">Copy</button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Opening this URL must return only <span class="font-bold">test123</span>.</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900">Events To Enable</h3>
            <div class="mt-4 space-y-2">
                @foreach($events as $event)
                    <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">
                        <i class="fas fa-check text-teal-700"></i>
                        {{ $event }}
                    </div>
                @endforeach
            </div>
            <div class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                Calls event abhi off rakho.
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900">Vendor Message</h3>
        <textarea id="vendorMessage" readonly rows="8" class="mt-4 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">Webhook URL:
{{ $webhookUrl }}

Please add this webhook for WhatsApp number {{ $whatsappNumber }} and enable:
{{ implode(', ', $events) }}

CRM supports your GET challange verification and returns the same challange value with HTTP 200.</textarea>
        <button type="button" onclick="copyField('vendorMessage')" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white hover:bg-teal-800">
            <i class="fas fa-copy"></i>
            Copy Vendor Message
        </button>
    </div>
</div>

<script>
function copyField(id) {
    const field = document.getElementById(id);
    field.select();
    field.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(field.value);
}

async function testChallenge() {
    const result = document.getElementById('challengeResult');
    const value = 'crm-test-' + Date.now();
    const url = document.getElementById('webhookUrl').value + '?challange=' + encodeURIComponent(value);

    result.className = 'rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-blue-800';
    result.textContent = 'Testing challenge response...';

    try {
        const response = await fetch(url, { method: 'GET', cache: 'no-store' });
        const text = await response.text();
        if (response.ok && text.trim() === value) {
            result.className = 'rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800';
            result.textContent = 'Challenge verified. Click2API webhook can be added now.';
            return;
        }

        result.className = 'rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800';
        result.textContent = 'Challenge failed. Status ' + response.status + ', response: ' + text.slice(0, 160);
    } catch (error) {
        result.className = 'rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800';
        result.textContent = 'Challenge failed: ' + error.message;
    }
}
</script>
@endsection
