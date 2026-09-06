@extends('layouts.app')

@section('title', 'Project Share Analytics - ' . ($project->name ?? brand_name()))
@section('page-title', 'Project Share Analytics')

@php
    $summary = $summary ?? [];
    $totals = $summary['totals'] ?? [];
    $visitor = $summary['visitor'] ?? [];
    $formatDuration = function ($milliseconds): string {
        $seconds = (int) floor(((int) $milliseconds) / 1000);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;
        if ($minutes < 60) {
            return $minutes . 'm ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 's';
        }
        $hours = intdiv($minutes, 60);
        return $hours . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };
    $metricCards = [
        ['label' => 'Share Links', 'value' => $totals['share_links'] ?? 0],
        ['label' => 'Opens', 'value' => $totals['opens'] ?? 0],
        ['label' => 'Visits', 'value' => $totals['unique_visits'] ?? 0],
        ['label' => 'Active Time', 'value' => $formatDuration($totals['total_active_time_ms'] ?? 0)],
        ['label' => 'CTA Clicks', 'value' => $totals['cta_clicks'] ?? 0],
        ['label' => 'Downloads', 'value' => $totals['downloads'] ?? 0],
    ];
@endphp

@section('content')
<div class="space-y-6" style="font-family:Arial, Helvetica, sans-serif;">
    <section style="border:1px solid #dbe6f3;border-radius:24px;padding:24px;background:linear-gradient(135deg,#0f4d38,#145f45);color:#fff;">
        <div style="display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:flex-end;">
            <div style="max-width:760px;">
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    <span style="padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.14);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Project Share Analytics</span>
                    @if(!empty($summary['uses_preview_fallback']))
                        <span style="padding:6px 12px;border-radius:999px;background:#fff3cd;color:#8a5a00;font-size:12px;font-weight:700;">Preview fallback data</span>
                    @endif
                </div>
                <h1 style="margin:0;font-size:32px;font-weight:800;">{{ $project->name }}</h1>
                <p style="margin:10px 0 0;color:rgba(255,255,255,.82);font-size:14px;line-height:1.7;">
                    Track share-link opens, visit sessions, CTA engagement, device/location basics, and exact downloaded assets from the public project page.
                </p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <a href="{{ route('projects.edit', $project) }}#project-publish" class="btn btn-secondary">Back to Builder</a>
                <a href="{{ route('projects.public-pages.preview', $project) }}" target="_blank" class="btn btn-primary">Open Preview</a>
            </div>
        </div>
    </section>

    <section style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;">
        @foreach($metricCards as $card)
            <div style="border:1px solid #dbe6f3;border-radius:18px;background:#fff;padding:18px;">
                <div style="font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#64748b;">{{ $card['label'] }}</div>
                <div style="margin-top:10px;font-size:24px;font-weight:800;color:#0f172a;">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </section>

    <section style="display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr);gap:20px;">
        <div style="display:flex;flex-direction:column;gap:20px;">
            <div style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0;font-size:22px;font-weight:800;color:#0f172a;">Share Link Breakdown</h2>
                        <p style="margin:6px 0 0;color:#64748b;font-size:14px;">Per token open history, active time, latest device/location, and top downloaded file.</p>
                    </div>
                </div>
                <div style="overflow:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:980px;">
                        <thead>
                            <tr style="background:#f8fafc;color:#475569;">
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Link</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Advisor / Lead</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Opens</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Downloads</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">CTA</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Total Time</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Top Download</th>
                                <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Latest Snapshot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($summary['share_link_rows'] ?? []) as $row)
                                <tr>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">
                                        <div style="font-weight:700;color:#0f172a;">{{ \Illuminate\Support\Str::limit($row['token'], 16) }}</div>
                                        <div style="margin-top:4px;font-size:12px;color:#64748b;">{{ ucfirst($row['status']) }}</div>
                                    </td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">
                                        <div style="font-weight:700;color:#0f172a;">{{ $row['advisor_name'] ?: 'No advisor' }}</div>
                                        <div style="margin-top:4px;font-size:12px;color:#64748b;">Lead ID: {{ $row['lead_id'] ?: 'N/A' }}</div>
                                    </td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $row['opens'] }}</td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $row['downloads'] }}</td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $row['cta_clicks'] }}</td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $formatDuration($row['total_duration_ms'] ?? 0) }}</td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $row['top_download'] ?: 'Waiting' }}</td>
                                    <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">
                                        <div style="font-weight:700;color:#0f172a;">{{ $row['latest_device'] ?: 'Waiting' }}</div>
                                        <div style="margin-top:4px;font-size:12px;color:#64748b;">{{ $row['latest_browser'] ?: 'Unknown' }} · {{ $row['latest_location'] ?: 'Unavailable' }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" style="padding:28px;text-align:center;color:#64748b;">No share-link analytics yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
                <h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">Visit Timeline</h2>
                <p style="margin:0 0 16px;color:#64748b;font-size:14px;">Session-wise activity with duration, device snapshot, downloads, and last CTA.</p>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    @forelse(($summary['visit_rows'] ?? []) as $visit)
                        <div style="border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:16px;">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                <div>
                                    <div style="font-weight:800;color:#0f172a;">{{ !empty($visit['started_at']) ? \Illuminate\Support\Carbon::parse($visit['started_at'])->format('d M Y, h:i A') : 'Visit' }}</div>
                                    <div style="margin-top:6px;font-size:13px;color:#64748b;">{{ ($visit['visitor']['device'] ?? 'Waiting') }} · {{ ($visit['visitor']['browser'] ?? 'Unknown') }} · {{ collect([$visit['visitor']['city'] ?? null, $visit['visitor']['region'] ?? null, $visit['visitor']['country'] ?? null])->filter()->implode(', ') ?: 'Unavailable' }}</div>
                                </div>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <span style="padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:12px;font-weight:700;">{{ $formatDuration($visit['duration_ms'] ?? 0) }}</span>
                                    <span style="padding:6px 10px;border-radius:999px;background:#ecfccb;color:#3f6212;font-size:12px;font-weight:700;">{{ $visit['actions_count'] ?? 0 }} actions</span>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px;">
                                <div style="background:#fff;border-radius:14px;padding:12px;">
                                    <div style="font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b;">Event Count</div>
                                    <div style="margin-top:6px;font-weight:800;color:#0f172a;">{{ $visit['event_count'] ?? 0 }}</div>
                                </div>
                                <div style="background:#fff;border-radius:14px;padding:12px;">
                                    <div style="font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b;">Last CTA</div>
                                    <div style="margin-top:6px;font-weight:800;color:#0f172a;">{{ str_replace('_', ' ', $visit['last_cta'] ?? 'Waiting') }}</div>
                                </div>
                                <div style="background:#fff;border-radius:14px;padding:12px;">
                                    <div style="font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b;">Downloads</div>
                                    <div style="margin-top:6px;font-weight:800;color:#0f172a;">{{ collect($visit['downloads'] ?? [])->join(', ') ?: 'None' }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div style="padding:26px;border:1px dashed #cbd5e1;border-radius:18px;color:#64748b;text-align:center;">No session data yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <div style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
                <h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">Top Signals</h2>
                <div style="display:grid;gap:12px;margin-top:16px;">
                    @foreach([
                        'Top CTA' => $totals['top_cta'] ? str_replace('_', ' ', $totals['top_cta']) : 'Waiting',
                        'Top Section' => $totals['top_section'] ? str_replace('_', ' ', $totals['top_section']) : 'Waiting',
                        'Top Price Sheet' => $totals['top_price_sheet'] ?: 'Waiting',
                        'Top Details PDF' => $totals['top_details_pdf'] ?: 'Waiting',
                        'Last Activity' => !empty($totals['last_activity_at']) ? \Illuminate\Support\Carbon::parse($totals['last_activity_at'])->format('d M Y, h:i A') : 'Waiting',
                    ] as $label => $value)
                        <div style="border-radius:14px;background:#f8fafc;padding:14px;">
                            <div style="font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b;">{{ $label }}</div>
                            <div style="margin-top:6px;font-weight:800;color:#0f172a;">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
                <h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">Visitor Snapshot</h2>
                <div style="display:grid;gap:12px;margin-top:16px;">
                    @foreach([
                        'Device' => $visitor['device'] ?? 'Unknown',
                        'Browser' => $visitor['browser'] ?? 'Unknown',
                        'OS' => $visitor['os'] ?? 'Unknown',
                        'Screen' => $visitor['screen'] ?? 'Unavailable',
                        'Language' => $visitor['language'] ?? 'Unavailable',
                        'Approx Location' => collect([$visitor['city'] ?? null, $visitor['region'] ?? null, $visitor['country'] ?? null])->filter()->implode(', ') ?: 'Unavailable',
                    ] as $label => $value)
                        <div style="border-radius:14px;background:#f8fafc;padding:14px;">
                            <div style="font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b;">{{ $label }}</div>
                            <div style="margin-top:6px;font-weight:800;color:#0f172a;">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
                <h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">CTA Breakdown</h2>
                <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px;">
                    @forelse(($summary['cta_breakdown'] ?? []) as $label => $count)
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border-radius:14px;background:#f8fafc;padding:12px 14px;">
                            <span style="font-weight:700;color:#0f172a;">{{ str_replace('_', ' ', $label) }}</span>
                            <span style="font-weight:800;color:#0f4d38;">{{ $count }}</span>
                        </div>
                    @empty
                        <div style="padding:18px;border-radius:14px;background:#f8fafc;color:#64748b;">No CTA clicks yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section style="border:1px solid #dbe6f3;border-radius:22px;background:#fff;padding:22px;">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px;">
            <div>
                <h2 style="margin:0;font-size:22px;font-weight:800;color:#0f172a;">Download Analytics</h2>
                <p style="margin:6px 0 0;color:#64748b;font-size:14px;">Exact file identity for price sheets, brochures, and details PDFs.</p>
            </div>
        </div>
        <div style="overflow:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:820px;">
                <thead>
                    <tr style="background:#f8fafc;color:#475569;">
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Type</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">File / Variant</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Count</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Section</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">CTA</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;">Last Download</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($summary['download_rows'] ?? []) as $row)
                        <tr>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ str_replace('_', ' ', $row['event_name']) }}</td>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;font-weight:700;color:#0f172a;">{{ $row['label'] }}</td>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ $row['count'] }}</td>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ str_replace('_', ' ', $row['section'] ?? 'media') }}</td>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ str_replace('_', ' ', $row['cta'] ?? 'download') }}</td>
                            <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;">{{ !empty($row['last_at']) ? \Illuminate\Support\Carbon::parse($row['last_at'])->format('d M Y, h:i A') : 'Waiting' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:28px;text-align:center;color:#64748b;">No downloads tracked yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
