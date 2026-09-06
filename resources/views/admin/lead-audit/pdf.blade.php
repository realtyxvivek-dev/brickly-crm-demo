<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead Audit Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.45;
            margin: 28px;
        }
        h1, h2, h3, h4, p {
            margin: 0;
        }
        .header {
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #dbe5dd;
        }
        .subtle {
            color: #64748b;
            font-size: 11px;
            margin-top: 4px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
        }
        .label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }
        .value {
            font-size: 13px;
            font-weight: 700;
        }
        .section {
            margin-top: 18px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .flag-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 0 -8px;
        }
        .flag-card {
            border: 1px solid #dbe5dd;
            border-radius: 10px;
            padding: 10px;
        }
        .flag-card.yes {
            background: #ecfdf5;
            border-color: #86efac;
        }
        .flag-card.no {
            background: #fff1f2;
            border-color: #fda4af;
        }
        .flag-title {
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 4px;
        }
        .flag-value {
            font-size: 14px;
            font-weight: 700;
        }
        .diagnosis {
            padding: 12px 14px;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 12px;
        }
        .timeline {
            width: 100%;
            border-collapse: collapse;
        }
        .timeline th,
        .timeline td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            vertical-align: top;
        }
        .timeline th {
            background: #f8fafc;
            text-align: left;
            font-size: 11px;
        }
        .raw-list {
            margin: 0;
            padding-left: 18px;
        }
        .raw-list li {
            margin-bottom: 6px;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 11px;
        }
    </style>
</head>
<body>
    @php
        $summary = $audit['summary'];
        $diagnosis = $audit['diagnosis'];
        $flags = $diagnosis['flags'];
        $rawPanels = $audit['rawPanels'];
    @endphp

    <div class="header">
        <h1>Lead Audit Report</h1>
        <p class="subtle">Generated {{ now()->format('d M Y, h:i A') }} | Full debug summary for admin review</p>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Lead Name</div>
                <div class="value">{{ $summary['lead_name'] }}</div>
            </td>
            <td>
                <div class="label">Phone</div>
                <div class="value">{{ $summary['phone'] }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Source</div>
                <div class="value">{{ $summary['source'] }}</div>
            </td>
            <td>
                <div class="label">Owner</div>
                <div class="value">{{ $summary['owner'] }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">CRM Status</div>
                <div class="value">{{ $summary['status'] }}</div>
            </td>
            <td>
                <div class="label">Created At</div>
                <div class="value">{{ $summary['created_at'] }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Lead ID</div>
                <div class="value">{{ $summary['lead_id'] ?: 'Not Created' }}</div>
            </td>
            <td>
                <div class="label">External IDs</div>
                <div class="value" style="font-size: 11px;">
                    @if(!empty($summary['external_ids']))
                        {{ collect($summary['external_ids'])->map(fn ($value, $label) => $label . ': ' . $value)->implode(' | ') }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">System Diagnosis</div>
        <table class="flag-grid">
            <tr>
                @foreach([
                    'lead_created' => 'Lead Created',
                    'assignment_created' => 'Assignment',
                    'task_created' => 'Task',
                    'automation_matched' => 'Automation',
                    'import_found' => 'Import',
                    'meta_sync_found' => 'Meta Sync',
                    'duplicate_detected' => 'Duplicate / Skip',
                ] as $key => $label)
                    <td class="flag-card {{ $flags[$key] ? 'yes' : 'no' }}">
                        <div class="flag-title">{{ $label }}</div>
                        <div class="flag-value">{{ $flags[$key] ? 'Yes' : 'No' }}</div>
                    </td>
                    @if($loop->iteration % 3 === 0 && !$loop->last)
                        </tr><tr>
                    @endif
                @endforeach
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Likely Outcome Summary</div>
        <div class="diagnosis">{{ $diagnosis['summary'] }}</div>
    </div>

    <div class="section">
        <div class="section-title">Event Timeline</div>
        <table class="timeline">
            <thead>
                <tr>
                    <th style="width: 22%;">Time</th>
                    <th style="width: 18%;">Source</th>
                    <th style="width: 18%;">Actor</th>
                    <th style="width: 18%;">Event</th>
                    <th>Description</th>
                    <th style="width: 12%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($audit['timeline']->take(50) as $event)
                    <tr>
                        <td>{{ optional($event['timestamp'])->format('d M Y, h:i A') ?: 'N/A' }}</td>
                        <td>{{ $event['source'] ?: 'System' }}</td>
                        <td>{{ $event['actor'] ?: 'System' }}</td>
                        <td>{{ $event['title'] }}</td>
                        <td>
                            {{ $event['description'] ?: 'N/A' }}
                            @if(!empty($event['reference']))
                                <div class="subtle">Ref: {{ $event['reference'] }}</div>
                            @endif
                            @if(!empty($event['error']))
                                <div class="subtle">Error: {{ \Illuminate\Support\Str::limit((string) $event['error'], 120) }}</div>
                            @endif
                        </td>
                        <td>{{ $event['status'] ?: 'logged' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No audit events available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Raw Evidence References</div>
        <ul class="raw-list">
            @forelse($rawPanels as $panel)
                <li>
                    <strong>{{ $panel['title'] }}:</strong>
                    {{ $panel['subtitle'] }}
                    <span class="mono">({{ is_array($panel['data']) ? count($panel['data']) : 1 }} record blocks)</span>
                </li>
            @empty
                <li>No raw source panels were found for this audit.</li>
            @endforelse
        </ul>
    </div>
</body>
</html>
