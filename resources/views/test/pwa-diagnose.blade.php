@extends('layouts.app')

@section('title', 'PWA Push Diagnostic')
@section('page-title', 'PWA Push Diagnostic')
@section('page-subtitle', '1-click report – copy and send to fix exact error')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Diagnostic report</h2>
        <p class="text-gray-600 mb-4">
            Use this for <strong>Gold</strong> (or add <code class="bg-gray-100 px-1 rounded">?user_id=19</code> in URL). Copy the full report below and send it to get the exact fix.
        </p>
        <div class="flex gap-2 mb-4">
            <a href="{{ route('test.pwa-diagnose') }}{{ isset($report['user']['id']) ? '?user_id=' . $report['user']['id'] : '' }}" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 text-sm font-medium">Refresh</a>
            <button type="button" id="copyBtn" class="px-4 py-2 rounded-lg text-white text-sm font-medium" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">
                Copy full report
            </button>
        </div>
        <pre id="reportPre" class="p-4 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto whitespace-pre-wrap border border-gray-700">{{ $reportText }}</pre>
    </div>

    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-sm text-gray-600">
        <strong>URL:</strong> <code>{{ url('/test/pwa-push/diagnose') }}</code> or <code>{{ url('/test/pwa-push/diagnose') }}?user_id=19</code>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('copyBtn').addEventListener('click', function() {
    var pre = document.getElementById('reportPre');
    navigator.clipboard.writeText(pre.textContent).then(function() {
        var btn = document.getElementById('copyBtn');
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy full report'; }, 2000);
    });
});
</script>
@endpush
@endsection
