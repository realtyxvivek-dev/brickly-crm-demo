@php
    $summary = $summary ?? [];
    $columns = $columns ?? [];
    $viewMode = $filters['view_mode'] ?? 'list';
    $activeFilters = collect($filters ?? [])
        ->except('view_mode')
        ->filter(function ($value, $key) {
            if ($key === 'date_filter' && (blank($value) || $value === 'all')) {
                return false;
            }

            return is_array($value) ? count(array_filter($value)) > 0 : filled($value);
        })
        ->count();
@endphp

<div class="kanban-summary">
    <div class="kanban-summary-card">
        <strong>{{ $summary['total'] ?? 0 }}</strong>
        <span>Total Leads</span>
    </div>
    <div class="kanban-summary-card">
        <strong>{{ $summary['today_followups'] ?? 0 }}</strong>
        <span>Today Follow-ups</span>
    </div>
    <div class="kanban-summary-card">
        <strong>{{ $summary['overdue_tasks'] ?? 0 }}</strong>
        <span>Overdue Tasks</span>
    </div>
    <div class="kanban-summary-card">
        <strong>{{ $summary['no_next_action'] ?? 0 }}</strong>
        <span>No Next Action</span>
    </div>
    <div class="kanban-summary-card kanban-filter-summary">
        <strong>{{ $activeFilters }}</strong>
        <span>Active Filters</span>
        @if(auth()->check() && auth()->user()->isAdmin())
            <label class="kanban-inline-user-filter">
                <select data-quick-assigned-filter aria-label="Filter by user">
                    <option value="">All Team</option>
                    @foreach($filterOptions['users'] ?? [] as $teamUser)
                        <option value="{{ $teamUser->id }}" @selected((string)($filters['assigned_to'] ?? '') === (string)$teamUser->id)>{{ $teamUser->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button type="button" class="kanban-filter-toggle" data-filter-toggle>
            {{ $activeFilters > 0 ? 'Edit Filter' : 'Filter' }}
        </button>
    </div>
</div>

@if($viewMode === 'list')
    <div class="kanban-board-wrap">
        <div class="kanban-board list-mode">
            @foreach($columns as $column)
                @php($cards = $column['cards'] ?? [])
                <section class="kanban-column" data-kanban-stage="{{ $column['key'] ?? '' }}">
                    <div class="kanban-column-head">
                        <h3>{{ $column['label'] ?? 'Stage' }}</h3>
                        <div class="kanban-head-tools">
                            @if(($column['key'] ?? '') === 'cnp')
                                <div class="kanban-cnp-filter" aria-label="CNP filter">
                                    <button type="button" class="active" data-cnp-filter="all">All</button>
                                    <button type="button" data-cnp-filter="fresh">Fresh</button>
                                    <button type="button" data-cnp-filter="interested">I CNP</button>
                                </div>
                            @endif
                            <span class="kanban-count">{{ count($cards) }}</span>
                        </div>
                    </div>

                    @forelse($cards as $card)
                        <article class="kanban-list-card {{ !empty($card['temperature']) ? 'is-' . $card['temperature'] : '' }}" data-cnp-type="{{ $card['cnp_type'] ?? '' }}">
                            <div class="kanban-list-name">
                                <span class="kanban-list-label">Name</span>
                                <strong title="{{ $card['name'] ?? 'Unnamed lead' }}">
                                    {{ $card['name'] ?? 'Unnamed lead' }}
                                    @if(!empty($card['temperature_label']))
                                        <span class="kanban-temperature {{ $card['temperature'] }}">{{ $card['temperature_label'] }}</span>
                                    @endif
                                </strong>
                            </div>

                            <div class="kanban-list-phone">
                                <span class="kanban-list-label">Number</span>
                                <strong>{{ $card['phone'] ?? 'N/A' }}</strong>
                            </div>

                            <details class="kanban-action-menu">
                                <summary>Action</summary>
                                <div class="kanban-action-list">
                                    <a href="{{ $card['show_url'] ?? '#' }}">Open Lead</a>
                                    @if(!empty($card['tel_url']))
                                        <a href="{{ $card['tel_url'] }}">Call</a>
                                    @endif
                                    @if(!empty($card['wa_url']))
                                        <a href="{{ $card['wa_url'] }}" target="_blank" rel="noopener">WhatsApp</a>
                                    @endif
                                    <a href="{{ $card['followup_url'] ?? ($card['show_url'] ?? '#') }}">Follow-up</a>
                                    <a href="{{ $card['meeting_url'] ?? ($card['show_url'] ?? '#') }}">Meeting</a>
                                    <a href="{{ $card['visit_url'] ?? ($card['show_url'] ?? '#') }}">Visit</a>
                                </div>
                            </details>
                        </article>
                    @empty
                        <div class="kanban-empty">No leads in this stage.</div>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
@else
    <div class="kanban-board-wrap">
        <div class="kanban-board">
            @foreach($columns as $column)
                @php($cards = $column['cards'] ?? [])
                <section class="kanban-column">
                    <div class="kanban-column-head">
                        <h3>{{ $column['label'] ?? 'Stage' }}</h3>
                        <span class="kanban-count">{{ count($cards) }}</span>
                    </div>

                    @forelse($cards as $card)
                        <article class="kanban-card">
                            <h4>{{ $card['name'] ?? 'Unnamed lead' }}</h4>

                            <div class="kanban-pill-row">
                                <span class="kanban-pill">{{ ucwords(str_replace('_', ' ', $card['status'] ?? 'new')) }}</span>
                                <span class="kanban-pill">{{ $card['source'] ?? 'N/A' }}</span>
                            </div>

                            <div class="kanban-meta">
                                <span><i class="fas fa-user"></i> {{ $card['assigned'] ?? 'Unassigned' }}</span>
                                <span><i class="fas fa-building"></i> {{ $card['project'] ?? 'N/A' }}</span>
                                <span><i class="fas fa-clock"></i> {{ $card['next_action'] ?? 'No next action' }}</span>
                                @if(!empty($card['phone']))
                                    <span><i class="fas fa-phone"></i> {{ $card['phone'] }}</span>
                                @endif
                            </div>

                            <div class="kanban-card-actions">
                                <a class="primary" href="{{ $card['show_url'] ?? '#' }}">Open Lead</a>
                                @if(!empty($card['tel_url']))
                                    <a href="{{ $card['tel_url'] }}">Call</a>
                                @endif
                                @if(!empty($card['wa_url']))
                                    <a href="{{ $card['wa_url'] }}" target="_blank" rel="noopener">WhatsApp</a>
                                @endif
                                <a href="{{ $card['followup_url'] ?? ($card['show_url'] ?? '#') }}">Follow-up</a>
                                <a href="{{ $card['meeting_url'] ?? ($card['show_url'] ?? '#') }}">Meeting</a>
                                <a href="{{ $card['visit_url'] ?? ($card['show_url'] ?? '#') }}">Visit</a>
                            </div>
                        </article>
                    @empty
                        <div class="kanban-empty">No leads in this stage.</div>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
@endif
