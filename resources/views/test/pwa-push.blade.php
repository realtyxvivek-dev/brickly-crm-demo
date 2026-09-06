@extends('layouts.app')

@section('title', 'Test: PWA Push Notification')
@section('page-title', 'Test: PWA Push Notification')
@section('page-subtitle', 'Select a user and send a test push to check delivery')

@section('content')
<div class="max-w-2xl mx-auto">
    @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Send test PWA notification</h2>
        <p class="text-gray-600 mb-4">
            Choose a user and send a test "New lead assigned" push. They will get it only if:
        </p>
        <ul class="list-disc list-inside text-gray-600 mb-6 space-y-1">
            <li>They have allowed notifications and opened the app (or PWA) at least once so a push subscription was saved</li>
            <li>VAPID keys are set in <code class="bg-gray-100 px-1 rounded">.env</code> and <code class="bg-gray-100 px-1 rounded">minishlink/web-push</code> is installed</li>
        </ul>

        <form method="POST" action="{{ route('test.pwa-push.send') }}" class="space-y-4">
            @csrf
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Select user</label>
                <select name="user_id" id="user_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="">-- Select user --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->email }})
                            @if($u->has_push_subscription ?? false)
                                — has PWA subscription
                            @else
                                — no push subscription yet
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-6 py-3 rounded-lg font-semibold text-white transition shadow-lg hover:opacity-90" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">
                <i class="fa-solid fa-paper-plane mr-2"></i> Send test PWA notification
            </button>
        </form>
    </div>

    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-sm text-gray-600">
        <strong>URLs:</strong>
        <ul class="mt-2 space-y-1">
            <li>• This page: <a href="{{ route('test.pwa-push') }}" class="text-blue-600 hover:underline">{{ route('test.pwa-push') }}</a></li>
            <li>• UI preview (local notification only): <a href="{{ route('pwa.notification-test') }}" class="text-blue-600 hover:underline">{{ route('pwa.notification-test') }}</a></li>
            <li>• Lead-assigned test (assign to yourself): <a href="{{ route('test.lead-notification') }}" class="text-blue-600 hover:underline">{{ route('test.lead-notification') }}</a></li>
        </ul>
    </div>
</div>
@endsection
