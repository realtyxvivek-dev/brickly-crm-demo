@extends('layouts.app')

@section('title', 'Google Sheet Import Monitor - ' . brand_name())
@section('page-title', 'Google Sheet Import Monitor')

@section('header-actions')
    <a href="{{ route('integrations.meta-sheet.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Meta Sheets
    </a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    @if(!empty($migrationWarning))
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-yellow-800">
            <div class="font-semibold mb-1">Migration required</div>
            <div class="text-sm">{{ $migrationWarning }}</div>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Cron State by Sheet</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Run</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Processed Row</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Error</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($configs as $config)
                        @php
                            $state = $config->importState;
                            $lastLog = $config->importLogs->first();
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $config->sheet_name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $config->creator->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $state?->last_run_at ? $state->last_run_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $state?->last_processed_row ?? 1 }}</td>
                            <td class="px-4 py-3 text-sm text-red-700 max-w-md">
                                {{ $state?->last_error ?: '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($lastLog)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $lastLog->status === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $lastLog->status === 'no_changes' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $lastLog->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $lastLog->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ strtoupper($lastLog->status) }}
                                    </span>
                                @else
                                    <span class="text-gray-500">No runs</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No active Google Sheet configs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Import Runs (Latest 100)</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started At</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Counts</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $log->started_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $log->config->sheet_name ?? 'Deleted sheet' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->trigger_source }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $log->status }}@if($log->timed_out) (timeout) @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $log->duration_ms }} ms</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $log->last_processed_row_before }} -> {{ $log->last_processed_row_after }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                imported={{ $log->imported_count }},
                                exists={{ $log->already_exists_count }},
                                missing={{ $log->missing_required_count }},
                                errors={{ $log->error_count }}
                            </td>
                            <td class="px-4 py-3 text-sm text-red-700 max-w-md">{{ $log->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No run logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

