@extends('layouts.app')

@section('title', 'Junior HR Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Hiring work and self attendance')

@push('styles')
<style>
    .jr-dashboard {
        max-width: 1180px;
        margin: 0 auto;
        display: grid;
        gap: 18px;
    }

    .jr-hero,
    .jr-card {
        background: #ffffff;
        border: 1px solid #dbe7df;
        border-radius: 22px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
    }

    .jr-hero {
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .jr-eyebrow {
        color: #0f6b43;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .jr-title {
        margin: 6px 0 0;
        color: #062b1d;
        font-size: 28px;
        line-height: 1.15;
        font-weight: 900;
    }

    .jr-copy {
        margin-top: 8px;
        color: #667085;
        font-size: 14px;
    }

    .jr-hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .jr-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 0 16px;
        border-radius: 14px;
        border: 1px solid #dbe7df;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .jr-btn.primary {
        background: #064e3b;
        color: #ffffff;
        border-color: #064e3b;
    }

    .jr-btn.secondary {
        background: #f8fbfa;
        color: #0b3d29;
    }

    .jr-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
    }

    .jr-stat {
        padding: 18px;
    }

    .jr-stat-label {
        color: #667085;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .jr-stat-value {
        margin-top: 8px;
        color: #062b1d;
        font-size: 30px;
        line-height: 1;
        font-weight: 900;
    }

    .jr-stat-note {
        margin-top: 6px;
        color: #667085;
        font-size: 12px;
    }

    .jr-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
        gap: 18px;
        align-items: start;
    }

    .jr-card {
        padding: 22px;
    }

    .jr-section-title {
        color: #062b1d;
        font-size: 20px;
        font-weight: 900;
        margin: 0;
    }

    .jr-list {
        display: grid;
        gap: 10px;
        margin-top: 16px;
    }

    .jr-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 14px;
        border: 1px solid #eef2f0;
        border-radius: 16px;
        background: #fbfdfc;
    }

    .jr-row-title {
        color: #0f172a;
        font-size: 14px;
        font-weight: 900;
    }

    .jr-row-meta {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
    }

    .jr-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 6px 10px;
        background: #ecfdf5;
        color: #047857;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .jr-empty {
        margin-top: 16px;
        padding: 18px;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        color: #64748b;
        font-size: 14px;
        text-align: center;
    }

    .jr-attendance-shell .attendance-widget {
        margin-bottom: 0;
    }

    .jr-attendance-shell-top {
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
    }

    .jr-attendance-shell-top .attendance-widget {
        margin-bottom: 0;
        border-radius: 8px;
        padding: 16px;
        box-shadow: none;
        background: linear-gradient(135deg, #063a1c 0%, #12643e 100%);
    }

    .jr-attendance-pending {
        background: #ffffff;
        border: 1px solid #dbe7df;
        border-radius: 22px;
        padding: 22px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
    }

    .jr-attendance-pending strong {
        display: block;
        color: #062b1d;
        font-size: 18px;
    }

    .jr-attendance-pending span {
        display: block;
        margin-top: 8px;
        color: #667085;
        font-size: 13px;
    }

    @media (max-width: 900px) {
        .jr-hero,
        .jr-main-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        .jr-hero-actions {
            justify-content: flex-start;
        }

        .jr-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
@php
    $statusLabel = fn ($status) => $statusOptions[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
@endphp

<div class="jr-dashboard">
    <section class="jr-hero">
        <div>
            <div class="jr-eyebrow">Junior HR Desk</div>
            <h1 class="jr-title">Good day, {{ $user->name }}</h1>
            <div class="jr-copy">Assigned hiring work aur self attendance ek clean screen par.</div>
        </div>
        <div class="jr-hero-actions">
            <a href="{{ route('junior-hr.dashboard') }}" class="jr-btn secondary">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="{{ route('junior-hr.hiring.index') }}" class="jr-btn primary">
                <i class="fas fa-user-tie"></i>
                Hiring Leads
            </a>
            <a href="{{ route('junior-hr.profile') }}" class="jr-btn secondary">
                <i class="fas fa-user"></i>
                Profile
            </a>
        </div>
    </section>

    <section class="jr-attendance-shell-top">
        @if(auth()->user()?->hasAttendanceRolloutEnabled())
            @include('attendance._widget', ['attendanceWidgetMode' => 'sales_manager_dashboard'])
        @else
            <div class="jr-attendance-pending">
                <strong>Attendance setup pending</strong>
                <span>Contact HR/Admin to enable office, rule, and attendance mapping for this account.</span>
            </div>
        @endif
    </section>

    <section class="jr-summary-grid">
        <div class="jr-card jr-stat">
            <div class="jr-stat-label">Total Assigned</div>
            <div class="jr-stat-value">{{ $summary['total'] }}</div>
            <div class="jr-stat-note">Hiring candidates</div>
        </div>
        <div class="jr-card jr-stat">
            <div class="jr-stat-label">Pending</div>
            <div class="jr-stat-value">{{ $summary['pending'] }}</div>
            <div class="jr-stat-note">Need action</div>
        </div>
        <div class="jr-card jr-stat">
            <div class="jr-stat-label">Interviews</div>
            <div class="jr-stat-value">{{ $summary['interviews'] }}</div>
            <div class="jr-stat-note">Scheduled / done</div>
        </div>
        <div class="jr-card jr-stat">
            <div class="jr-stat-label">Selected</div>
            <div class="jr-stat-value">{{ $summary['selected'] }}</div>
            <div class="jr-stat-note">Ready pipeline</div>
        </div>
        <div class="jr-card jr-stat">
            <div class="jr-stat-label">Rejected</div>
            <div class="jr-stat-value">{{ $summary['rejected'] }}</div>
            <div class="jr-stat-note">Closed cases</div>
        </div>
    </section>

    <section class="jr-main-grid">
        <div class="jr-card">
            <h2 class="jr-section-title">Recent Hiring Work</h2>
            @if($recentCandidates->isNotEmpty())
                <div class="jr-list">
                    @foreach($recentCandidates as $candidate)
                        <a href="{{ route('junior-hr.hiring.show', $candidate) }}" class="jr-row" style="text-decoration:none;">
                            <div>
                                <div class="jr-row-title">{{ $candidate->name ?: 'Unnamed Candidate' }}</div>
                                <div class="jr-row-meta">
                                    {{ $candidate->phone ?: 'No phone' }}
                                    @if($candidate->next_followup_at)
                                        · Next {{ $candidate->next_followup_at->format('d M, h:i A') }}
                                    @endif
                                </div>
                            </div>
                            <span class="jr-badge">{{ $statusLabel($candidate->hiring_status) }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="jr-empty">No hiring candidates assigned yet.</div>
            @endif
        </div>

        <div class="jr-card">
            <h2 class="jr-section-title">Attendance Links</h2>
            <div class="jr-list">
                <a href="{{ route('attendance.leaves') }}" class="jr-row" style="text-decoration:none;">
                    <div>
                        <div class="jr-row-title">Apply Leave</div>
                        <div class="jr-row-meta">Leave request create/view karo.</div>
                    </div>
                    <span class="jr-badge">Self</span>
                </a>
                <a href="{{ route('attendance.regularizations') }}" class="jr-row" style="text-decoration:none;">
                    <div>
                        <div class="jr-row-title">Regularization</div>
                        <div class="jr-row-meta">Missed punch correction request karo.</div>
                    </div>
                    <span class="jr-badge">Self</span>
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
