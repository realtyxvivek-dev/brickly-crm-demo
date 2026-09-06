@extends('layouts.app')

@section('title', 'Test Email - ' . brand_name())
@section('page-title', 'Test Email')

@section('header-actions')
    <a href="{{ route('admin.system-settings.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to System Settings
    </a>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div id="message-container" class="mb-6" style="display: none;">
        <div id="message-alert" class="p-4 rounded-lg"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">
            <i class="fas fa-envelope text-green-700 mr-2"></i> Send sample welcome email
        </h2>
        <p class="text-sm text-gray-600 mb-6">
            Enter an email address and click the button to send a sample new-user welcome email. Use this to test that mail is working and to see the exact format users will receive.
        </p>

        <form id="test-email-form" class="space-y-4">
            @csrf
            <div>
                <label for="test-email" class="block text-sm font-medium text-gray-700 mb-2">Email address</label>
                <input type="email" id="test-email" name="email" required
                    placeholder="e.g. you@example.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <button type="submit" id="send-btn" class="w-full sm:w-auto px-6 py-2.5 bg-green-700 hover:bg-green-800 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                <i class="fas fa-paper-plane"></i>
                Send test email
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('test-email-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('test-email').value.trim();
    const btn = document.getElementById('send-btn');
    const msgContainer = document.getElementById('message-container');
    const msgAlert = document.getElementById('message-alert');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    msgContainer.style.display = 'none';

    fetch('{{ route("admin.system-settings.test-email.send") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
    })
    .then(r => r.json())
    .then(data => {
        msgContainer.style.display = 'block';
        msgAlert.className = 'p-4 rounded-lg ' + (data.success ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200');
        msgAlert.textContent = data.message || (data.success ? 'Email sent.' : 'Failed.');
    })
    .catch(err => {
        msgContainer.style.display = 'block';
        msgAlert.className = 'p-4 rounded-lg bg-red-50 text-red-800 border border-red-200';
        msgAlert.textContent = 'Error: ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send test email';
    });
});
</script>
@endsection
