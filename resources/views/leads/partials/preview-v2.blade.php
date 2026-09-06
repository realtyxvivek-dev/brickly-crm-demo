@php
    $previewOwner = $lead->activeAssignments->first()?->assignedTo;
    $previewLatestActivity = $timelineItems->first();
    $previewLastContact = data_get($leadCallSummary, 'last_call_at') ?: $lead->last_contacted_at;
    $previewCreatedAt = $leadDisplayCreatedAt ?: $lead->created_at;
    $previewLatestRemark = trim((string) (data_get($previewLatestActivity, 'description') ?: $displayLeadNotes));
    $previewAssignments = collect($lead->assignments ?? [])->sortByDesc(fn ($assignment) => optional($assignment->created_at)->timestamp ?? 0);
    $previewLatestProspect = collect($lead->prospects ?? [])->sortByDesc(fn ($prospect) => optional($prospect->updated_at)->timestamp ?? 0)->first();
    $previewValue = fn ($value) => filled($value) ? $value : 'Not provided';
    $previewRequirementRows = collect([
        ['label' => 'Category', 'value' => $lead->category],
        ['label' => 'Property Type', 'value' => $lead->property_type ?: $lead->type],
        ['label' => 'Preferred Location', 'value' => $lead->preferred_location ?: $lead->location],
        ['label' => 'Budget', 'value' => $lead->budget],
        ['label' => 'Purpose', 'value' => $lead->purpose],
        ['label' => 'Possession', 'value' => $lead->possession_status ?: data_get($previewLatestProspect, 'possession')],
        ['label' => 'Lead Quality', 'value' => $lead->lead_quality],
        ['label' => 'Interested Projects', 'value' => $leadInterestedProjects->implode(', ')],
    ]);
    $previewProfileRows = collect([
        ['label' => 'Customer Job', 'value' => data_get($previewLatestProspect, 'customer_job') ?: $lead->customer_job],
        ['label' => 'Industry / Sector', 'value' => data_get($previewLatestProspect, 'industry_sector') ?: $lead->industry_sector],
        ['label' => 'Buying Frequency', 'value' => data_get($previewLatestProspect, 'buying_frequency') ?: $lead->buying_frequency],
        ['label' => 'Living City', 'value' => data_get($previewLatestProspect, 'living_city') ?: $lead->living_city],
        ['label' => 'City Type', 'value' => data_get($previewLatestProspect, 'city_type') ?: $lead->city_type],
    ]);
    $previewDocuments = collect();
    $appendPreviewDocuments = function ($records, string $recordLabel, array $fields) use (&$previewDocuments) {
        foreach (collect($records) as $record) {
            foreach ($fields as $field => $label) {
                foreach ((array) data_get($record, $field, []) as $path) {
                    if (!is_string($path) || trim($path) === '') continue;
                    $url = filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . ltrim($path, '/'));
                    $previewDocuments->push([
                        'label' => $label,
                        'record' => $recordLabel . ' #' . $record->id,
                        'url' => $url,
                        'name' => basename(parse_url($path, PHP_URL_PATH) ?: $path),
                        'date' => $record->updated_at,
                    ]);
                }
            }
        }
    };
    $appendPreviewDocuments($lead->siteVisits, 'Site Visit', [
        'photos' => 'Visit Photo',
        'completion_proof_photos' => 'Visit Completion Proof',
        'closer_request_proof_photos' => 'Closer Request Proof',
        'kyc_documents' => 'KYC Document',
    ]);
    $appendPreviewDocuments($lead->meetings, 'Meeting', [
        'photos' => 'Meeting Photo',
        'completion_proof_photos' => 'Meeting Completion Proof',
    ]);
    $previewAuditItems = $timelineItems->filter(function ($item) {
        $type = strtolower((string) data_get($item, 'type'));
        $title = strtolower((string) data_get($item, 'title'));
        return str_contains($type, 'assign') || str_contains($type, 'transfer') || str_contains($type, 'reopen')
            || str_contains($type, 'status') || str_contains($title, 'assign') || str_contains($title, 'transfer')
            || str_contains($title, 'reopen') || str_contains($title, 'status') || data_get($item, 'metadata.automation');
    });
    $previewUserNames = $previewAssignments->reduce(function ($names, $assignment) {
        if ($assignment->assigned_to && $assignment->assignedTo?->name) $names[(int) $assignment->assigned_to] = $assignment->assignedTo->name;
        if ($assignment->assigned_by && $assignment->assignedBy?->name) $names[(int) $assignment->assigned_by] = $assignment->assignedBy->name;
        return $names;
    }, []);
    $previewReadableActivity = function ($item) use ($previewUserNames, $previewValue) {
        $title = $previewValue(data_get($item, 'title'));
        $description = $previewValue(data_get($item, 'description'));
        $title = preg_match('/[_-]/', $title) ? \Illuminate\Support\Str::headline($title) : $title;
        $description = preg_replace_callback('/user\s+#(\d+)/i', fn ($match) => $previewUserNames[(int) $match[1]] ?? 'User #' . $match[1], $description);
        $description = str_ireplace([
            'acknowledged via task_status_changed',
            'acknowledged via telecaller_task_status_changed',
        ], [
            'accepted after the assigned task was updated',
            'accepted after the calling task was updated',
        ], $description);
        $description = preg_replace_callback('/\b([a-z]+(?:_[a-z]+)+)\b/i', fn ($match) => strtolower(\Illuminate\Support\Str::headline($match[1])), $description);
        return ['title' => $title, 'description' => $description];
    };
@endphp

<div class="lead-v2" data-lead-v2>
    <header class="lead-v2-header">
        <div class="lead-v2-identity">
            <div class="lead-v2-avatar">{{ strtoupper(substr($lead->name ?: 'L', 0, 1)) }}</div>
            <div class="lead-v2-title">
                <div class="lead-v2-eyebrow">Lead #{{ $lead->id }} <span>Preview</span></div>
                <h1>{{ $previewValue($lead->name) }}</h1>
                <div class="lead-v2-meta">
                    <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}"><i class="fas fa-phone-alt"></i>{{ $previewValue($lead->phone) }}</a>
                    <span><i class="fas fa-bullseye"></i>{{ $previewValue($leadSourceLabel) }}</span>
                    <span><i class="fas fa-user"></i>{{ $previewOwner?->name ?: 'Unassigned' }}</span>
                </div>
            </div>
        </div>
        <div class="lead-v2-header-actions">
            <span class="lead-v2-status">{{ $leadStatusLabel }}</span>
            <button type="button" class="lead-v2-icon-button lead-favorite-btn {{ $isLeadFavorite ? 'is-favorite' : '' }}" data-lead-favorite-btn data-favorite-state="{{ $isLeadFavorite ? '1' : '0' }}" onclick="toggleLeadDetailFavorite({{ $lead->id }})" aria-label="{{ $isLeadFavorite ? 'Remove from favorite leads' : 'Add to favorite leads' }}" title="{{ $isLeadFavorite ? 'Remove from favorites' : 'Add to favorites' }}"><i class="{{ $isLeadFavorite ? 'fas' : 'far' }} fa-heart"></i></button>
            <a href="{{ route('leads.show', ['lead' => $lead->id, 'back' => $backUrl]) }}" class="lead-v2-classic"><i class="fas fa-arrow-left"></i>Back to Classic</a>
        </div>
    </header>

    <section class="lead-v2-summary" aria-label="Lead summary">
        <div><span>Current Stage</span><strong>{{ $leadStatusLabel }}</strong></div>
        <div><span>Last Contact</span><strong>{{ $previewLastContact ? optional($previewLastContact)->format('d M Y, h:i A') : 'Not provided' }}</strong></div>
        <div><span>Next Action</span><strong>{{ $nextAsmTask?->title ?: 'No open action' }}</strong><small>{{ optional($nextAsmTask?->scheduled_at)->format('d M Y, h:i A') ?: 'Not scheduled' }}</small></div>
        <div><span>Lead Age</span><strong>{{ $previewCreatedAt ? optional($previewCreatedAt)->diffForHumans(null, true) : 'Not provided' }}</strong></div>
        <div class="lead-v2-summary-remark">
            <span>Latest Remark</span>
            <strong title="{{ $previewLatestRemark !== '' ? $previewLatestRemark : 'Not provided' }}">{{ $previewLatestRemark !== '' ? $previewLatestRemark : 'Not provided' }}</strong>
            @if(mb_strlen($previewLatestRemark) > 110)
            <button type="button" class="lead-v2-remark-open" onclick="document.getElementById('lead-v2-remark-dialog').showModal()">View full</button>
            @endif
        </div>
    </section>

    <section class="lead-v2-actionbar" aria-label="Lead actions">
        <div class="lead-v2-primary-actions">
            <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="lead-v2-primary"><i class="fas fa-phone-alt"></i>Call</a>
            <a href="{{ $leadWhatsAppUrl ?: 'javascript:void(0)' }}" target="_blank" data-whatsapp-url="{{ $leadWhatsAppUrl }}" data-whatsapp-business-url="{{ $leadWhatsAppBusinessUrl }}" data-whatsapp-phone="{{ $leadWhatsAppPhone }}" onclick="return handleLeadWhatsAppClick(event, this)" class="lead-v2-action"><i class="fab fa-whatsapp"></i>WhatsApp</a>
            <button type="button" onclick="openFollowupModal()" class="lead-v2-action"><i class="fas fa-calendar-check"></i>Follow-up</button>
            <button type="button" onclick="openScheduleCallTaskModal()" class="lead-v2-action"><i class="fas fa-calendar-plus"></i>Schedule</button>
        </div>
        <div class="lead-v2-more-wrap">
            <button type="button" class="lead-v2-more-trigger" data-lead-v2-more aria-expanded="false"><i class="fas fa-ellipsis-h"></i>More Actions<i class="fas fa-chevron-down"></i></button>
            <div class="lead-v2-more-menu" data-lead-v2-menu hidden>
                <button type="button" onclick="openMeetingModal()"><i class="fas fa-handshake"></i>Meeting</button>
                <button type="button" onclick="openSiteVisitModal()"><i class="fas fa-map-marker-alt"></i>Site Visit</button>
                @if(isset($ownerTransferUsers) && $ownerTransferUsers->isNotEmpty())
                <button type="button" onclick="openOwnerTransferModal()"><i class="fas fa-user-edit"></i>Change Owner</button>
                @endif
                <button type="button" id="markCloserDraftBtn" onclick="markLeadAsCloserDraft()"><i class="fas fa-file-signature"></i>Mark as Closer</button>
                @include('leads.partials.reopen')
                <button type="button" onclick="openLeadRequirementsModal({{ $lead->id }})"><i class="fas fa-edit"></i>Edit Requirements</button>
                <form method="POST" action="{{ route('leads.destroy', $lead->id) }}" onsubmit="return confirm('Remove this lead from the list? It will be moved to trash and can be recovered.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="lead-v2-danger"><i class="fas fa-trash-alt"></i>Delete Lead</button>
                </form>
            </div>
        </div>
    </section>

    <nav class="lead-v2-tabs" role="tablist" aria-label="Lead detail sections">
        @foreach(['overview' => 'Overview', 'activities' => 'Activities', 'requirements' => 'Requirements', 'proposals' => 'Proposals', 'documents' => 'Documents', 'audit' => 'Audit'] as $tabKey => $tabLabel)
        <button type="button" role="tab" data-lead-v2-tab="{{ $tabKey }}" aria-controls="lead-v2-panel-{{ $tabKey }}" aria-selected="{{ $tabKey === 'overview' ? 'true' : 'false' }}" class="{{ $tabKey === 'overview' ? 'is-active' : '' }}">{{ $tabLabel }}</button>
        @endforeach
    </nav>

    <main class="lead-v2-content">
        <section id="lead-v2-panel-overview" class="lead-v2-panel is-active" data-lead-v2-panel="overview" role="tabpanel">
            <div class="lead-v2-overview-grid">
                <div class="lead-v2-overview-main">
                    <section class="lead-v2-section">
                        <div class="lead-v2-section-head"><div><span>Lead Information</span><h2>Contact & Assignment</h2></div></div>
                        <dl class="lead-v2-data-grid">
                            <div><dt>Customer Name</dt><dd>{{ $previewValue($lead->name) }}</dd></div>
                            <div><dt>Phone</dt><dd>{{ $previewValue($lead->phone) }}</dd></div>
                            <div><dt>Email</dt><dd>{{ $previewValue($lead->email) }}</dd></div>
                            <div><dt>Source</dt><dd>{{ $previewValue($leadSourceLabel) }}</dd></div>
                            <div><dt>Current Owner</dt><dd>{{ $previewOwner?->name ?: 'Unassigned' }}</dd></div>
                            <div><dt>Created On</dt><dd>{{ optional($previewCreatedAt)->format('d M Y, h:i A') ?: 'Not provided' }}</dd></div>
                            <div class="lead-v2-wide"><dt>Address</dt><dd>{{ $previewValue(collect([$lead->address, $lead->city, $lead->state, $lead->pincode])->filter()->implode(', ')) }}</dd></div>
                        </dl>
                    </section>
                    <section class="lead-v2-section">
                        <div class="lead-v2-section-head"><div><span>Customer Profiling</span><h2>Profile Snapshot</h2></div></div>
                        <dl class="lead-v2-data-grid">
                            @foreach($previewProfileRows as $row)<div><dt>{{ $row['label'] }}</dt><dd>{{ $previewValue($row['value']) }}</dd></div>@endforeach
                        </dl>
                    </section>
                    <section class="lead-v2-section">
                        <div class="lead-v2-section-head"><div><span>Latest Sales Update</span><h2>Current Context</h2></div></div>
                        <dl class="lead-v2-data-grid">
                            <div><dt>Handled By</dt><dd>{{ data_get($previewLatestActivity, 'user.name') ?: ($previewOwner?->name ?: 'Not provided') }}</dd></div>
                            <div><dt>Activity</dt><dd>{{ $previewValue(data_get($previewLatestActivity, 'title')) }}</dd></div>
                            <div class="lead-v2-wide"><dt>Remark</dt><dd>{{ $previewLatestRemark !== '' ? $previewLatestRemark : 'Not provided' }}</dd></div>
                        </dl>
                    </section>
                </div>
                <aside class="lead-v2-next-panel">
                    <span>Next Action</span>
                    <h2>{{ $nextAsmTask?->title ?: 'No open action' }}</h2>
                    <p>{{ $nextAsmTask?->description ?: $nextAsmTask?->notes ?: 'No pending task is currently scheduled for this lead.' }}</p>
                    <dl>
                        <div><dt>When</dt><dd>{{ optional($nextAsmTask?->scheduled_at)->format('d M Y, h:i A') ?: 'Not scheduled' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $nextAsmTask ? ucfirst($nextAsmTask->status ?: 'pending') : 'Not applicable' }}</dd></div>
                    </dl>
                    <button type="button" onclick="openScheduleCallTaskModal()"><i class="fas fa-calendar-plus"></i>Schedule an Action</button>
                </aside>
            </div>
        </section>

        <section id="lead-v2-panel-activities" class="lead-v2-panel" data-lead-v2-panel="activities" role="tabpanel" hidden>
            <div class="lead-v2-section-head"><div><span>Complete History</span><h2>Activity Timeline</h2></div><strong>{{ $timelineItems->count() }} records</strong></div>
            <div class="lead-v2-timeline">
                @forelse($timelineItems as $activity)
                @php
                    $previewIsSystem = data_get($activity, 'metadata.automation') || str_contains(strtolower((string) data_get($activity, 'type')), 'system');
                    $previewActivityCopy = $previewReadableActivity($activity);
                    $previewSubmittedForm = data_get($activity, 'metadata.submitted_form');
                @endphp
                <article class="{{ $previewIsSystem ? 'is-system' : '' }}">
                    <div class="lead-v2-timeline-icon" style="--event-color:{{ data_get($activity, 'color', '#17724c') }}"><i class="fas {{ data_get($activity, 'icon', 'fa-circle') }}"></i></div>
                    <div>
                        <time>{{ optional(data_get($activity, 'timestamp'))->format('d M Y, h:i A') ?: 'Time unavailable' }}</time>
                        <h3>{{ $previewActivityCopy['title'] }}</h3>
                        <p>{{ $previewActivityCopy['description'] }}</p>
                        @if(is_array($previewSubmittedForm) && (!empty($previewSubmittedForm['fields']) || !empty($previewSubmittedForm['files'])))
                        <button type="button" class="lead-submitted-form-trigger lead-v2-form-trigger" data-form-title="{{ $previewSubmittedForm['title'] ?? 'Submitted Form' }}" data-form-fields="{{ base64_encode(json_encode($previewSubmittedForm['fields'] ?? [])) }}" data-form-files="{{ base64_encode(json_encode($previewSubmittedForm['files'] ?? [])) }}">
                            <i class="fas fa-eye"></i><span>{{ data_get($activity, 'type') === 'site_visit_completed' ? 'View Visit Form' : 'View Details' }}</span>
                        </button>
                        @endif
                        <small>{{ data_get($activity, 'user.name') ? 'by ' . data_get($activity, 'user.name') : ($previewIsSystem ? 'System event' : 'User unavailable') }}</small>
                    </div>
                </article>
                @empty
                <div class="lead-v2-empty"><i class="fas fa-stream"></i><h3>No activities yet</h3><p>Activity history will appear here.</p></div>
                @endforelse
            </div>
        </section>

        <section id="lead-v2-panel-requirements" class="lead-v2-panel" data-lead-v2-panel="requirements" role="tabpanel" hidden>
            <div class="lead-v2-section-head"><div><span>Saved Requirement</span><h2>Customer Requirement</h2></div><button type="button" onclick="openLeadRequirementsModal({{ $lead->id }})"><i class="fas fa-edit"></i>Edit</button></div>
            <dl class="lead-v2-data-grid lead-v2-requirements-grid">
                @foreach($previewRequirementRows as $row)<div><dt>{{ $row['label'] }}</dt><dd>{{ $previewValue($row['value']) }}</dd></div>@endforeach
            </dl>
            <div class="lead-v2-note-block"><span>Other Requirements / Notes</span><p>{{ $previewValue($displayLeadNotes) }}</p></div>
        </section>

        <section id="lead-v2-panel-proposals" class="lead-v2-panel lead-v2-proposals" data-lead-v2-panel="proposals" role="tabpanel" hidden>
            @include('leads.partials.proposal-card')
        </section>

        <section id="lead-v2-panel-documents" class="lead-v2-panel" data-lead-v2-panel="documents" role="tabpanel" hidden>
            <div class="lead-v2-section-head"><div><span>Lead Files</span><h2>Documents & Proofs</h2></div><strong>{{ $previewDocuments->count() }} files</strong></div>
            <div class="lead-v2-document-list">
                @forelse($previewDocuments as $document)
                <a href="{{ $document['url'] }}" target="_blank" rel="noopener">
                    <i class="fas {{ str_ends_with(strtolower($document['name']), '.pdf') ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                    <span><strong>{{ $document['label'] }}</strong><small>{{ $document['record'] }} &middot; {{ optional($document['date'])->format('d M Y') ?: 'Date unavailable' }}</small><em>{{ $document['name'] }}</em></span>
                    <i class="fas fa-external-link-alt"></i>
                </a>
                @empty
                <div class="lead-v2-empty"><i class="fas fa-folder-open"></i><h3>No documents available</h3><p>Existing visit, meeting and KYC files will appear here.</p></div>
                @endforelse
            </div>
        </section>

        <section id="lead-v2-panel-audit" class="lead-v2-panel" data-lead-v2-panel="audit" role="tabpanel" hidden>
            <div class="lead-v2-section-head"><div><span>Admin Only</span><h2>Audit Trail</h2></div>@if(filled($internalAuditStage ?? null))<strong>{{ $internalAuditStage }}</strong>@endif</div>
            @if(collect($internalAuditRemarks ?? [])->isNotEmpty())
            <div class="lead-v2-audit-remarks">
                @foreach(collect($internalAuditRemarks) as $remark)<article><time>{{ optional($remark->edited_at)->format('d M Y, h:i A') ?: 'Time unavailable' }}</time><strong>{{ $remark->editor?->name ?? 'Lead Quality Auditor' }}</strong><p>{{ $remark->new_value }}</p></article>@endforeach
            </div>
            @endif
            <div class="lead-v2-timeline lead-v2-audit-timeline">
                @forelse($previewAuditItems as $activity)
                @php $previewActivityCopy = $previewReadableActivity($activity); @endphp
                <article class="is-system"><div class="lead-v2-timeline-icon" style="--event-color:{{ data_get($activity, 'color', '#526b61') }}"><i class="fas {{ data_get($activity, 'icon', 'fa-shield-alt') }}"></i></div><div><time>{{ optional(data_get($activity, 'timestamp'))->format('d M Y, h:i A') ?: 'Time unavailable' }}</time><h3>{{ $previewActivityCopy['title'] }}</h3><p>{{ $previewActivityCopy['description'] }}</p></div></article>
                @empty
                <div class="lead-v2-empty"><i class="fas fa-shield-alt"></i><h3>No audit events available</h3><p>Assignment, transfer, reopen and system events will appear here.</p></div>
                @endforelse
            </div>
        </section>
    </main>

    <div class="lead-v2-mobile-actions">
        <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}"><i class="fas fa-phone-alt"></i><span>Call</span></a>
        <a href="{{ $leadWhatsAppUrl ?: 'javascript:void(0)' }}" target="_blank" data-whatsapp-url="{{ $leadWhatsAppUrl }}" data-whatsapp-business-url="{{ $leadWhatsAppBusinessUrl }}" data-whatsapp-phone="{{ $leadWhatsAppPhone }}" onclick="return handleLeadWhatsAppClick(event, this)"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
        <button type="button" onclick="openFollowupModal()"><i class="fas fa-calendar-check"></i><span>Follow-up</span></button>
        <button type="button" onclick="openScheduleCallTaskModal()"><i class="fas fa-calendar-plus"></i><span>Schedule</span></button>
    </div>

    @if(mb_strlen($previewLatestRemark) > 110)
    <dialog id="lead-v2-remark-dialog" class="lead-v2-remark-dialog" onclick="if (event.target === this) this.close()">
        <div class="lead-v2-dialog-head"><div><span>Latest Remark</span><h2>Complete update</h2></div><button type="button" onclick="this.closest('dialog').close()" aria-label="Close latest remark"><i class="fas fa-times"></i></button></div>
        <p>{{ $previewLatestRemark }}</p>
        <div class="lead-v2-dialog-actions"><button type="button" onclick="this.closest('dialog').close()">Close</button></div>
    </dialog>
    @endif
</div>

@push('styles')
<style>
.lead-v2{--v2-green:#0b5137;--v2-green-2:#17724c;--v2-ink:#173d30;--v2-muted:#647970;--v2-line:#d8e4de;--v2-soft:#f2f7f4;color:var(--v2-ink);font-family:Inter,system-ui,sans-serif;letter-spacing:0}.lead-v2 *{box-sizing:border-box}.lead-v2-header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:24px 28px;background:#fff;border:1px solid var(--v2-line);border-radius:8px}.lead-v2-identity{display:flex;align-items:center;min-width:0;gap:16px}.lead-v2-avatar{width:58px;height:58px;display:grid;place-items:center;flex:none;border-radius:8px;background:var(--v2-green);color:#fff;font-size:24px;font-weight:800}.lead-v2-title{min-width:0}.lead-v2-eyebrow{font-size:11px;font-weight:800;text-transform:uppercase;color:var(--v2-muted)}.lead-v2-eyebrow span{display:inline-block;margin-left:6px;padding:2px 7px;border-radius:999px;background:#e9f4ee;color:var(--v2-green-2)}.lead-v2-title h1{margin:3px 0 7px;font-size:25px;line-height:1.2;font-weight:750;overflow-wrap:anywhere}.lead-v2-meta{display:flex;flex-wrap:wrap;gap:8px 16px;font-size:13px}.lead-v2-meta a,.lead-v2-meta span{display:inline-flex;align-items:center;gap:7px;color:#476359}.lead-v2-meta a{color:var(--v2-green);font-weight:650}.lead-v2-header-actions{display:flex;align-items:center;flex-wrap:wrap;justify-content:flex-end;gap:9px}.lead-v2-status{padding:7px 10px;border:1px solid #b9d7c6;border-radius:999px;background:#edf7f1;color:var(--v2-green);font-size:12px;font-weight:750}.lead-v2-icon-button,.lead-v2-classic{height:38px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #c8d8d0;border-radius:6px;background:#fff;color:var(--v2-ink)}.lead-v2-icon-button{width:38px}.lead-v2-classic{gap:8px;padding:0 13px;font-size:13px;font-weight:700}.lead-v2-summary{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) minmax(220px,1.6fr);margin-top:12px;border:1px solid var(--v2-line);border-radius:8px;background:#fff;overflow:hidden}.lead-v2-summary>div{min-width:0;padding:14px 16px;border-right:1px solid var(--v2-line)}.lead-v2-summary>div:last-child{border:0}.lead-v2-summary span,.lead-v2-next-panel>span,.lead-v2-note-block>span{display:block;margin-bottom:5px;color:var(--v2-muted);font-size:10px;font-weight:800;text-transform:uppercase}.lead-v2-summary strong{display:block;font-size:13px;line-height:1.4;overflow-wrap:anywhere}.lead-v2-summary small{display:block;margin-top:2px;color:var(--v2-muted);font-size:11px}.lead-v2-summary-remark strong{max-height:38px;overflow:hidden}.lead-v2-actionbar{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:12px;padding:11px 12px;border:1px solid var(--v2-line);border-radius:8px;background:#fff}.lead-v2-primary-actions{display:flex;flex-wrap:wrap;gap:8px}.lead-v2-actionbar a,.lead-v2-actionbar button{min-height:38px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 13px;border:1px solid #c5d8ce;border-radius:6px;background:#fff;color:var(--v2-ink);font-size:13px;font-weight:700;cursor:pointer}.lead-v2-actionbar .lead-v2-primary{background:var(--v2-green);border-color:var(--v2-green);color:#fff}.lead-v2-more-wrap{position:relative}.lead-v2-more-menu{position:absolute;right:0;top:calc(100% + 8px);z-index:45;width:220px;padding:6px;background:#fff;border:1px solid var(--v2-line);border-radius:7px;box-shadow:0 18px 45px rgba(15,45,33,.18)}.lead-v2-more-menu[hidden]{display:none}.lead-v2-more-menu button,.lead-v2-more-menu form{width:100%}.lead-v2-more-menu button{justify-content:flex-start;border:0;background:#fff}.lead-v2-more-menu button:hover{background:var(--v2-soft)}.lead-v2-more-menu #reopenLeadButton{color:var(--v2-ink);border:0;background:#fff}.lead-v2-more-menu form{margin-top:5px;padding-top:5px;border-top:1px solid var(--v2-line)}.lead-v2-more-menu .lead-v2-danger{color:#b42318}.lead-v2-tabs{position:sticky;top:0;z-index:30;display:flex;gap:2px;margin-top:18px;padding:0 12px;border:1px solid var(--v2-line);border-radius:8px 8px 0 0;background:#fff;overflow-x:auto}.lead-v2-tabs button{flex:none;padding:14px 15px;border:0;border-bottom:3px solid transparent;background:transparent;color:#657a71;font-size:13px;font-weight:700;cursor:pointer}.lead-v2-tabs button.is-active{border-bottom-color:var(--v2-green-2);color:var(--v2-green)}.lead-v2-content{min-height:480px;padding:24px;border:1px solid var(--v2-line);border-top:0;border-radius:0 0 8px 8px;background:#fff}.lead-v2-panel[hidden]{display:none}.lead-v2-overview-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px}.lead-v2-overview-main{min-width:0}.lead-v2-section{padding:0 0 24px;margin-bottom:24px;border-bottom:1px solid var(--v2-line)}.lead-v2-section:last-child{margin:0;padding:0;border:0}.lead-v2-section-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}.lead-v2-section-head span{display:block;color:var(--v2-green-2);font-size:10px;font-weight:800;text-transform:uppercase}.lead-v2-section-head h2{margin:2px 0 0;font-size:18px;font-weight:750}.lead-v2-section-head>strong{color:var(--v2-muted);font-size:12px}.lead-v2-section-head button{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #bed3c7;border-radius:6px;background:#fff;color:var(--v2-green);font-weight:700}.lead-v2-data-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin:0;border-top:1px solid var(--v2-line);border-left:1px solid var(--v2-line)}.lead-v2-data-grid>div{min-width:0;padding:12px 14px;border-right:1px solid var(--v2-line);border-bottom:1px solid var(--v2-line);background:#fbfdfc}.lead-v2-data-grid .lead-v2-wide{grid-column:1/-1}.lead-v2-data-grid dt{margin:0 0 5px;color:var(--v2-muted);font-size:10px;font-weight:800;text-transform:uppercase}.lead-v2-data-grid dd{margin:0;font-size:13px;font-weight:650;line-height:1.45;overflow-wrap:anywhere}.lead-v2-next-panel{position:sticky;top:68px;align-self:start;padding:20px;border:1px solid #bad7c6;border-radius:8px;background:#eff8f3}.lead-v2-next-panel h2{margin:5px 0 8px;font-size:19px}.lead-v2-next-panel p{margin:0 0 18px;color:#4c655b;font-size:13px;line-height:1.55}.lead-v2-next-panel dl{margin:0}.lead-v2-next-panel dl div{padding:10px 0;border-top:1px solid #cfe2d7}.lead-v2-next-panel dt{font-size:10px;color:var(--v2-muted);text-transform:uppercase;font-weight:800}.lead-v2-next-panel dd{margin:3px 0 0;font-size:13px;font-weight:700}.lead-v2-next-panel button{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;padding:10px;border:0;border-radius:6px;background:var(--v2-green);color:#fff;font-weight:750}.lead-v2-timeline{position:relative;max-width:980px}.lead-v2-timeline:before{content:"";position:absolute;left:17px;top:8px;bottom:8px;width:1px;background:var(--v2-line)}.lead-v2-timeline article{position:relative;display:grid;grid-template-columns:36px minmax(0,1fr);gap:14px;padding:0 0 22px}.lead-v2-timeline article.is-system{opacity:.72}.lead-v2-timeline-icon{z-index:1;width:35px;height:35px;display:grid;place-items:center;border:1px solid color-mix(in srgb,var(--event-color) 30%,white);border-radius:50%;background:color-mix(in srgb,var(--event-color) 10%,white);color:var(--event-color);font-size:12px}.lead-v2-timeline time{display:block;margin-bottom:3px;color:var(--v2-muted);font-size:11px}.lead-v2-timeline h3{margin:0;font-size:14px}.lead-v2-timeline p{margin:4px 0;color:#536a60;font-size:13px;line-height:1.5}.lead-v2-timeline small{color:#819189}.lead-v2-note-block{margin-top:18px;padding:14px;border-left:3px solid var(--v2-green-2);background:var(--v2-soft)}.lead-v2-note-block p{margin:0;font-size:13px;white-space:pre-wrap}.lead-v2-proposals>.bg-white{border:0!important;box-shadow:none!important;padding:0!important}.lead-v2-document-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.lead-v2-document-list>a{display:grid;grid-template-columns:34px minmax(0,1fr) 18px;align-items:center;gap:10px;padding:13px;border:1px solid var(--v2-line);border-radius:7px;color:var(--v2-ink)}.lead-v2-document-list>a>i:first-child{font-size:20px;color:var(--v2-green-2)}.lead-v2-document-list strong,.lead-v2-document-list small,.lead-v2-document-list em{display:block;overflow-wrap:anywhere}.lead-v2-document-list strong{font-size:13px}.lead-v2-document-list small,.lead-v2-document-list em{color:var(--v2-muted);font-size:11px;font-style:normal}.lead-v2-audit-remarks{display:grid;gap:8px;margin-bottom:20px}.lead-v2-audit-remarks article{padding:12px 14px;border-left:3px solid #c18a1b;background:#fff9e9}.lead-v2-audit-remarks time{float:right;color:var(--v2-muted);font-size:11px}.lead-v2-audit-remarks strong{font-size:12px}.lead-v2-audit-remarks p{margin:5px 0 0;font-size:13px;white-space:pre-wrap}.lead-v2-empty{grid-column:1/-1;padding:54px 20px;text-align:center;border:1px dashed #cbdad2;border-radius:7px;background:#fbfdfc}.lead-v2-empty>i{color:#9cafA6;font-size:28px}.lead-v2-empty h3{margin:10px 0 3px;font-size:15px}.lead-v2-empty p{margin:0;color:var(--v2-muted);font-size:13px}.lead-v2-mobile-actions{display:none}
@media(max-width:1100px){.lead-v2-summary{grid-template-columns:repeat(3,1fr)}.lead-v2-summary>div:nth-child(3){border-right:0}.lead-v2-summary>div:nth-child(n+4){border-top:1px solid var(--v2-line)}.lead-v2-summary-remark{grid-column:span 2}.lead-v2-overview-grid{grid-template-columns:minmax(0,1fr) 270px}}
@media(max-width:760px){.lead-detail-container:has(.lead-v2){padding-bottom:74px}.lead-v2-header{align-items:flex-start;padding:16px}.lead-v2-avatar{width:46px;height:46px;font-size:19px}.lead-v2-title h1{font-size:20px}.lead-v2-meta{display:grid;gap:5px}.lead-v2-header-actions{max-width:120px}.lead-v2-status{order:3}.lead-v2-classic{padding:0 9px;font-size:12px}.lead-v2-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.lead-v2-summary>div,.lead-v2-summary>div:nth-child(3){border-right:1px solid var(--v2-line);border-top:1px solid var(--v2-line)}.lead-v2-summary>div:nth-child(odd){border-left:0}.lead-v2-summary>div:nth-child(even){border-right:0}.lead-v2-summary-remark{grid-column:1/-1}.lead-v2-actionbar{display:none}.lead-v2-tabs{margin-top:12px}.lead-v2-content{padding:16px;min-height:440px}.lead-v2-overview-grid{grid-template-columns:1fr}.lead-v2-next-panel{position:static;grid-row:1}.lead-v2-data-grid,.lead-v2-document-list{grid-template-columns:1fr}.lead-v2-data-grid .lead-v2-wide{grid-column:auto}.lead-v2-mobile-actions{position:fixed;z-index:70;left:0;right:0;bottom:0;display:grid;grid-template-columns:repeat(4,1fr);padding:7px max(8px,env(safe-area-inset-right)) calc(7px + env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-left));border-top:1px solid var(--v2-line);background:#fff;box-shadow:0 -8px 24px rgba(20,58,43,.12)}.lead-v2-mobile-actions a,.lead-v2-mobile-actions button{display:grid;place-items:center;gap:3px;padding:4px;border:0;background:#fff;color:var(--v2-green);font-size:16px}.lead-v2-mobile-actions span{font-size:10px;font-weight:700}.lead-v2-more-menu{position:fixed;left:12px;right:12px;top:auto;bottom:72px;width:auto}.lead-v2-audit-remarks time{float:none;display:block;margin-bottom:4px}}
.lead-v2 .lead-v2-icon-button{width:38px!important;height:38px!important;min-width:38px!important;padding:0!important;border:1px solid #c8d8d0!important;border-radius:6px!important;background:#fff!important;color:var(--v2-green)!important;box-shadow:none!important}.lead-v2 .lead-v2-icon-button i{color:inherit!important}
@media(max-width:760px){.lead-detail-container:has(.lead-v2){padding-bottom:138px}.lead-v2-mobile-actions{z-index:110;bottom:64px;padding-bottom:7px;border-bottom:1px solid var(--v2-line)}.lead-v2-more-menu{bottom:128px}}
.lead-v2-header{padding:20px 24px}.lead-v2-identity{gap:14px}.lead-v2-avatar{width:52px;height:52px;font-size:22px}.lead-v2-summary-remark strong{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;max-height:38px}.lead-v2-remark-open{min-height:auto!important;margin-top:5px;padding:0!important;border:0!important;background:transparent!important;color:var(--v2-green-2)!important;font-size:11px!important;text-decoration:underline;text-underline-offset:2px}.lead-v2-more-menu{width:240px;min-width:240px;padding:7px}.lead-v2-more-menu button{width:100%;min-height:42px!important;justify-content:flex-start!important;gap:10px!important;padding:0 12px!important;white-space:nowrap}.lead-v2-more-menu button i{width:18px;text-align:center}.lead-v2-tabs button.is-active{background:#f2f7f4;border-bottom-color:var(--v2-green-2);color:var(--v2-green)}.lead-v2-audit-timeline{max-width:none}.lead-v2-remark-dialog{width:min(560px,calc(100vw - 32px));max-height:min(70vh,560px);padding:0;border:1px solid var(--v2-line);border-radius:8px;color:var(--v2-ink);box-shadow:0 24px 70px rgba(15,45,33,.24)}.lead-v2-remark-dialog::backdrop{background:rgba(8,32,23,.48)}.lead-v2-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid var(--v2-line)}.lead-v2-dialog-head span{display:block;color:var(--v2-green-2);font-size:10px;font-weight:800;text-transform:uppercase}.lead-v2-dialog-head h2{margin:2px 0 0;font-size:18px}.lead-v2-dialog-head button{width:38px;height:38px;display:grid;place-items:center;flex:none;border:1px solid var(--v2-line);border-radius:6px;background:#fff;color:var(--v2-ink)}.lead-v2-remark-dialog>p{max-height:340px;margin:0;padding:20px;overflow:auto;font-size:14px;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}.lead-v2-dialog-actions{display:flex;justify-content:flex-end;padding:12px 20px;border-top:1px solid var(--v2-line);background:var(--v2-soft)}.lead-v2-dialog-actions button{min-height:38px;padding:0 16px;border:1px solid var(--v2-green);border-radius:6px;background:var(--v2-green);color:#fff;font-weight:700}
@media(max-width:760px){.lead-v2-header{padding:16px}.lead-v2-more-menu{width:auto;min-width:0}.lead-v2-remark-dialog>p{max-height:55vh}}
.lead-v2 .lead-v2-remark-dialog{margin:auto}
.lead-v2-form-trigger{min-height:34px;display:inline-flex;align-items:center;gap:7px;margin:6px 10px 6px 0;padding:0 11px;border:1px solid #b9d7c6;border-radius:6px;background:#edf7f1;color:var(--v2-green);font-size:12px;font-weight:750;cursor:pointer}.lead-v2-form-trigger:hover{background:#e3f2e9}.lead-v2-form-trigger+small{display:inline-block}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-lead-v2]');
    if (!root) return;
    const tabs = Array.from(root.querySelectorAll('[data-lead-v2-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-lead-v2-panel]'));
    const allowed = tabs.map(tab => tab.dataset.leadV2Tab);
    const activate = (name, updateHash = true) => {
        const active = allowed.includes(name) ? name : 'overview';
        tabs.forEach(tab => { const selected = tab.dataset.leadV2Tab === active; tab.classList.toggle('is-active', selected); tab.setAttribute('aria-selected', selected ? 'true' : 'false'); });
        panels.forEach(panel => { const selected = panel.dataset.leadV2Panel === active; panel.classList.toggle('is-active', selected); panel.hidden = !selected; });
        if (updateHash && window.location.hash !== '#' + active) history.replaceState(null, '', window.location.pathname + window.location.search + '#' + active);
    };
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.leadV2Tab)));
    activate(window.location.hash.replace('#', ''), false);
    window.addEventListener('hashchange', () => activate(window.location.hash.replace('#', ''), false));

    const trigger = root.querySelector('[data-lead-v2-more]');
    const menu = root.querySelector('[data-lead-v2-menu]');
    const closeMenu = () => { if (!menu) return; menu.hidden = true; trigger?.setAttribute('aria-expanded', 'false'); };
    trigger?.addEventListener('click', event => { event.stopPropagation(); menu.hidden = !menu.hidden; trigger.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true'); });
    menu?.addEventListener('click', event => { if (event.target.closest('button')) setTimeout(closeMenu, 0); });
    document.addEventListener('click', event => { if (!event.target.closest('.lead-v2-more-wrap')) closeMenu(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
});
</script>
@endpush
