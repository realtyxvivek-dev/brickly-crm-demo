@extends('layouts.app')

@section('title', 'Profile')
@section('page-title', 'Profile')
@section('page-subtitle', 'Junior HR account details')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-2xl font-bold text-emerald-800">
                    {{ strtoupper(substr($user->name ?? 'J', 0, 1)) }}
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Junior HR</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $user->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Assigned hiring candidates only.</p>
                </div>
            </div>

            <a href="{{ route('logout.get') }}" class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                <i class="fas fa-sign-out-alt mr-2"></i>
                Logout
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Name</p>
            <p class="mt-2 text-base font-semibold text-slate-900">{{ $user->name }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Email</p>
            <p class="mt-2 break-all text-base font-semibold text-slate-900">{{ $user->email }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Phone</p>
            <p class="mt-2 text-base font-semibold text-slate-900">{{ $user->phone ?: 'Not set' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Role</p>
            <p class="mt-2 text-base font-semibold text-slate-900">{{ $user->role?->name ?: 'Junior HR' }}</p>
        </div>
    </div>

    <div class="rounded-3xl border border-emerald-100 bg-emerald-50 p-5 text-sm text-emerald-900">
        <div class="font-semibold">Allowed access</div>
        <p class="mt-2">You can view assigned hiring candidates, update hiring status, set next follow-up/interview time, and add HR remarks.</p>
    </div>
</div>
@endsection
