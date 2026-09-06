@extends('layouts.app')

@section('title', 'Meta Review - CRM')
@section('page-title', 'Meta Review')

@push('styles')
<style>
    .meta-review-shell {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .meta-review-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        padding: 24px 28px;
        border-radius: 28px;
        border: 1px solid rgba(6, 58, 28, 0.08);
        background: linear-gradient(135deg, rgba(230, 246, 240, 0.9), rgba(255, 255, 255, 0.96));
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.05);
    }
    .meta-review-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(24, 119, 242, 0.1);
        color: #1877f2;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .meta-review-title {
        margin-top: 14px;
        font-size: 34px;
        font-weight: 800;
        color: #163028;
        letter-spacing: -0.04em;
    }
    .meta-review-copy {
        margin-top: 10px;
        max-width: 760px;
        color: #5f6c7b;
        font-size: 15px;
        line-height: 1.7;
    }
    .meta-review-surface {
        border-radius: 28px;
        border: 1px solid rgba(6, 58, 28, 0.08);
        background: #fff;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }
    .meta-review-table {
        width: 100%;
        border-collapse: collapse;
    }
    .meta-review-table th {
        background: #f3f7f4;
        color: #667085;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        text-align: left;
    }
    .meta-review-table th,
    .meta-review-table td {
        padding: 16px 18px;
        border-bottom: 1px solid #edf2ee;
        vertical-align: top;
    }
    .meta-review-table tr:last-child td {
        border-bottom: none;
    }
    .meta-review-name {
        font-size: 16px;
        font-weight: 700;
        color: #163028;
    }
    .meta-review-sub {
        margin-top: 4px;
        font-size: 13px;
        color: #6b7280;
        line-height: 1.5;
    }
    .meta-review-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 700;
        background: #eef2f7;
        color: #465467;
        text-transform: capitalize;
    }
    .meta-review-badge.synced {
        background: #dcfce7;
        color: #166534;
    }
    .meta-review-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }
    .meta-review-badge.failed {
        background: #fee2e2;
        color: #b91c1c;
    }
    .meta-review-badge.skipped {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .meta-review-form {
        display: grid;
        grid-template-columns: minmax(180px, 220px) minmax(180px, 1fr) auto auto;
        gap: 10px;
        align-items: start;
    }
    .meta-review-select,
    .meta-review-note {
        width: 100%;
        min-height: 44px;
        border-radius: 14px;
        border: 1px solid #d7e0d9;
        background: #fff;
        color: #16232f;
        padding: 10px 12px;
        font-size: 14px;
    }
    .meta-review-note {
        min-height: 44px;
        resize: vertical;
    }
    .meta-review-btn {
        min-height: 44px;
        border: none;
        border-radius: 14px;
        padding: 0 16px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .meta-review-btn-primary {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: #fff;
    }
    .meta-review-btn-secondary {
        background: #f3f4f6;
        color: #1f2937;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .meta-review-empty {
        padding: 48px 24px;
        text-align: center;
        color: #6b7280;
    }
    @media (max-width: 1200px) {
        .meta-review-form {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .meta-review-hero {
            padding: 20px;
        }
        .meta-review-title {
            font-size: 28px;
        }
        .meta-review-table,
        .meta-review-table thead,
        .meta-review-table tbody,
        .meta-review-table tr,
        .meta-review-table th,
        .meta-review-table td {
            display: block;
            width: 100%;
        }
        .meta-review-table thead {
            display: none;
        }
        .meta-review-table tr {
            border-bottom: 1px solid #edf2ee;
            padding: 16px;
        }
        .meta-review-table td {
            border: none;
            padding: 8px 0;
        }
    }
</style>
@endpush

@section('content')
<div class="meta-review-shell">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <section class="meta-review-hero">
        <div>
            <span class="meta-review-kicker"><i class="fab fa-facebook"></i> CRM Meta Queue</span>
            <h1 class="meta-review-title">Meta Review</h1>
            <p class="meta-review-copy">
                Yahan sirf Meta-linked leads aati hain. Current CRM status alag dikh raha hai, aur Meta stage ko row-level par direct update karke sync queue me bheja ja sakta hai.
            </p>
        </div>
        <div class="meta-review-badge">{{ $leads->total() }} leads</div>
    </section>

    <section class="meta-review-surface">
        @if($leads->isEmpty())
            <div class="meta-review-empty">
                Koi Meta-linked lead available nahi hai.
            </div>
        @else
            <table class="meta-review-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>CRM Status</th>
                        <th>Assignment</th>
                        <th>Meta Review</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                        @php
                            $fbLead = $lead->latestFbLead;
                            $assignedTo = $lead->activeAssignments->first()?->assignedTo?->name;
                            $selectedStage = $lead->meta_stage ?: 'intake';
                        @endphp
                        <tr>
                            <td>
                                <div class="meta-review-name">{{ $lead->name ?: 'Unnamed Lead' }}</div>
                                <div class="meta-review-sub">{{ $lead->phone ?: 'No phone' }}</div>
                                <div class="meta-review-sub">Form: {{ $fbLead?->form?->form_name ?: 'Meta Form' }}</div>
                                <div class="meta-review-sub">Leadgen: {{ $fbLead?->leadgen_id ?: 'N/A' }}</div>
                            </td>
                            <td>
                                <div class="meta-review-badge">{{ str_replace('_', ' ', $lead->status ?: 'new') }}</div>
                                <div class="meta-review-sub">Next follow up: {{ $lead->next_followup_at?->format('d M Y, h:i A') ?: 'N/A' }}</div>
                            </td>
                            <td>
                                <div class="meta-review-sub"><strong>Assigned:</strong> {{ $assignedTo ?: 'Unassigned' }}</div>
                                <div class="meta-review-sub"><strong>Updated by:</strong> {{ $lead->metaStageUpdatedBy?->name ?: 'N/A' }}</div>
                                <div class="meta-review-sub"><strong>Updated at:</strong> {{ $lead->meta_stage_updated_at?->format('d M Y, h:i A') ?: 'N/A' }}</div>
                                @if($lead->meta_sync_status)
                                    <div class="meta-review-sub">
                                        <span class="meta-review-badge {{ $lead->meta_sync_status }}">{{ $lead->meta_sync_status }}</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('crm.meta-review.update', $lead) }}" class="meta-review-form">
                                    @csrf
                                    <select name="meta_stage" class="meta-review-select">
                                        @foreach($stageOptions as $option)
                                            <option value="{{ $option['value'] }}" {{ $selectedStage === $option['value'] ? 'selected' : '' }}>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <textarea name="meta_review_note" class="meta-review-note" placeholder="Add note...">{{ $lead->meta_review_note }}</textarea>
                                    <button type="submit" class="meta-review-btn meta-review-btn-primary">Save</button>
                                    <a href="{{ route('leads.show', $lead) }}" class="meta-review-btn meta-review-btn-secondary">Open Lead</a>
                                </form>
                                @if($lead->meta_sync_status === 'skipped' && $lead->meta_last_sync_error)
                                    <div class="meta-review-sub" style="margin-top:10px; color:#1d4ed8;">{{ $lead->meta_last_sync_error }}</div>
                                @elseif($lead->meta_sync_status === 'failed' && $lead->meta_last_sync_error)
                                    <div class="meta-review-sub" style="margin-top:10px; color:#b91c1c;">{{ $lead->meta_last_sync_error }}</div>
                                @elseif($lead->last_sent_meta_stage)
                                    <div class="meta-review-sub" style="margin-top:10px;">Last synced stage: {{ str_replace('_', ' ', $lead->last_sent_meta_stage) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:16px 18px;">
                {{ $leads->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
