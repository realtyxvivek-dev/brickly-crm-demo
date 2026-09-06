@extends('layouts.app')

@section('title', 'Session Control - ' . brand_name())
@section('page-title', 'Session Control')

@section('header-actions')
    <a href="{{ route('users.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200 text-sm font-medium">
        Back to Users
    </a>
@endsection

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if(!$databaseSessionsEnabled)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                Database session driver enabled nahi hai ya `sessions` table missing hai. Pehle migration run karke app ko `SESSION_DRIVER=database` par chalaiye.
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('admin.sessions.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search by user, email, or role"
                        class="w-full sm:w-80 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-[#205A44] focus:outline-none focus:ring-2 focus:ring-[#205A44]/10"
                    >
                    <button type="submit" class="rounded-lg bg-[#205A44] px-4 py-2 text-sm font-medium text-white hover:bg-[#174634]">
                        Search
                    </button>
                    @if(request('search'))
                        <a href="{{ route('admin.sessions.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Clear
                        </a>
                    @endif
                </form>

                <div class="flex flex-wrap gap-2">
                    <form action="{{ route('admin.sessions.revoke-all-non-admin') }}" method="POST" onsubmit="return confirm('Logout all non-admin sessions?');">
                        @csrf
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            Logout all non-admin
                        </button>
                    </form>
                    <form action="{{ route('admin.sessions.revoke-all') }}" method="POST" onsubmit="return confirm('Logout all users except your current admin session?');">
                        @csrf
                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Logout all users
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            @forelse($sessionGroups as $group)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $group->display_name }}</h3>
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ $group->session_count }} active session(s)
                                </span>
                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                    {{ $group->role_name }}
                                </span>
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                <div>{{ $group->email ?: 'Email unavailable' }}</div>
                                <div class="mt-1">Latest IP: {{ $group->latest_ip_address ?: 'Unknown' }}</div>
                                <div class="mt-1">Latest device: {{ $group->latest_device ?: 'Unknown device' }}</div>
                                <div class="mt-1">Last activity: {{ $group->last_activity_at?->format('d M Y h:i A') }}</div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if($group->user)
                                <a href="{{ route('users.show', $group->user) }}#active-sessions" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Open profile
                                </a>
                                <form action="{{ route('admin.sessions.users.revoke', $group->user) }}" method="POST" onsubmit="return confirm('Logout all sessions for {{ addslashes($group->display_name) }}?');">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                        Logout this user
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">IP</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Last Activity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Flags</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($group->sessions as $session)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="font-medium">{{ $session->device_label }}</div>
                                            <div class="mt-1 text-xs text-gray-500 break-all">{{ $session->user_agent ?: 'User agent unavailable' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $session->ip_address }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $session->last_activity_at->format('d M Y h:i A') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex flex-wrap gap-2">
                                                @if($session->is_current)
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Current session</span>
                                                @endif
                                                @if($session->is_impersonating)
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Impersonating</span>
                                                @endif
                                                @if(!$session->is_current && !$session->is_impersonating)
                                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Active</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-sm text-gray-600">
                    No active user sessions found.
                </div>
            @endforelse
        </div>
    </div>
@endsection
