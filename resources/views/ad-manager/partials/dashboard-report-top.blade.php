<div class="ql-page">
    <div class="ql-shell">
        <section class="ql-hero">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">Ad Manager</div>
                    <h1>{{ $sourceLabel }} Lead Quality Report</h1>
                    <p>CRM me user outcomes ke basis par live lead quality analysis: Interested, Not Interested, Junk/Invalid, CNP, call later aur pending leads ka breakdown.</p>
                </div>
                <div class="ql-actions ql-no-print">
                    <a href="{{ route('ad-manager.meta.index') }}" class="ql-btn light"><i class="fas fa-share-alt"></i> Meta Ops</a>
                    <a href="{{ route('ad-manager.meta.facebook-lead-ads.diagnostics') }}" class="ql-btn light"><i class="fas fa-stethoscope"></i> Diagnostics</a>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('ad-manager.dashboard') }}" class="ql-panel ql-filter">
            @if(empty($sourceOptions))
                <div class="ql-note">No lead source access assigned. Ask an admin to assign allowed sources for this Ad Manager.</div>
            @else
            <label>Source
                <select name="source" class="ql-input" data-source-select>
                    @foreach($sourceOptions as $sourceKey => $sourceName)
                        <option value="{{ $sourceKey }}" @selected($filters['source'] === $sourceKey)>{{ $sourceName }}</option>
                    @endforeach
                </select>
            </label>
            @endif
            @php
                $selectedFormIds = collect($filters['fb_form_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
                $selectedCampaignIds = collect($filters['campaign_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
            @endphp
            <label data-meta-view-wrap style="{{ $isMetaSource ? '' : 'display:none' }}">Meta View
                <select name="meta_view" class="ql-input" data-meta-view-select>
                    @foreach($metaViewOptions as $metaViewKey => $metaViewLabel)
                        <option value="{{ $metaViewKey }}" @selected($filters['meta_view'] === $metaViewKey)>{{ $metaViewLabel }}</option>
                    @endforeach
                </select>
            </label>
            <div class="ql-meta-controls" data-meta-controls style="{{ $isMetaSource ? '' : 'display:none' }}">
                <div class="ql-check-group" data-meta-panel="form" style="{{ $isMetaSource && $filters['meta_view'] === 'form' ? '' : 'display:none' }}">
                    <div class="ql-check-head">
                        <div>
                            <div class="ql-check-title">Meta Forms</div>
                            <span class="ql-help" data-selected-count>All Forms selected</span>
                        </div>
                        <div class="ql-check-actions">
                            <button type="button" class="ql-mini-btn" data-check-all>Select All</button>
                            <button type="button" class="ql-mini-btn" data-check-clear>Clear</button>
                        </div>
                    </div>
                    <input type="search" class="ql-check-search" placeholder="Search form..." data-check-search>
                    <div class="ql-check-list">
                        @forelse($metaOptions['forms'] as $form)
                            <label class="ql-check-option">
                                <input type="checkbox" name="fb_form_ids[]" value="{{ $form['id'] }}" @checked(in_array((string) $form['id'], $selectedFormIds, true))>
                                <span>{{ $form['name'] }}</span>
                            </label>
                        @empty
                            <div class="ql-check-empty">No Meta forms found for selected period.</div>
                        @endforelse
                    </div>
                </div>
                <div class="ql-check-group" data-meta-panel="campaign" style="{{ $isMetaSource && $filters['meta_view'] === 'campaign' ? '' : 'display:none' }}">
                    <div class="ql-check-head">
                        <div>
                            <div class="ql-check-title">Campaigns</div>
                            <span class="ql-help" data-selected-count>All Campaigns selected</span>
                        </div>
                        <div class="ql-check-actions">
                            <button type="button" class="ql-mini-btn" data-check-all>Select All</button>
                            <button type="button" class="ql-mini-btn" data-check-clear>Clear</button>
                        </div>
                    </div>
                    <input type="search" class="ql-check-search" placeholder="Search campaign..." data-check-search>
                    <div class="ql-check-list">
                        @forelse($metaOptions['campaigns'] as $campaign)
                            <label class="ql-check-option">
                                <input type="checkbox" name="campaign_ids[]" value="{{ $campaign['id'] }}" @checked(in_array((string) $campaign['id'], $selectedCampaignIds, true))>
                                <span>{{ $campaign['name'] }}</span>
                            </label>
                        @empty
                            <div class="ql-check-empty">No Meta campaigns found for selected period.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <label>Quick Period
                <select name="period" class="ql-input">
                    @foreach($periodOptions as $periodKey => $periodLabel)
                        <option value="{{ $periodKey }}" @selected($filters['period'] === $periodKey)>{{ $periodLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>Month
                <input type="month" name="month" value="{{ $filters['month'] }}" class="ql-input">
            </label>
            <label>From
                <input type="date" name="from" value="{{ request('from') ? $filters['from']->toDateString() : '' }}" class="ql-input">
            </label>
            <label>To
                <input type="date" name="to" value="{{ request('to') ? $filters['to']->toDateString() : '' }}" class="ql-input">
            </label>
            <label>User
                <select name="user_id" class="ql-input">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) $filters['user_id'] === (int) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Outcome Bucket
                <select name="bucket" class="ql-input">
                    <option value="">All Buckets</option>
                    @foreach($bucketOptions as $bucketKey => $bucketLabel)
                        <option value="{{ $bucketKey }}" @selected($filters['bucket'] === $bucketKey)>{{ $bucketLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>Evidence Rows
                <input type="number" name="sample_limit" min="5" max="100" value="{{ $filters['sample_limit'] }}" class="ql-input">
            </label>
            <button class="ql-btn primary" type="submit"><i class="fas fa-filter"></i> Generate Report</button>
            <a href="{{ route('ad-manager.dashboard', array_filter(['source' => array_key_first($sourceOptions) ?: null])) }}" class="ql-btn"><i class="fas fa-rotate-left"></i> Reset</a>
            <div class="ml-auto text-sm text-slate-500">Period: <b>{{ $filters['from']->format('d M Y') }}</b> to <b>{{ $filters['to']->format('d M Y') }}</b></div>
        </form>

        <div class="ql-grid meta">
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div>
                        <h2>Meta Health Status</h2>
                        <p>Token, mapping, webhook aur CRM handoff monitoring.</p>
                    </div>
                    <span class="am-pill {{ ($healthSnapshot['tone'] ?? 'green') === 'red' ? 'danger' : (($healthSnapshot['tone'] ?? 'green') === 'amber' ? 'warn' : 'success') }}">{{ $healthSnapshot['label'] ?? 'Healthy' }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="ql-note"><b>Pages</b><br>{{ number_format($healthSnapshot['connected_pages'] ?? 0) }}</div>
                    <div class="ql-note"><b>Enabled Forms</b><br>{{ number_format($healthSnapshot['enabled_forms'] ?? 0) }}</div>
                    <div class="ql-note"><b>Mapped</b><br>{{ number_format($healthSnapshot['mapped_enabled_forms'] ?? 0) }}</div>
                    <div class="ql-note"><b>Webhook</b><br>{{ ($healthSnapshot['webhook_endpoint_ok'] ?? false) ? 'OK' : 'Failed' }}</div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($metaHealthAlerts as $alert)
                        <span class="am-pill {{ $alert['tone'] }}">{{ $alert['label'] }}: {{ $alert['display'] }}</span>
                    @endforeach
                </div>
                <ul class="ql-list mt-4">
                    @foreach(($healthSnapshot['reasons'] ?? ['No current issue found.']) as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </section>

            <section class="ql-panel ql-section">
                <div class="am-attendance-card">
                    <div class="ql-section-head !mb-0">
                        <div>
                            <h2 class="!text-white">Self Attendance</h2>
                            <p class="sub">{{ data_get($attendanceToday, 'record.info_line', 'Attendance policy not configured.') }}</p>
                        </div>
                        <span id="attStatus" class="am-pill success">{{ data_get($attendanceToday, 'record.status_label', $attendanceConfigured ? 'Not Punched' : 'Not Configured') }}</span>
                    </div>
                    <div class="am-attendance-grid">
                        <div class="am-attendance-stat">
                            <div class="label">Punch In</div>
                            <div class="value" id="attIn">{{ data_get($attendanceRecord, 'first_punch_in_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'first_punch_in_at'))->format('d M, h:i A') : '--' }}</div>
                        </div>
                        <div class="am-attendance-stat">
                            <div class="label">Punch Out</div>
                            <div class="value" id="attOut">{{ data_get($attendanceRecord, 'last_punch_out_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'last_punch_out_at'))->format('d M, h:i A') : '--' }}</div>
                        </div>
                    </div>
                    <div class="am-actions">
                        <button id="attInBtn" type="button" class="primary" @disabled(!$attendanceConfigured || data_get($attendanceRecord, 'first_punch_in_at'))>Punch In</button>
                        <button id="attOutBtn" type="button" class="secondary" @disabled(!$attendanceConfigured || !data_get($attendanceRecord, 'first_punch_in_at') || data_get($attendanceRecord, 'last_punch_out_at'))>Punch Out</button>
                    </div>
                    <div id="attMsg" class="sub mt-3">{{ data_get($attendanceToday, 'record.info_line', 'Attendance policy not configured.') }}</div>
                </div>
            </section>
        </div>

        <div class="ql-grid kpi">
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Total Leads</div>
                <div class="ql-kpi-value">{{ number_format($summary['total']) }}</div>
                <div class="ql-kpi-sub">{{ $isAllSourceReport ? 'All source leads' : $sourceLabel . ' source leads' }}</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Interested</div>
                <div class="ql-kpi-value text-emerald-700">{{ number_format($summary['interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Not Interested</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['not_interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['not_interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Junk / Invalid</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['junk']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['junk']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">CNP</div>
                <div class="ql-kpi-value text-amber-700">{{ number_format($summary['cnp']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['cnp']) }}% not reachable</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Quality Score</div>
                <div class="ql-kpi-value text-emerald-800">{{ $summary['quality_score'] }}%</div>
                <div class="ql-kpi-sub">Interested / qualified only</div>
            </div>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-two">
                <div class="ql-score">
                    <div class="ql-ring" style="--score:{{ $summary['quality_score'] }}"><span>{{ $summary['quality_score'] }}%</span></div>
                    <div>
                        <h2>Lead Quality Summary</h2>
                        <p>Total {{ number_format($summary['total']) }} leads me se {{ number_format($summary['good_leads']) }} usable leads hain, aur {{ number_format($summary['poor_leads']) }} leads vendor review/replacement bucket me aate hain.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="ql-badge good">Usable {{ $summary['quality_score'] }}%</span>
                            <span class="ql-badge bad">Poor {{ $summary['poor_quality_rate'] }}%</span>
                            <span class="ql-badge info">Actioned {{ $summary['actioned_rate'] }}%</span>
                        </div>
                    </div>
                </div>
                <div class="ql-note">
                    <b>Vendor note:</b> This report is generated from CRM call outcomes submitted by users. {{ $isAllSourceReport ? 'Leads' : $sourceLabel . ' leads' }} marked Not Interested, Junk/Invalid and repeated CNP should be treated as low-quality enquiries and reviewed for replacement or credit adjustment.
                </div>
            </div>
        </section>

        @if($isAllSourceReport)
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Source Wise Quality</h2><p>Interested % sirf Interested / Qualified leads par based hai; CNP, call later, junk aur not interested exclude hain.</p></div>
                    <div class="ql-funnel-legend">
                        <span><i class="ql-dot raw"></i> Raw outcome</span>
                        <span><i class="ql-dot pipe"></i> Interested pipeline</span>
                        <span><i class="ql-dot bad"></i> Low quality</span>
                    </div>
                </div>
                <div class="ql-table-scroll">
                    <table class="ql-table funnel">
                        <thead>
                            <tr class="ql-funnel-group">
                                <th class="ql-funnel-source" rowspan="2">Source</th>
                                <th rowspan="2">Total</th>
                                <th colspan="2" class="pipe">Qualified</th>
                                <th colspan="4" class="raw">Raw Outcome</th>
                                <th colspan="5" class="pipe">Interested Pipeline</th>
                            </tr>
                            <tr>
                                <th>Interested</th><th>Rate</th><th>CNP</th><th>Call Later</th><th>Junk</th><th>Not Interested</th><th>Interested Follow-up</th><th>Site Visit</th><th>Visit Follow-up</th><th>Meeting</th><th>Closed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sourceBreakdown as $row)
                                <tr>
                                    <td class="ql-funnel-source"><b>{{ $row['source'] }}</b></td>
                                    <td><span class="ql-count neutral">{{ number_format($row['total']) }}</span></td>
                                    <td><span class="ql-count good">{{ number_format($row['interested']) }}</span></td>
                                    <td><span class="ql-badge {{ $row['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $row['interested_rate'] }}%</span></td>
                                    <td><span class="ql-count warn">{{ number_format($row['cnp']) }}</span></td>
                                    <td><span class="ql-count info">{{ number_format($row['call_later']) }}</span></td>
                                    <td><span class="ql-count bad">{{ number_format($row['junk']) }}</span></td>
                                    <td><span class="ql-count bad">{{ number_format($row['not_interested']) }}</span></td>
                                    <td><span class="ql-count info">{{ number_format($row['interested_follow_up']) }}</span></td>
                                    <td><span class="ql-count good">{{ number_format($row['site_visit']) }}</span></td>
                                    <td><span class="ql-count info">{{ number_format($row['site_visit_followup']) }}</span></td>
                                    <td><span class="ql-count good">{{ number_format($row['meeting']) }}</span></td>
                                    <td><span class="ql-count good">{{ number_format($row['closed']) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="text-center text-slate-500">No source-wise data found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($sourceBreakdown->isNotEmpty())
                            <tfoot>
                                <tr class="ql-funnel-total">
                                    <td class="ql-funnel-source">Total</td>
                                    <td>{{ number_format($sourceTotals['total']) }}</td>
                                    <td>{{ number_format($sourceTotals['interested']) }}</td>
                                    <td><span class="ql-badge {{ $sourceTotals['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $sourceTotals['interested_rate'] }}%</span></td>
                                    <td>{{ number_format($sourceTotals['cnp']) }}</td>
                                    <td>{{ number_format($sourceTotals['call_later']) }}</td>
                                    <td>{{ number_format($sourceTotals['junk']) }}</td>
                                    <td>{{ number_format($sourceTotals['not_interested']) }}</td>
                                    <td>{{ number_format($sourceTotals['interested_follow_up']) }}</td>
                                    <td>{{ number_format($sourceTotals['site_visit']) }}</td>
                                    <td>{{ number_format($sourceTotals['site_visit_followup']) }}</td>
                                    <td>{{ number_format($sourceTotals['meeting']) }}</td>
                                    <td>{{ number_format($sourceTotals['closed']) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </section>
        @endif


