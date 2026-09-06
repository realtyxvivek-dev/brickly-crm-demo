@php
    $user = $user ?? auth()->user();
    $proposalProjects = collect($proposalProjects ?? []);
    $leadProposals = collect($leadProposals ?? []);
    $leadProposalSummaries = collect($leadProposalSummaries ?? []);
    $leadProjectShareLinks = collect($leadProjectShareLinks ?? []);
    $canCreateProposal = (bool) (
        $user?->isAdmin()
        || $user?->isCrm()
        || $user?->isSalesHead()
        || $user?->isSalesManager()
        || $user?->isSeniorManager()
        || $user?->isAssistantSalesManager()
        || $user?->isSalesExecutive()
    );
    $canViewProposalAnalytics = (bool) (
        $user?->isAdmin()
        || $user?->isCrm()
        || $user?->isSalesHead()
        || $user?->isSalesManager()
        || $user?->isSeniorManager()
        || $user?->isAssistantSalesManager()
        || $user?->isSalesExecutive()
    );
    $proposalProjectOptions = $proposalProjects->map(function ($project) {
        $variants = $project->publicUnitTypes->flatMap->sizeVariants;
        $startingPrice = $variants->where('is_price_on_request', false)->whereNotNull('final_price')->sortBy('final_price')->first()?->formatted_final_price;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'location' => collect([$project->area, $project->city])->filter()->implode(', '),
            'price' => $startingPrice ?: 'Price on request',
        ];
    })->values();
    $formatProposalDuration = function ($milliseconds): string {
        $seconds = (int) floor(((int) $milliseconds) / 1000);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;
        if ($minutes < 60) {
            return $minutes . 'm ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 's';
        }
        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };
    $latestProposal = $leadProposals->first();
    $latestSummary = $latestProposal ? ($leadProposalSummaries->get($latestProposal->id, []) ?: []) : [];
    $shareLinkSummaries = $leadProjectShareLinks->mapWithKeys(function ($shareLink) {
        $events = collect($shareLink->events ?? []);
        $topAsset = $events
            ->map(fn ($event) => data_get($event->meta, 'asset_title') ?: data_get($event->meta, 'variant_label') ?: data_get($event->meta, 'unit_type'))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return [
            $shareLink->id => [
                'total_visits' => $events->pluck('session_id')->filter()->unique()->count() ?: max(0, (int) $shareLink->view_count),
                'total_duration_ms' => (int) $events->sum('duration_ms'),
                'top_project' => ['name' => $shareLink->project->name ?? 'Public project'],
                'top_unit' => $topAsset ?: 'Waiting',
                'suggestion' => 'Tracked public project page sent. Open tracking is attached to the public share link.',
            ],
        ];
    });
    $latestShareLink = $leadProjectShareLinks->sortByDesc(fn ($item) => optional($item->created_at)->timestamp ?? 0)->first();
    $latestProposalAt = optional($latestProposal?->created_at)->timestamp ?? 0;
    $latestShareAt = optional($latestShareLink?->created_at)->timestamp ?? 0;
    $latestTrackedType = $latestShareAt > $latestProposalAt ? 'project_share' : ($latestProposal ? 'proposal' : ($latestShareLink ? 'project_share' : null));
    $latestTracked = $latestTrackedType === 'project_share' ? $latestShareLink : $latestProposal;
    $latestTrackedSummary = $latestTrackedType === 'project_share'
        ? ($latestTracked ? ($shareLinkSummaries->get($latestTracked->id, []) ?: []) : [])
        : $latestSummary;
    $latestTrackedUrl = $latestTrackedType === 'project_share'
        ? ($latestTracked ? route('projects.public-share.show', $latestTracked->token) : null)
        : ($latestTracked ? route('lead-proposals.public.show', $latestTracked->token) : null);
    $latestTrackedCount = $leadProposals->count() + $leadProjectShareLinks->count();
@endphp

@push('styles')
<style>
    #leadProposalProjectPicker {
        align-items: start;
    }
    #leadProposalProjectPicker .proposal-project-option {
        display: flex !important;
        align-items: flex-start !important;
        width: 100% !important;
        min-height: 76px !important;
        height: auto !important;
        max-height: none !important;
        padding: 12px !important;
        overflow: visible !important;
        color: #0f172a !important;
    }
    #leadProposalProjectPicker .proposal-project-check {
        display: inline-grid !important;
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        max-width: 24px !important;
        min-height: 24px !important;
        max-height: 24px !important;
        border-radius: 999px !important;
        place-items: center !important;
        line-height: 1 !important;
    }
    #leadProposalProjectPicker .proposal-project-title,
    #leadProposalProjectPicker .proposal-project-location,
    #leadProposalProjectPicker .proposal-project-price {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        color: inherit;
    }
    #leadProposalProjectPicker .proposal-project-title {
        color: #020617 !important;
        font-size: 14px !important;
        line-height: 20px !important;
        font-weight: 700 !important;
    }
    #leadProposalProjectPicker .proposal-project-location {
        margin-top: 3px !important;
        color: #64748b !important;
        font-size: 12px !important;
        line-height: 16px !important;
        font-weight: 600 !important;
    }
    #leadProposalProjectPicker .proposal-project-price {
        margin-top: 8px !important;
        width: fit-content !important;
        color: #334155 !important;
        background: #f1f5f9 !important;
        border-radius: 999px !important;
        padding: 4px 10px !important;
        font-size: 11px !important;
        line-height: 1 !important;
        font-weight: 700 !important;
    }
</style>
@endpush

<div class="bg-white rounded-xl sm:rounded-2xl shadow-md border border-slate-200/80 p-4 sm:p-6 md:p-8 mb-4 sm:mb-6" id="leadProposalCard">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-blue-600 mb-1">WhatsApp Proposal</p>
            <h2 class="text-xl md:text-2xl font-bold text-slate-900">Send Project Proposal</h2>
            <p class="mt-1 text-sm text-slate-500">Single project sends use the live public project page. Multi-project sends use a private proposal link.</p>
        </div>
        <div class="hidden md:flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
            <i class="fab fa-whatsapp text-lg"></i>
        </div>
    </div>

    @if($canCreateProposal)
        @if($proposalProjects->isNotEmpty())
            <form id="leadProposalForm" class="space-y-4">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-semibold text-slate-700">Select projects</label>
                        <span id="leadProposalSelectedCount" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">0 selected</span>
                    </div>
                    <select id="leadProposalProjects" name="project_ids[]" multiple class="hidden">
                        @foreach($proposalProjects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}{{ $project->area ? ' - ' . $project->area : '' }}</option>
                        @endforeach
                    </select>
                    <div class="grid gap-2 sm:grid-cols-2" id="leadProposalProjectPicker">
                        @foreach($proposalProjects as $project)
                            @php
                                $variants = $project->publicUnitTypes->flatMap->sizeVariants;
                                $startingPrice = $variants->where('is_price_on_request', false)->whereNotNull('final_price')->sortBy('final_price')->first()?->formatted_final_price ?: 'Price on request';
                                $location = collect([$project->area, $project->city])->filter()->implode(', ');
                            @endphp
                            <button type="button" class="proposal-project-option group flex w-full items-start gap-3 rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:border-blue-300 hover:bg-blue-50" data-project-id="{{ $project->id }}" aria-pressed="false">
                                <span class="proposal-project-check mt-0.5 shrink-0 border border-slate-300 bg-white text-xs text-white transition">
                                    <i class="fas fa-check"></i>
                                </span>
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="proposal-project-title">{{ $project->name }}</span>
                                    <span class="proposal-project-location">{{ $location ?: 'Location on request' }}</span>
                                    <span class="proposal-project-price">{{ $startingPrice }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Tap projects to select multiple. Selected projects will be sent in one private proposal link.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Link expiry</label>
                    <select id="leadProposalExpiryMode" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="7d" selected>7 days</option>
                        <option value="24h">24 hours</option>
                        <option value="3d">3 days</option>
                        <option value="4d">4 days</option>
                        <option value="15d">15 days</option>
                        <option value="none">No expiry</option>
                        <option value="custom">Custom date/time</option>
                    </select>
                    <input id="leadProposalCustomExpiry" type="datetime-local" class="mt-3 hidden w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Lead prompt</label>
                    <select id="leadProposalCaptureMode" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="off" selected>Off - clean proposal page</option>
                        <option value="soft_prompt">Soft prompt - bottom WhatsApp reminder</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">No popup is shown by default. Soft prompt is a small bottom CTA only.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Message preview</label>
                    <textarea id="leadProposalMessage" rows="5" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
                </div>

                <button type="submit" id="leadProposalGenerateBtn" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                    <i class="fas fa-link"></i>
                    Generate Proposal Link
                </button>
            </form>

            <div id="leadProposalResult" class="mt-5 hidden rounded-2xl border border-blue-100 bg-blue-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Ready to send</p>
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-blue-700">Tracked link</span>
                </div>
                <input id="leadProposalUrlOutput" readonly class="mt-3 w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-900">
                <textarea id="leadProposalMessageOutput" readonly rows="4" class="mt-3 w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs text-blue-950"></textarea>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" id="leadProposalCopyBtn" class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100">
                        <i class="fas fa-copy"></i> Copy Message
                    </button>
                    <a id="leadProposalWhatsAppBtn" href="#" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">
                        <i class="fab fa-whatsapp"></i> Open WhatsApp
                    </a>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center">
                <p class="text-sm font-semibold text-slate-700">No published project public pages found.</p>
                <p class="mt-1 text-xs text-slate-500">Publish a project public page before sending proposals.</p>
            </div>
        @endif
    @else
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
            You can view proposal engagement, but you do not have permission to generate new proposal links.
        </div>
    @endif

    <div class="mt-7 border-t border-slate-200 pt-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Proposal Engagement</h3>
                <p class="text-xs text-slate-500">Compact latest summary. Open analytics for full detail.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $latestTrackedCount }} links</span>
        </div>

        @if($latestTracked)
            @php
                $topProject = $latestTrackedSummary['top_project'] ?? null;
                $statusClass = match((string) ($latestTracked->status ?? 'active')) {
                    'active' => 'bg-blue-50 text-blue-700 border-blue-100',
                    'expired' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'revoked' => 'bg-rose-50 text-rose-700 border-rose-100',
                    default => 'bg-rose-50 text-rose-700 border-rose-100',
                };
                $latestTitle = $latestTrackedType === 'project_share'
                    ? collect([$latestTracked->project->name ?? 'Public project'])
                    : $latestTracked->projects->pluck('name');
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.08em] {{ $statusClass }}">{{ $latestTracked->status }}</span>
                            <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $latestTrackedType === 'project_share' ? 'Public share' : 'Private proposal' }}</span>
                        </div>
                        <p class="mt-2 text-sm font-bold text-slate-900">
                            {{ $latestTitle->take(2)->implode(', ') }}{{ $latestTitle->count() > 2 ? ' +' . ($latestTitle->count() - 2) : '' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Created {{ $latestTracked->created_at->format('d M, h:i A') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $latestTrackedUrl }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50">
                            <i class="fas fa-external-link-alt"></i> Open
                        </a>
                        @if($canViewProposalAnalytics && $latestTrackedType === 'proposal')
                            <a href="{{ route('leads.proposals.analytics', [$lead, $latestTracked]) }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                                <i class="fas fa-chart-line"></i> View Analytics
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-3">
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Opens</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $latestTracked->view_count }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Visits</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $latestTrackedSummary['total_visits'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Last open</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $latestTracked->last_viewed_at ? $latestTracked->last_viewed_at->format('d M, h:i A') : 'Not opened' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Top project</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $topProject['name'] ?? 'Waiting' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Interested unit</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $latestTrackedSummary['top_unit'] ?? 'Waiting' }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Total time</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $formatProposalDuration($latestTrackedSummary['total_duration_ms'] ?? 0) }}</p>
                    </div>
                </div>

                <p class="mt-3 rounded-xl bg-blue-50 px-3 py-2 text-xs font-medium leading-5 text-blue-800">
                    {{ $latestTrackedSummary['suggestion'] ?? 'Proposal sent. Follow up after the customer opens the link.' }}
                </p>
            </div>

            @if($leadProposals->count() > 1)
                <div class="mt-3 space-y-2">
                    @foreach($leadProposals->skip(1)->take(4) as $proposal)
                        @php $summary = $leadProposalSummaries->get($proposal->id, []) ?: []; @endphp
                        <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $proposal->projects->pluck('name')->take(2)->implode(', ') }}</p>
                                <p class="text-xs text-slate-500">{{ $proposal->view_count }} opens · {{ $formatProposalDuration($summary['total_duration_ms'] ?? 0) }}</p>
                            </div>
                            @if($canViewProposalAnalytics)
                                <a href="{{ route('leads.proposals.analytics', [$lead, $proposal]) }}" class="text-xs font-bold text-blue-700 hover:underline">View Analytics</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center">
                <p class="text-sm font-semibold text-slate-700">No proposal sent yet.</p>
                <p class="mt-1 text-xs text-slate-500">Generate a link and send it on WhatsApp to start tracking.</p>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const projectOptions = @json($proposalProjectOptions);
    const leadName = @json($lead->name ?: 'there');
    const form = document.getElementById('leadProposalForm');
    if (!form) return;

    const projectSelect = document.getElementById('leadProposalProjects');
    const expiryMode = document.getElementById('leadProposalExpiryMode');
    const customExpiry = document.getElementById('leadProposalCustomExpiry');
    const captureMode = document.getElementById('leadProposalCaptureMode');
    const messageInput = document.getElementById('leadProposalMessage');
    const generateBtn = document.getElementById('leadProposalGenerateBtn');
    const resultBox = document.getElementById('leadProposalResult');
    const urlOutput = document.getElementById('leadProposalUrlOutput');
    const messageOutput = document.getElementById('leadProposalMessageOutput');
    const copyBtn = document.getElementById('leadProposalCopyBtn');
    const whatsappBtn = document.getElementById('leadProposalWhatsAppBtn');
    const selectedCount = document.getElementById('leadProposalSelectedCount');
    const projectCards = Array.from(document.querySelectorAll('.proposal-project-option'));
    const proposalPlaceholder = String.fromCharCode(123, 123) + 'proposal_url' + String.fromCharCode(125, 125);

    function selectedProjects() {
        const ids = Array.from(projectSelect.options)
            .filter((option) => option.selected)
            .map((option) => parseInt(option.value, 10));
        return projectOptions.filter((project) => ids.includes(project.id));
    }

    function syncProjectCardState() {
        const selectedIds = new Set(selectedProjects().map((project) => String(project.id)));
        projectCards.forEach((card) => {
            const active = selectedIds.has(String(card.dataset.projectId));
            const check = card.querySelector('.proposal-project-check');
            card.setAttribute('aria-pressed', active ? 'true' : 'false');
            card.classList.toggle('border-blue-500', active);
            card.classList.toggle('bg-blue-50', active);
            card.classList.toggle('shadow-md', active);
            check?.classList.toggle('bg-blue-600', active);
            check?.classList.toggle('border-blue-600', active);
        });
        if (selectedCount) {
            selectedCount.textContent = `${selectedIds.size} selected`;
        }
    }

    function buildPreview(url = proposalPlaceholder) {
        const selected = selectedProjects();
        const names = selected.map((project) => project.name);
        const projectText = names.length ? names.join(', ') : 'selected projects';
        const linkLabel = selected.length === 1 ? 'project page link' : 'private proposal link';
        return `Hi ${leadName}, as discussed, these projects match your budget and requirement: ${projectText}. Please open this ${linkLabel}: ${url}`;
    }

    function refreshPreview() {
        messageInput.value = buildPreview();
    }

    function toggleProject(projectId) {
        const option = Array.from(projectSelect.options).find((item) => item.value === String(projectId));
        if (!option) return;
        option.selected = !option.selected;
        syncProjectCardState();
        refreshPreview();
    }

    projectCards.forEach((card) => card.addEventListener('click', () => toggleProject(card.dataset.projectId)));
    projectSelect.addEventListener('change', () => {
        syncProjectCardState();
        refreshPreview();
    });
    expiryMode.addEventListener('change', () => {
        customExpiry.classList.toggle('hidden', expiryMode.value !== 'custom');
    });
    syncProjectCardState();
    refreshPreview();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const projectIds = selectedProjects().map((project) => project.id);
        if (!projectIds.length) {
            alert('Please select at least one project.');
            return;
        }
        if (expiryMode.value === 'custom' && !customExpiry.value) {
            alert('Please select custom expiry date and time.');
            return;
        }

        generateBtn.disabled = true;
        generateBtn.classList.add('opacity-70', 'cursor-not-allowed');
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

        try {
            const response = await fetch(@json(route('leads.proposals.store', $lead)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    project_ids: projectIds,
                    expiry_mode: expiryMode.value,
                    expires_at: customExpiry.value || null,
                    lead_capture_mode: captureMode?.value || 'off',
                    message: messageInput.value.trim(),
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to generate proposal link.');
            }

            urlOutput.value = data.proposal_url;
            messageOutput.value = data.proposal_message;
            whatsappBtn.href = data.whatsapp_url || '#';
            whatsappBtn.classList.toggle('pointer-events-none', !data.whatsapp_url);
            whatsappBtn.classList.toggle('opacity-60', !data.whatsapp_url);
            resultBox.classList.remove('hidden');
        } catch (error) {
            alert(error.message || 'Unable to generate proposal link.');
        } finally {
            generateBtn.disabled = false;
            generateBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            generateBtn.innerHTML = originalText;
        }
    });

    copyBtn?.addEventListener('click', async () => {
        const text = messageOutput.value || messageInput.value;
        try {
            await navigator.clipboard.writeText(text);
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Message', 1600);
        } catch (error) {
            alert('Copy failed. Please copy manually.');
        }
    });
});
</script>
@endpush
