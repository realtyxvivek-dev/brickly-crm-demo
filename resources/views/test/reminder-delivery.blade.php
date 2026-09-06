@extends('layouts.app')

@section('title', 'Test: Reminder Delivery')
@section('page-title', 'Reminder Delivery Test')
@section('page-subtitle', 'Select a user and fire reminder notifications through the real AppNotification + FCM pipeline')

@php
    $result = $result ?? session('reminderDeliveryResult');
@endphp

@section('content')
<div class="mx-auto" style="max-width: 980px;">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[400px_minmax(0,1fr)]">
        <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-lg font-semibold text-[#063A1C]">Send Reminder Test</h2>
            <p class="mb-5 text-sm text-gray-600">
                Ye page direct reminder notification create karti hai. Isse DB notification, in-app popup, browser notification, aur FCM push path test kar sakte ho.
            </p>

            <form method="POST" action="{{ route('test.reminder-delivery.send') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="user_id" class="mb-1 block text-sm font-medium text-gray-700">Select user</label>
                    <select name="user_id" id="user_id" required class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200">
                        <option value="">-- Select active user --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (string) old('user_id') === (string) $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->role->name ?? 'No role' }})
                                | FCM {{ $user->fcm_tokens_count }}
                                | Push {{ $user->push_subscriptions_count }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="stage_minutes" class="mb-1 block text-sm font-medium text-gray-700">Reminder stage</label>
                    <select name="stage_minutes" id="stage_minutes" required class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200">
                        <option value="15" {{ old('stage_minutes', '15') === '15' ? 'selected' : '' }}>15 minutes before</option>
                        <option value="5" {{ old('stage_minutes') === '5' ? 'selected' : '' }}>5 minutes before</option>
                    </select>
                </div>

                <div>
                    <label for="test_note" class="mb-1 block text-sm font-medium text-gray-700">Test note</label>
                    <input type="text" name="test_note" id="test_note" value="{{ old('test_note') }}" class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200" placeholder="Optional note to identify this reminder test">
                </div>

                <div class="grid gap-3">
                    @foreach($reminderTypes as $type => $meta)
                        <button type="submit" name="reminder_type" value="{{ $type }}" class="inline-flex items-center justify-center rounded-xl border border-[#D7E9DE] bg-[#F3FBF6] px-4 py-3 text-sm font-semibold text-[#0B6B4F] transition hover:bg-[#E8F7EE]">
                            {{ $meta['title'] }}
                        </button>
                    @endforeach
                </div>
            </form>

            <div class="mt-5 rounded-2xl bg-[#F7F6F3] p-4 text-sm text-gray-700">
                <div class="font-semibold text-[#063A1C]">Use this for</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>User selection test</li>
                    <li>Browser popup / in-app popup test</li>
                    <li>PWA / app push path test</li>
                    <li>Action URL navigation test</li>
                </ul>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-lg font-semibold text-[#063A1C]">Test Link</h2>
                <p class="text-sm text-gray-600">Is page ka direct link yahi hai. Browser ya device par isi link ko open karke test karo.</p>
                <div class="mt-3 rounded-2xl bg-[#F7F6F3] px-4 py-3 text-sm text-[#063A1C] break-all">
                    {{ route('test.reminder-delivery') }}
                </div>
            </div>

            @if($result)
                <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-lg font-semibold text-[#063A1C]">Latest Result</h2>
                    <dl class="space-y-2 text-sm text-gray-700">
                        <div><dt class="font-medium">Generated at</dt><dd>{{ data_get($result, 'generated_at') }}</dd></div>
                        <div><dt class="font-medium">User</dt><dd>{{ data_get($result, 'user.name') }} ({{ data_get($result, 'user.role') }})</dd></div>
                        <div><dt class="font-medium">Stage</dt><dd>{{ data_get($result, 'stage_minutes') }} min</dd></div>
                        <div><dt class="font-medium">FCM tokens</dt><dd>{{ data_get($result, 'user.fcm_tokens_count', 0) }}</dd></div>
                        <div><dt class="font-medium">Push subscriptions</dt><dd>{{ data_get($result, 'user.push_subscriptions_count', 0) }}</dd></div>
                        <div><dt class="font-medium">Notification ID</dt><dd>{{ data_get($result, 'notification.id') }}</dd></div>
                        <div><dt class="font-medium">Notification type</dt><dd>{{ data_get($result, 'notification.type') }}</dd></div>
                        <div><dt class="font-medium">Title</dt><dd>{{ data_get($result, 'notification.title') }}</dd></div>
                        <div><dt class="font-medium">Message</dt><dd>{{ data_get($result, 'notification.message') }}</dd></div>
                        <div><dt class="font-medium">Action URL</dt><dd class="break-all">{{ data_get($result, 'notification.action_url') }}</dd></div>
                        @if(data_get($result, 'test_note'))
                            <div><dt class="font-medium">Test note</dt><dd>{{ data_get($result, 'test_note') }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
