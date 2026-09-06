@extends('layouts.app')

@section('title', 'Mail Debug - ' . brand_name())
@section('page-title', 'Mail Debug')

@section('header-actions')
    <a href="{{ route('admin.system-settings.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to System Settings
    </a>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div id="message-container" class="hidden">
        <div id="message-alert" class="p-4 rounded-lg whitespace-pre-wrap font-mono text-sm"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-cog text-blue-600 mr-2"></i> Mail config (Laravel sees this)
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left border-b">Key</th>
                        <th class="px-4 py-2 text-left border-b">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td class="px-4 py-2 border-b font-medium">config('mail.default')</td><td class="px-4 py-2 border-b">{{ $default ?? '(null)' }}</td></tr>
                    <tr><td class="px-4 py-2 border-b font-medium">config('mail.mailers') keys</td><td class="px-4 py-2 border-b">{{ implode(', ', $mailerKeys ?: ['(none)']) }}</td></tr>
                    <tr><td class="px-4 py-2 border-b font-medium">config('mail.from')</td><td class="px-4 py-2 border-b">{{ ($from['address'] ?? '') . ' / ' . ($from['name'] ?? '') }}</td></tr>
                    @if(!empty($smtp['host']))
                    <tr><td class="px-4 py-2 font-medium">smtp host (effective)</td><td>{{ $smtp['host'] }} : {{ $smtp['port'] ?? '' }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500">Localhost: Use <strong>MAIL_MAILER=log</strong> in .env – no connection needed, emails go to <code>storage/logs/laravel.log</code>. Same from address (support@crm.bihtech.in / {{ brand_name() }}) shows when not set in .env.</p>
        <p class="mt-1 text-xs text-amber-600">Connection refused? Set <strong>MAIL_MAILER=log</strong> in .env and run <code>php artisan config:clear</code>.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-envelope-open-text text-amber-600 mr-2"></i> .env mail vars (password hidden)
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left border-b">Variable</th>
                        <th class="px-4 py-2 text-left border-b">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($envMail as $k => $v)
                    <tr><td class="px-4 py-2 border-b font-medium">{{ $k }}</td><td class="px-4 py-2 border-b">{{ $v ?? '(empty)' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fas fa-paper-plane text-green-600 mr-2"></i> Send test email
        </h2>
        <p class="text-sm text-gray-600 mb-4">On failure, the full error will appear below so you can fix the issue.</p>
        <form id="mail-debug-form" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label for="debug-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="debug-email" name="email" required value="vivek.baseinfra@gmail.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            <button type="submit" id="debug-send-btn" class="px-6 py-2.5 bg-green-700 hover:bg-green-800 text-white font-medium rounded-lg">
                <i class="fas fa-paper-plane mr-2"></i>Send test
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('mail-debug-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('debug-send-btn');
    var msgContainer = document.getElementById('message-container');
    var msgAlert = document.getElementById('message-alert');
    var email = document.getElementById('debug-email').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    msgContainer.classList.add('hidden');

    fetch('{{ route("admin.system-settings.mail-debug.send") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        msgContainer.classList.remove('hidden');
        if (data.success) {
            msgAlert.className = 'p-4 rounded-lg bg-green-50 text-green-800 border border-green-200 whitespace-pre-wrap font-mono text-sm';
            msgAlert.textContent = data.message;
        } else {
            msgAlert.className = 'p-4 rounded-lg bg-red-50 text-red-800 border border-red-200 whitespace-pre-wrap font-mono text-sm';
            var txt = data.message || 'Unknown error';
            if (data.error_class) txt += '\n\nClass: ' + data.error_class;
            if (data.error_detail) txt += '\n\n' + data.error_detail;
            msgAlert.textContent = txt;
        }
    })
    .catch(function(err) {
        msgContainer.classList.remove('hidden');
        msgAlert.className = 'p-4 rounded-lg bg-red-50 text-red-800 border border-red-200 whitespace-pre-wrap font-mono text-sm';
        msgAlert.textContent = 'Request failed: ' + err.message;
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send test';
    });
});
</script>
@endsection
