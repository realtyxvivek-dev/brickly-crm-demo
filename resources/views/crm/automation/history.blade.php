@extends('layouts.app')

@section('title', 'Import History - ' . brand_name())
@section('page-title', 'Import History')
@section('page-subtitle', 'Review previous CRM import batches with the same data, cleaner presentation.')

@section('content')
<div class="page-shell">
    <section class="crm-hero">
        <div class="crm-hero-grid">
            <div>
                <span class="crm-kicker">
                    <i class="fas fa-clock-rotate-left"></i>
                    Import History
                </span>
                <h2 class="crm-hero-title">Track previous lead imports in the <strong>same CRM operations theme</strong>.</h2>
                <p class="crm-hero-copy">Batch counts, rule names, and statuses remain exactly the same. Presentation ko shared CRM workspace ke saath align kiya gaya hai.</p>
            </div>
            <div class="crm-inline-stack" style="justify-content:flex-end;">
                <a href="{{ route('crm.automation.index') }}" class="btn btn-brand-secondary">Back to Automation</a>
            </div>
        </div>
    </section>

    <section class="crm-surface">
        <div class="crm-table-shell">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Imported By</th>
                            <th>Source</th>
                            <th>File / Sheet</th>
                            <th>Total Leads</th>
                            <th>Imported</th>
                            <th>Failed</th>
                            <th>Rule</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($imports as $import)
                            <tr>
                                <td>{{ $import->created_at->format('M d, Y H:i') }}</td>
                                <td>{{ $import->user->name }}</td>
                                <td>{{ strtoupper($import->source_type) }}</td>
                                <td>{{ $import->file_name ?? $import->google_sheet_name ?? 'N/A' }}</td>
                                <td>{{ $import->total_leads }}</td>
                                <td>{{ $import->imported_leads }}</td>
                                <td>{{ $import->failed_leads }}</td>
                                <td>{{ $import->assignmentRule->name ?? 'N/A' }}</td>
                                <td>
                                    @if($import->status === 'completed')
                                        <span class="crm-pill" style="background:#ecfdf3;color:#027a48;">Completed</span>
                                    @elseif($import->status === 'failed')
                                        <span class="crm-pill" style="background:#fff1f2;color:#b42318;">Failed</span>
                                    @else
                                        <span class="crm-pill" style="background:#fff7e6;color:#b54708;">{{ ucfirst($import->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="crm-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>No import history found.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($imports->hasPages())
            <div class="mt-4">
                {{ $imports->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
