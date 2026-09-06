<div style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 12px;">New Lead SLA Escalation</h2>

    <p style="margin: 0 0 12px;">
        Lead <strong>{{ $state->lead?->name ?? 'Unknown Lead' }}</strong>
        ({{ $state->lead?->phone ?? 'N/A' }}) from source
        <strong>{{ \App\Models\Lead::displaySourceLabel($state->lead?->source) }}</strong>
        was not responded to within the configured SLA by the full assignment pool.
    </p>

    <p style="margin: 0 0 12px;">
        Current status: <strong>{{ ucfirst($state->status) }}</strong><br>
        Attempts used: <strong>{{ $state->attempt_number }}</strong><br>
        Escalated at: <strong>{{ optional($state->escalated_at)->format('d M Y h:i A') }}</strong>
    </p>

    <h3 style="margin: 18px 0 10px;">Assignment Trail</h3>
    <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
        <thead>
            <tr>
                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">User</th>
                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Assigned</th>
                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Deadline</th>
                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attemptTrail as $attempt)
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $attempt['user_name'] ?? 'Unknown' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $attempt['assigned_at'] ?? 'N/A' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $attempt['deadline_at'] ?? 'N/A' }}</td>
                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $attempt['status'] ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="border: 1px solid #d1d5db; padding: 8px;">No assignment trail available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
