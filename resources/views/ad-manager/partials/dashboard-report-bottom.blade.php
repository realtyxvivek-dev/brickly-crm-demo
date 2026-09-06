        <div class="ql-two">
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Outcome Breakdown</h2><p>User submitted outcomes from telecalling/calling tasks.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Outcome</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($outcomes as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No outcome data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Lead List Status Breakdown</h2><p>Lead list jaisa operational status: CNP aur Call Later / Follow-up New se alag count hote hain.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Status</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($statusBreakdown as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar info"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No status data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Daily Quality Trend</h2><p>Day-wise received leads and low quality buckets.</p></div>
            </div>
            @php
                $maxTrendTotal = max(1, (int) $dailyTrend->max('total'));
            @endphp
            <div class="ql-trend-layout">
                <div class="ql-trend-card">
                    <div class="ql-trend-card-head"><h3>Daily Numbers</h3><span>{{ $dailyTrend->count() }} days</span></div>
                    <div class="ql-trend-table-wrap">
                        <table class="ql-table">
                            <thead><tr><th>Date</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Call Later</th><th>Pending</th></tr></thead>
                            <tbody>
                            @foreach($dailyTrend as $day)
                                <tr>
                                    <td>{{ $day['date']->format('d M Y') }}</td>
                                    <td><b>{{ $day['total'] }}</b></td>
                                    <td class="text-emerald-700">{{ $day['interested'] }}</td>
                                    <td class="text-red-700">{{ $day['not_interested'] }}</td>
                                    <td class="text-red-700">{{ $day['junk'] }}</td>
                                    <td class="text-amber-700">{{ $day['cnp'] }}</td>
                                    <td class="text-blue-700">{{ $day['call_later'] ?? $day['follow_up'] ?? 0 }}</td>
                                    <td class="text-slate-500">{{ $day['pending'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ql-trend-card">
                    <div class="ql-trend-card-head"><h3>Low Quality Trend</h3><span>Not Interested + Junk + CNP</span></div>
                    <div class="ql-trend-chart">
                        @foreach($dailyTrend as $day)
                            @php
                                $dayTotal = max(1, (int) $day['total']);
                                $trackWidth = max(2, round(((int) $day['total'] / $maxTrendTotal) * 100, 1));
                                $notInterestedWidth = round(((int) $day['not_interested'] / $dayTotal) * 100, 1);
                                $junkWidth = round(((int) $day['junk'] / $dayTotal) * 100, 1);
                                $cnpWidth = round(((int) $day['cnp'] / $dayTotal) * 100, 1);
                                $lowQualityTotal = (int) $day['not_interested'] + (int) $day['junk'] + (int) $day['cnp'];
                            @endphp
                            <div class="ql-trend-row">
                                <div class="ql-trend-date">{{ $day['date']->format('d M') }}</div>
                                <div class="ql-trend-track" style="width:{{ $trackWidth }}%">
                                    <span class="ql-trend-segment not-interested" style="width:{{ $notInterestedWidth }}%"></span>
                                    <span class="ql-trend-segment junk" style="width:{{ $junkWidth }}%"></span>
                                    <span class="ql-trend-segment cnp" style="width:{{ $cnpWidth }}%"></span>
                                </div>
                                <div class="ql-trend-total">{{ $lowQualityTotal }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="ql-trend-legend">
                        <span><i style="background:#d9534f"></i>Not Interested</span>
                        <span><i style="background:#f4b183"></i>Junk</span>
                        <span><i style="background:#ffd966"></i>CNP</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>User Outcome Performance</h2><p>Assigned user wise lead quality summary.</p></div>
            </div>
            <table class="ql-table">
                <thead><tr><th>User</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Call Later</th><th>Quality Score</th></tr></thead>
                <tbody>
                @forelse($ownerBreakdown as $owner)
                    <tr>
                        <td><b>{{ $owner['owner'] }}</b></td>
                        <td>{{ $owner['total'] }}</td>
                        <td class="text-emerald-700">{{ $owner['interested'] }}</td>
                        <td class="text-red-700">{{ $owner['not_interested'] }}</td>
                        <td class="text-red-700">{{ $owner['junk'] }}</td>
                        <td class="text-amber-700">{{ $owner['cnp'] }}</td>
                        <td class="text-blue-700">{{ $owner['call_later'] }}</td>
                        <td><span class="ql-badge {{ $owner['quality_score'] >= 50 ? 'good' : 'warn' }}">{{ $owner['quality_score'] }}%</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">No assigned-user data found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @if($isMetaSource && in_array($filters['meta_view'], ['form', 'campaign'], true))
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div>
                        <h2>{{ $filters['meta_view'] === 'form' ? 'Meta Form Quality Breakdown' : 'Meta Campaign Quality Breakdown' }}</h2>
                        <p>{{ $filters['meta_view'] === 'form' ? 'Form wise Meta lead quality based on CRM user outcomes.' : 'Campaign wise Meta lead quality based on CRM user outcomes.' }}</p>
                    </div>
                </div>
                <table class="ql-table">
                    <thead>
                        <tr><th>{{ $filters['meta_view'] === 'form' ? 'Form Name' : 'Campaign Name' }}</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Call Later</th><th>Pending</th><th>Quality Score</th></tr>
                    </thead>
                    <tbody>
                    @forelse($metaBreakdown as $row)
                        <tr>
                            <td><b>{{ $row['label'] }}</b></td>
                            <td>{{ number_format($row['total']) }}</td>
                            <td class="text-emerald-700">{{ number_format($row['interested']) }}</td>
                            <td class="text-red-700">{{ number_format($row['not_interested']) }}</td>
                            <td class="text-red-700">{{ number_format($row['junk']) }}</td>
                            <td class="text-amber-700">{{ number_format($row['cnp']) }}</td>
                            <td class="text-blue-700">{{ number_format($row['call_later'] ?? $row['follow_up'] ?? 0) }}</td>
                            <td class="text-slate-500">{{ number_format($row['pending']) }}</td>
                            <td><span class="ql-badge {{ $row['quality_score'] >= 50 ? 'good' : 'warn' }}">{{ $row['quality_score'] }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-slate-500">No Meta breakdown data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Evidence Rows</h2><p>Filtered rows for vendor review and internal quality checks.</p></div>
                <span class="ql-badge muted">{{ $evidenceRows->count() }} rows</span>
            </div>
            <table class="ql-table">
                <thead><tr><th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Bucket</th><th>Outcome</th>@if($isMetaSource)<th>Meta Context</th>@endif<th>Remark / Evidence</th></tr></thead>
                <tbody>
                @forelse($evidenceRows as $row)
                    @php
                        $lead = $row['lead'];
                    @endphp
                    <tr>
                        <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                        <td>{{ $lead->phone }}</td>
                        <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $row['owner'] }}</td>
                        <td>{{ $row['status_label'] }}</td>
                        <td><span class="ql-badge {{ $bucketClass[$row['bucket']] ?? 'muted' }}">{{ $row['bucket_label'] }}</span></td>
                        <td>{{ $row['outcome_label'] }}</td>
                        @if($isMetaSource)
                            <td>
                                <b>{{ $row['meta_form_name'] }}</b>
                                <div class="text-xs text-slate-500">{{ $row['meta_campaign_name'] }}</div>
                                @if($row['meta_adset_name'] || $row['meta_ad_name'])
                                    <div class="text-xs text-slate-500">{{ $row['meta_adset_name'] }}{{ $row['meta_adset_name'] && $row['meta_ad_name'] ? ' / ' : '' }}{{ $row['meta_ad_name'] }}</div>
                                @endif
                            </td>
                        @endif
                        <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isMetaSource ? 9 : 8 }}" class="text-center text-slate-500">No leads found for selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @foreach([
            'interested' => 'Interested / Qualified Evidence',
            'not_interested' => 'Not Interested Evidence',
            'junk' => 'Junk / Invalid Evidence',
            'cnp' => 'CNP / Not Reachable Evidence',
        ] as $bucket => $title)
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>{{ $title }}</h2><p>Sample rows for vendor review. Increase evidence rows filter if more examples are needed.</p></div>
                    <span class="ql-badge {{ $bucketClass[$bucket] ?? 'muted' }}">{{ $samples[$bucket]->count() }} rows</span>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Outcome</th><th>Remark / Evidence</th></tr></thead>
                    <tbody>
                    @forelse($samples[$bucket] as $row)
                        @php
                            $lead = $row['lead'];
                        @endphp
                        <tr>
                            <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                            <td>{{ $lead->phone }}</td>
                            <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $row['owner'] }}</td>
                            <td>{{ $row['status_label'] }}</td>
                            <td><span class="ql-badge {{ $bucketClass[$row['bucket']] ?? 'muted' }}">{{ $row['outcome_label'] }}</span></td>
                            <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-slate-500">No rows in this bucket.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Recommendation for {{ $sourceLabel }}</h2><p>Points that can be shared with vendor for corrective action.</p></div>
            </div>
            <ul class="ql-list">
                @foreach($recommendations as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const metaSources = new Set(['meta', 'meta_awareness']);

    document.querySelectorAll('form.ql-filter').forEach((form) => {
        const sourceSelect = form.querySelector('[data-source-select]');
        const metaControls = form.querySelector('[data-meta-controls]');
        const metaViewWrap = form.querySelector('[data-meta-view-wrap]');
        const metaViewSelect = form.querySelector('[data-meta-view-select]');
        const panels = form.querySelectorAll('[data-meta-panel]');

        const updateCounts = () => {
            form.querySelectorAll('.ql-check-group').forEach((group) => {
                const checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
                const total = group.querySelectorAll('input[type="checkbox"]').length;
                const count = group.querySelector('[data-selected-count]');
                const title = group.querySelector('.ql-check-title')?.textContent?.trim() || 'Options';

                if (!count) {
                    return;
                }

                count.textContent = checked === 0
                    ? `All ${title.toLowerCase()} selected`
                    : `${checked} of ${total} selected`;
            });
        };

        const syncMetaControls = () => {
            const isMeta = metaSources.has(sourceSelect?.value || '');
            const view = metaViewSelect?.value || 'all';

            if (metaControls) {
                metaControls.style.display = isMeta ? 'block' : 'none';
            }

            if (metaViewWrap) {
                metaViewWrap.style.display = isMeta ? 'grid' : 'none';
            }

            panels.forEach((panel) => {
                panel.style.display = isMeta && panel.dataset.metaPanel === view ? 'block' : 'none';
            });

            updateCounts();
        };

        sourceSelect?.addEventListener('change', syncMetaControls);
        metaViewSelect?.addEventListener('change', syncMetaControls);

        form.querySelectorAll('[data-check-all]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.ql-check-group')?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = true;
                });
                updateCounts();
            });
        });

        form.querySelectorAll('[data-check-clear]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.ql-check-group')?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = false;
                });
                updateCounts();
            });
        });

        form.querySelectorAll('[data-check-search]').forEach((input) => {
            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                input.closest('.ql-check-group')?.querySelectorAll('.ql-check-option').forEach((option) => {
                    option.style.display = option.textContent.toLowerCase().includes(term) ? 'flex' : 'none';
                });
            });
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('.ql-check-option input')) {
                updateCounts();
            }
        });

        syncMetaControls();
    });
});
</script>


