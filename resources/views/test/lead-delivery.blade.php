@extends('layouts.app')

@section('title', 'Test: Lead Delivery Console')
@section('page-title', 'Lead Delivery Console')
@section('page-subtitle', 'Assign a tagged dummy lead and inspect notification delivery diagnostics')

@php
    $result = $result ?? session('leadDeliveryResult');
    $pushStatus = data_get($result, 'push.status');
@endphp

@section('content')
<div class="mx-auto" style="max-width:1120px;">
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

    <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-lg font-semibold text-[#063A1C]">Send Test Lead</h2>
            <p class="mb-5 text-sm text-gray-600">
                This uses the real lead assignment flow, creates a tagged dummy lead, and shows task plus notification evidence immediately.
            </p>

            <form method="POST" action="{{ route('test.lead-delivery.send') }}" class="space-y-4">
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
                    <label for="test_note" class="mb-1 block text-sm font-medium text-gray-700">Test note</label>
                    <textarea name="test_note" id="test_note" rows="4" class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200" placeholder="Optional note to help identify this test run">{{ old('test_note') }}</textarea>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: linear-gradient(135deg, #063A1C, #205A44);">
                    <i class="fa-solid fa-paper-plane mr-2"></i>
                    Create dummy lead and send
                </button>
            </form>

            <div class="mt-5 rounded-2xl bg-[#F7F6F3] p-4 text-sm text-gray-700">
                <div class="font-semibold text-[#063A1C]">What this checks</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Lead creation and active assignment</li>
                    <li>Auto task creation or transfer</li>
                    <li>Laravel database notification and AppNotification entry</li>
                    <li>FCM token / push subscription readiness</li>
                    <li>Recent log hints for failures</li>
                </ul>
            </div>
        </div>

        <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
            <h2 class="mb-2 text-lg font-semibold text-[#063A1C]">Send Individual Notification</h2>
            <p class="mb-5 text-sm text-gray-600">
                Select a user and fire major notification types one-by-one through the same AppNotification + FCM pipeline.
            </p>

            <form method="POST" action="{{ route('test.lead-delivery.send-notification') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="notification_user_id" class="mb-1 block text-sm font-medium text-gray-700">Select user</label>
                    <select name="user_id" id="notification_user_id" required class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200">
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
                    <label for="notification_test_note" class="mb-1 block text-sm font-medium text-gray-700">Test note</label>
                    <input type="text" name="test_note" id="notification_test_note" value="{{ old('test_note') }}" class="w-full rounded-xl border border-gray-300 px-3 py-3 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200" placeholder="Optional note to identify this notification batch">
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($notificationTypes as $type => $meta)
                        <button type="submit" name="notification_type" value="{{ $type }}" class="inline-flex items-center justify-center rounded-xl border border-[#D7E9DE] bg-[#F3FBF6] px-4 py-3 text-sm font-semibold text-[#0B6B4F] transition hover:bg-[#E8F7EE]">
                            {{ $meta['title'] }}
                        </button>
                    @endforeach
                </div>
            </form>

            <div class="mt-5 rounded-2xl bg-[#F7F6F3] p-4 text-sm text-gray-700">
                <div class="font-semibold text-[#063A1C]">Included test buttons</div>
                <div class="mt-2 grid gap-2 text-xs text-gray-600 sm:grid-cols-2">
                    @foreach($notificationTypes as $meta)
                        <div>• {{ $meta['title'] }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-[#063A1C]">Latest Result</h2>
                        <p class="text-sm text-gray-500">
                            @if($result)
                                Generated at {{ data_get($result, 'generated_at') }} using queue `{{ data_get($result, 'queue_connection') }}`.
                            @else
                                Run a test to see delivery diagnostics.
                            @endif
                        </p>
                    </div>
                    @if($result)
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $pushStatus === 'ready' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ ucfirst($pushStatus) }}
                        </span>
                    @endif
                </div>
            </div>

            @if($result)
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Lead</h3>
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div><dt class="font-medium">Lead ID</dt><dd>{{ data_get($result, 'lead.id') ?: 'Not created' }}</dd></div>
                            <div><dt class="font-medium">Name</dt><dd>{{ data_get($result, 'lead.name') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Phone</dt><dd>{{ data_get($result, 'lead.phone') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Created at</dt><dd>{{ data_get($result, 'lead.created_at') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Created by</dt><dd>{{ data_get($result, 'actor.name') ?: 'N/A' }}</dd></div>
                            @if(data_get($result, 'lead.show_url'))
                                <div class="pt-2">
                                    <a href="{{ data_get($result, 'lead.show_url') }}" class="inline-flex items-center rounded-lg bg-[#F3FBF6] px-3 py-2 text-sm font-medium text-[#0B6B4F] hover:bg-[#E6F7ED]">
                                        Open lead
                                    </a>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Assignment</h3>
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div><dt class="font-medium">Assigned to</dt><dd>{{ data_get($result, 'assignment.assigned_to') }}</dd></div>
                            <div><dt class="font-medium">Role</dt><dd>{{ data_get($result, 'assignment.assigned_to_role') }}</dd></div>
                            <div><dt class="font-medium">Active assignment</dt><dd>{{ data_get($result, 'assignment.exists') ? 'Yes' : 'No' }}</dd></div>
                            <div><dt class="font-medium">Assignment row ID</dt><dd>{{ data_get($result, 'assignment.assignment_id') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Event dispatched</dt><dd>{{ data_get($result, 'assignment.event_dispatched') ? 'Yes' : 'No' }}</dd></div>
                            <div><dt class="font-medium">Transferred tasks</dt><dd>Telecaller {{ data_get($result, 'assignment.transferred_counts.telecaller_tasks', 0) }}, Manager {{ data_get($result, 'assignment.transferred_counts.manager_tasks', 0) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Task</h3>
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div><dt class="font-medium">Outcome</dt><dd>{{ str_replace('_', ' ', data_get($result, 'task.outcome', 'unknown')) }}</dd></div>
                            <div><dt class="font-medium">Task type</dt><dd>{{ data_get($result, 'task.task_type') ?: 'None' }}</dd></div>
                            <div><dt class="font-medium">Task ID</dt><dd>{{ data_get($result, 'task.task_id') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Task status</dt><dd>{{ data_get($result, 'task.task_status') ?: 'N/A' }}</dd></div>
                            @if(data_get($result, 'task.skip_reason'))
                                <div><dt class="font-medium">Skip reason</dt><dd>{{ data_get($result, 'task.skip_reason') }}</dd></div>
                            @endif
                            @if(data_get($result, 'task.task_error'))
                                <div><dt class="font-medium text-red-700">Task error</dt><dd class="text-red-700">{{ data_get($result, 'task.task_error') }}</dd></div>
                            @endif
                            @if(data_get($result, 'task.task_url'))
                                <div class="pt-2">
                                    <a href="{{ data_get($result, 'task.task_url') }}" class="inline-flex items-center rounded-lg bg-[#F3FBF6] px-3 py-2 text-sm font-medium text-[#0B6B4F] hover:bg-[#E6F7ED]">
                                        Open task view
                                    </a>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Notifications</h3>
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div><dt class="font-medium">Laravel database notification</dt><dd>{{ data_get($result, 'notifications.database_present') ? 'Present' : 'Not found' }}</dd></div>
                            <div><dt class="font-medium">AppNotification</dt><dd>{{ data_get($result, 'notifications.app_present') ? 'Present' : 'Not found' }}</dd></div>
                            <div><dt class="font-medium">Database notification ID</dt><dd>{{ data_get($result, 'notifications.database_id') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">App notification ID</dt><dd>{{ data_get($result, 'notifications.app_id') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Action URL</dt><dd class="break-all">{{ data_get($result, 'notifications.action_url') ?: 'N/A' }}</dd></div>
                            <div><dt class="font-medium">Message</dt><dd>{{ data_get($result, 'notifications.database_message') ?: data_get($result, 'notifications.app_message') ?: 'N/A' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Push Readiness</h3>
                        <dl class="space-y-2 text-sm text-gray-700">
                            <div><dt class="font-medium">Status</dt><dd>{{ data_get($result, 'push.status') }}</dd></div>
                            <div><dt class="font-medium">FCM tokens</dt><dd>{{ data_get($result, 'push.fcm_tokens_count', 0) }}</dd></div>
                            <div><dt class="font-medium">Push subscriptions</dt><dd>{{ data_get($result, 'push.push_subscriptions_count', 0) }}</dd></div>
                            @if(data_get($result, 'test_note'))
                                <div><dt class="font-medium">Test note</dt><dd>{{ data_get($result, 'test_note') }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Error Summary</h3>
                        @if(data_get($result, 'error'))
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {{ data_get($result, 'error') }}
                            </div>
                        @else
                            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                                No controller-level exception captured.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-[24px] border border-[#E5DED4] bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-base font-semibold text-[#063A1C]">Recent Log Hints</h3>
                    @if(count(data_get($result, 'log_tail', [])))
                        <pre class="overflow-x-auto rounded-2xl bg-[#0F172A] p-4 text-xs leading-6 text-slate-100">{{ implode("\n", data_get($result, 'log_tail', [])) }}</pre>
                    @else
                        <div class="rounded-xl bg-[#F7F6F3] px-4 py-3 text-sm text-gray-600">
                            No matching recent log lines found.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
