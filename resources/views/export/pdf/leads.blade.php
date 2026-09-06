<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Leads Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h1 {
            color: #063A1C;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #063A1C;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <h1>Leads Export Report</h1>
    <p><strong>Generated:</strong> {{ date('Y-m-d H:i:s') }}</p>
    <p><strong>Total Records:</strong> {{ count($leads) }}</p>
    
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($leads as $index => $lead)
                <tr>
                    @foreach($fields as $field)
                        <td>
                            @php
                                $remarkCandidates = collect();
                                foreach (($lead->tasks ?? collect()) as $task) {
                                    foreach (['outcome_remark', 'notes', 'description'] as $attribute) {
                                        $value = trim((string) ($task->{$attribute} ?? ''));
                                        if ($value !== '') {
                                            $remarkCandidates->push(['value' => $value, 'at' => $task->updated_at ?? $task->created_at]);
                                            break;
                                        }
                                    }
                                }
                                foreach (($lead->followUps ?? collect()) as $followUp) {
                                    $value = trim((string) ($followUp->notes ?? ''));
                                    if ($value !== '') {
                                        $remarkCandidates->push(['value' => $value, 'at' => $followUp->updated_at ?? $followUp->created_at]);
                                    }
                                }
                                foreach (($lead->prospects ?? collect()) as $prospect) {
                                    foreach (['manager_remark', 'employee_remark', 'remark', 'notes'] as $attribute) {
                                        $value = trim((string) ($prospect->{$attribute} ?? ''));
                                        if ($value !== '') {
                                            $remarkCandidates->push(['value' => $value, 'at' => $prospect->updated_at ?? $prospect->created_at]);
                                            break;
                                        }
                                    }
                                }
                                if (trim((string) ($lead->notes ?? '')) !== '') {
                                    $remarkCandidates->push(['value' => trim((string) $lead->notes), 'at' => $lead->updated_at ?? $lead->created_at]);
                                }
                                $latestRemark = $remarkCandidates->sortByDesc(fn ($item) => $item['at'] ? \Carbon\Carbon::parse($item['at'])->timestamp : 0)->first();
                                $nextFollowUpDate = $lead->next_followup_at
                                    ?? collect($lead->followUps ?? [])->filter(fn ($followUp) => $followUp->scheduled_at)->sortByDesc(fn ($followUp) => $followUp->scheduled_at?->timestamp ?? 0)->first()?->scheduled_at
                                    ?? collect($lead->tasks ?? [])->filter(fn ($task) => $task->scheduled_at && in_array($task->status, ['pending', 'in_progress', 'rescheduled'], true))->sortByDesc(fn ($task) => $task->scheduled_at?->timestamp ?? 0)->first()?->scheduled_at;
                            @endphp
                            @if($field === 'serial_no')
                                {{ $index + 1 }}
                                        @elseif($field === 'last_assigned_to')
                                            {{ $lead->latestAssignment?->assignedTo?->name ?? 'Never assigned' }}
                                        @elseif($field === 'assigned_to' || $field === 'crm_advisor')
                                            {{ $lead->activeAssignments->first()?->assignedTo->name ?? 'Unassigned' }}
                            @elseif($field === 'status')
                                {{ app(\App\Services\LeadDisplayStatusResolver::class)->label($lead) }}
                            @elseif($field === 'source')
                                {{ \App\Models\Lead::displaySourceLabel($lead->source) }}
                            @elseif($field === 'latest_remark')
                                {{ $latestRemark['value'] ?? 'N/A' }}
                            @elseif($field === 'next_followup_date')
                                {{ $nextFollowUpDate ? \Carbon\Carbon::parse($nextFollowUpDate)->format('Y-m-d H:i') : 'N/A' }}
                            @elseif(in_array($field, ['created_at', 'updated_at', 'last_contacted_at', 'marked_dead_at']))
                                {{ $lead->$field ? $lead->$field->format('Y-m-d H:i') : 'N/A' }}
                            @elseif($field === 'marked_dead_by')
                                {{ $lead->marked_dead_by ? \App\Models\User::find($lead->marked_dead_by)?->name ?? 'N/A' : 'N/A' }}
                            @else
                                {{ $lead->$field ?? 'N/A' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <p>Generated by {{ brand_name() }} - {{ date('Y') }}</p>
    </div>
</body>
</html>
