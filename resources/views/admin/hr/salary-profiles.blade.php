@extends('layouts.app')

@section('title', 'Salary Profiles')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@push('styles')
<style>
    .salary-profiles-page {
        --line: rgba(15, 23, 42, 0.08);
        --text: #0f172a;
        --muted: #64748b;
        --green: #0f5c3f;
        --green-soft: #e7f6ee;
        --shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .salary-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: var(--shadow);
    }

    .salary-hero {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 22px 24px;
    }

    .salary-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .salary-title {
        margin-top: 8px;
        font-size: 32px;
        line-height: 1;
        letter-spacing: -0.05em;
        font-weight: 800;
        color: var(--text);
    }

    .salary-subtitle {
        margin-top: 10px;
        font-size: 14px;
        color: var(--muted);
        font-weight: 500;
    }

    .salary-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        min-width: 360px;
    }

    .salary-stat {
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #f8fafc;
    }

    .salary-stat-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .salary-stat-value {
        display: block;
        margin-top: 8px;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
        color: var(--text);
    }

    .salary-table-wrap {
        overflow-x: auto;
    }

    .salary-table {
        width: 100%;
        min-width: 880px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .salary-table th,
    .salary-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        vertical-align: middle;
    }

    .salary-table th {
        background: #f8fafc;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
        white-space: nowrap;
    }

    .salary-table td {
        font-size: 14px;
        color: var(--text);
        background: #fff;
    }

    .salary-table tr:last-child td {
        border-bottom: none;
    }

    .salary-user-name {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .salary-user-role {
        margin-top: 4px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 500;
    }

    .salary-amount {
        font-weight: 800;
        color: var(--text);
    }

    .salary-empty {
        padding: 28px;
        text-align: center;
        color: var(--muted);
        font-size: 14px;
        font-weight: 500;
    }

    @media (max-width: 900px) {
        .salary-hero {
            padding: 18px;
        }

        .salary-title {
            font-size: 28px;
        }

        .salary-stats {
            min-width: 0;
            width: 100%;
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $latestProfiles = $profiles
        ->sortByDesc(fn ($profile) => optional($profile->effective_from)?->timestamp ?? 0)
        ->unique('user_id')
        ->keyBy('user_id');
@endphp

<div class="hr-setup-page salary-profiles-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Employee Salary',
        'title' => 'All employee salaries in one table',
        'subtitle' => 'Simple list view showing current structure, basic salary, total salary, and effective date.',
        'stats' => [
            ['label' => 'Active Users', 'value' => $users->count(), 'note' => 'Login enabled users'],
            ['label' => 'Profiles Found', 'value' => $latestProfiles->count(), 'note' => 'Current salary records'],
            ['label' => 'Salary Rules', 'value' => $structures->count(), 'note' => 'Available templates'],
        ],
    ])
    @include('admin.hr._nav')

    <div class="salary-card">
        <div style="padding: 18px 20px 0;">
            <h2 class="hr-setup-section-title">Current salary records</h2>
            <p class="hr-setup-section-copy">Showing the latest effective salary profile for each user.</p>
        </div>
        <div class="salary-table-wrap">
            <table class="salary-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Structure</th>
                        <th>Basic Salary</th>
                        <th>Total Salary</th>
                        <th>Effective From</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php($current = $latestProfiles->get($user->id))
                        <tr>
                            <td>
                                <div class="salary-user-name">{{ $user->name }}</div>
                                <div class="salary-user-role">{{ $user->role->name ?? 'Role' }}</div>
                            </td>
                            <td>{{ optional(optional($current)->salaryStructure)->name ?: '--' }}</td>
                            <td class="salary-amount">
                                {{ $current ? 'Rs ' . number_format((float) $current->base_salary, 2) : '--' }}
                            </td>
                            <td class="salary-amount">
                                {{ $current ? 'Rs ' . number_format((float) ($current->total_salary ?? $current->base_salary), 2) : '--' }}
                            </td>
                            <td>{{ optional(optional($current)->effective_from)->format('d M Y') ?: '--' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="salary-empty">No active users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
