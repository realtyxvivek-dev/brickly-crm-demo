@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $sourceAutomationRoutePrefix = request()->routeIs('ad-manager.*') ? 'ad-manager.automation.' : 'admin.automation.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Facebook Lead Ads Settings - ' . brand_name())
@section('page-title', 'Facebook Lead Ads – Settings')

@section('header-actions')
    <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back
    </a>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div id="message-container" class="mb-4" style="display: none;">
        <div id="message-alert" class="p-4 rounded-lg"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Connection settings</h2>

        <form id="settings-form">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Page Access Token</label>
                <textarea name="page_access_token" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Paste long-lived Page Access Token">{{ old('page_access_token', $settings->page_access_token) }}</textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Graph API version</label>
                <input type="text" name="graph_version" value="{{ old('graph_version', $settings->graph_version ?? 'v18.0') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="v18.0">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Page ID (optional – set after Test Connection)</label>
                <input type="text" name="page_id" value="{{ old('page_id', $settings->page_id) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Page ID">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Page name (optional)</label>
                <input type="text" name="page_name" value="{{ old('page_name', $pageName ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook verify token (for Meta subscription)</label>
                <input type="text" name="webhook_verify_token" value="{{ old('webhook_verify_token', $settings->webhook_verify_token) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Any string you choose">
            </div>
            <div class="mb-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="signature_verification_enabled" value="1" {{ $settings->signature_verification_enabled ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Verify webhook signature (X-Hub-Signature-256)</span>
                </label>
            </div>
            <div class="mb-4" id="app-secret-wrap" style="{{ $settings->signature_verification_enabled ? '' : 'display:none' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">App secret</label>
                <input type="password" name="app_secret" value="{{ old('app_secret', $settings->app_secret) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="App Secret">
            </div>
            <div class="mt-6 border-t border-gray-100 pt-5">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Marketing API CPL settings</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marketing Access Token</label>
                    <textarea name="marketing_access_token" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="{{ $settings->marketing_access_token ? 'Token saved. Paste a new token only to replace it.' : 'Paste System User token with ads_read permission' }}">{{ old('marketing_access_token') }}</textarea>
                    @if($settings->marketing_access_token)
                        <p class="mt-1 text-xs text-gray-500">Token saved securely. Full token is hidden.</p>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ad Account ID</label>
                        <input type="text" name="ad_account_id" value="{{ old('ad_account_id', $settings->ad_account_id) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="act_1234567890">
                    </div>
                    <label class="flex items-center gap-2 mt-7">
                        <input type="checkbox" name="cpl_sync_enabled" value="1" {{ $settings->cpl_sync_enabled ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Enable hourly CPL sync</span>
                    </label>
                </div>
                <div class="mb-4 text-xs text-gray-500">
                    Last CPL sync: {{ $settings->last_cpl_synced_at ? $settings->last_cpl_synced_at->format('d M Y H:i') : 'Never' }}
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" id="btn-test" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Test connection</button>
                <button type="button" id="btn-test-marketing" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Test Marketing API</button>
                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium">Save settings</button>
            </div>
        </form>
    </div>

    <div id="test-result" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6" style="display: none;">
        <h3 class="font-semibold text-gray-900 mb-2">Pages from Meta</h3>
        <p class="text-sm text-gray-600 mb-3">Add pages to use in Select Form. You can also "Use this page" to fill the fields below for backward compatibility.</p>
        <ul id="pages-list" class="space-y-2"></ul>
    </div>

    @if(isset($addedPages) && $addedPages->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-2">Added pages</h3>
        <p class="text-sm text-gray-600 mb-3">These pages appear in Select Form. Remove only clears the token; configured forms stay linked.</p>
        <ul class="space-y-2" id="added-pages-list">
            @foreach($addedPages as $p)
            <li class="flex items-center justify-between py-2 border-b border-gray-100" data-page-id="{{ $p->page_id }}">
                <span class="font-medium text-gray-800">{{ $p->page_name ?: $p->page_id }}</span>
                <span class="text-xs text-gray-500">{{ $p->page_id }}</span>
                <button type="button" class="btn-remove-page px-2 py-1 text-sm text-red-600 hover:bg-red-50 rounded" data-page-id="{{ $p->page_id }}">Remove</button>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

@php $fbPage = $settings->page_id ? \App\Models\FbPage::where('page_id', $settings->page_id)->first() : null; @endphp
<script>
document.querySelector('input[name="signature_verification_enabled"]').addEventListener('change', function() {
    document.getElementById('app-secret-wrap').style.display = this.checked ? 'block' : 'none';
});
document.getElementById('btn-test').addEventListener('click', function() {
    var token = document.querySelector('textarea[name="page_access_token"]').value.trim();
    var graphVersion = document.querySelector('input[name="graph_version"]').value.trim() || 'v18.0';
    if (!token) { window.alert('Enter Page Access Token first'); return; }
    this.disabled = true;
    fetch('{{ route($fbLeadAdsRoutePrefix . "test-connection") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ page_access_token: token, graph_version: graphVersion })
    }).then(r => r.json()).then(function(data) {
        document.getElementById('btn-test').disabled = false;
        var container = document.getElementById('message-container');
        var alertBox = document.getElementById('message-alert');
        var resultDiv = document.getElementById('test-result');
        var list = document.getElementById('pages-list');
        if (data.success) {
            alertBox.className = 'p-4 rounded-lg bg-green-50 border border-green-200 text-green-800';
            alertBox.textContent = data.message || 'Connection successful. Pages listed below.';
            container.style.display = 'block';
            resultDiv.style.display = 'block';
            var pages = data.pages || [];
            list.innerHTML = pages.length ? pages.map(function(p) {
                var name = (p.name || p.id) + '';
                var safeName = name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return '<li class="flex items-center justify-between py-2 border-b border-gray-100"><span>' + name + '</span><span class="text-xs text-gray-500">' + p.id + '</span><span class="flex gap-2"><button type="button" class="text-sm text-blue-600 hover:underline add-page-btn" data-page-id="' + p.id + '" data-page-name="' + safeName + '" data-access-token="' + (p.access_token || '').replace(/"/g, '&quot;') + '">Add page</button><button type="button" class="text-sm text-gray-600 hover:underline" onclick="document.querySelector(\'input[name=page_id]\').value=\'' + p.id + '\'; document.querySelector(\'input[name=page_name]\').value=\'' + safeName + '\';">Use this page</button></span></li>';
            }).join('') : '<li class="text-gray-500">' + (data.message || 'No pages returned.') + '</li>';
            list.querySelectorAll('.add-page-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var pageId = this.getAttribute('data-page-id');
                    var pageName = this.getAttribute('data-page-name') || pageId;
                    var token = this.getAttribute('data-access-token');
                    if (!token) { window.alert('No token for this page'); return; }
                    btn.disabled = true;
                    fetch('{{ route($fbLeadAdsRoutePrefix . "add-page") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: JSON.stringify({ page_id: pageId, page_name: pageName, page_access_token: token, _token: document.querySelector('input[name="_token"]').value })
                    }).then(r => r.json()).then(function(res) {
                        btn.disabled = false;
                        if (res.success) { window.alert(res.message || 'Page added.'); window.location.reload(); }
                        else { window.alert(res.message || 'Failed'); }
                    }).catch(function() { btn.disabled = false; window.alert('Request failed'); });
                });
            });
        } else {
            alertBox.className = 'p-4 rounded-lg bg-red-50 border border-red-200 text-red-800';
            alertBox.textContent = data.error || 'Connection failed';
            container.style.display = 'block';
            resultDiv.style.display = 'none';
        }
    }).catch(function() {
        document.getElementById('btn-test').disabled = false;
        document.getElementById('message-alert').className = 'p-4 rounded-lg bg-red-50 border border-red-200 text-red-800';
        document.getElementById('message-alert').textContent = 'Request failed';
        document.getElementById('message-container').style.display = 'block';
    });
});
document.getElementById('btn-test-marketing').addEventListener('click', function() {
    var token = document.querySelector('textarea[name="marketing_access_token"]').value.trim();
    var adAccountId = document.querySelector('input[name="ad_account_id"]').value.trim();
    var graphVersion = document.querySelector('input[name="graph_version"]').value.trim() || 'v18.0';
    if (!token) { window.alert('Paste Marketing Access Token first. Existing saved token is hidden, so testing needs a fresh paste.'); return; }
    if (!adAccountId) { window.alert('Enter Ad Account ID first'); return; }
    this.disabled = true;
    fetch('{{ route($fbLeadAdsRoutePrefix . "test-marketing-connection") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ marketing_access_token: token, ad_account_id: adAccountId, graph_version: graphVersion })
    }).then(r => r.json()).then(function(data) {
        document.getElementById('btn-test-marketing').disabled = false;
        var container = document.getElementById('message-container');
        var alertBox = document.getElementById('message-alert');
        if (data.success) {
            alertBox.className = 'p-4 rounded-lg bg-green-50 border border-green-200 text-green-800';
            alertBox.textContent = data.message || 'Marketing API access verified.';
        } else {
            alertBox.className = 'p-4 rounded-lg bg-red-50 border border-red-200 text-red-800';
            alertBox.textContent = data.error || 'Marketing API connection failed';
        }
        container.style.display = 'block';
    }).catch(function() {
        document.getElementById('btn-test-marketing').disabled = false;
        document.getElementById('message-alert').className = 'p-4 rounded-lg bg-red-50 border border-red-200 text-red-800';
        document.getElementById('message-alert').textContent = 'Request failed';
        document.getElementById('message-container').style.display = 'block';
    });
});
document.getElementById('settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var fd = new FormData(form);
    fetch('{{ route($fbLeadAdsRoutePrefix . "settings.update") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: fd
    }).then(r => r.json()).then(function(data) {
        if (data.success) {
            window.alert('Settings saved.');
            window.location.href = '{{ route($fbLeadAdsRoutePrefix . "index") }}';
        } else {
            window.alert(data.message || 'Save failed');
        }
    }).catch(function() { window.alert('Save failed'); });
});
document.querySelectorAll('.btn-remove-page').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var pageId = this.getAttribute('data-page-id');
        if (!pageId || !confirm('Remove this page? You can re-add it from Test connection.')) return;
        var li = this.closest('li');
        btn.disabled = true;
        fetch('{{ route($fbLeadAdsRoutePrefix . "remove-page") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ page_id: pageId, _token: document.querySelector('input[name="_token"]').value })
        }).then(r => r.json()).then(function(res) {
            if (res.success && li) li.remove();
            else if (!res.success) window.alert(res.message || 'Failed');
            btn.disabled = false;
        }).catch(function() { btn.disabled = false; window.alert('Request failed'); });
    });
});
</script>
@endsection
