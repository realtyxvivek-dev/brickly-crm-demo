@extends(
    auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager())
        ? 'sales-manager.layout'
        : (auth()->check() && auth()->user()->isTelecaller()
            ? 'telecaller.layout'
            : (auth()->check() && auth()->user()->isFinanceManager()
                ? 'finance-manager.layout'
                : 'layouts.app'))
)

@section('title', 'Knowledge Base')
@section('page-title', 'Knowledge Base')
@section('page-subtitle', 'Assigned learning and searchable training library')

@section('content')
@php use Illuminate\Support\Str; @endphp
<style>
    .kb-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); }
    .kb-head { padding: 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
    .kb-title { font-size: 30px; font-weight: 800; color: #0f3b2e; margin: 0; }
    .kb-sub { margin: 6px 0 0; color: #5f766d; }
    .kb-manage { display: inline-flex; align-items: center; gap: 10px; padding: 12px 18px; border-radius: 999px; border: 1px solid #d7e5df; background: #f8fcfa; color: #13513f; text-decoration: none; font-weight: 700; }
    .kb-tabs { padding: 0 24px 20px; display: flex; gap: 12px; flex-wrap: wrap; }
    .kb-tab { border: 1px solid #d9e6e0; background: #fff; color: #184c3d; border-radius: 999px; padding: 11px 18px; text-decoration: none; font-weight: 700; }
    .kb-tab.active { background: #13513f; color: #fff; border-color: #13513f; }
    .kb-filters { padding: 0 24px 24px; display: grid; gap: 14px; grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(180px, .7fr)) auto; align-items: end; }
    .kb-filters.is-assigned { grid-template-columns: minmax(0, 1.2fr) repeat(3, minmax(160px, .6fr)) auto; }
    .kb-field { display: grid; gap: 8px; }
    .kb-field label { font-size: 12px; font-weight: 800; color: #5e756d; text-transform: uppercase; letter-spacing: .08em; }
    .kb-input, .kb-select { width: 100%; border-radius: 16px; border: 1px solid #d7e5df; padding: 14px 16px; font-size: 15px; }
    .kb-submit { align-self: end; border: none; border-radius: 16px; background: #13513f; color: #fff; font-weight: 800; padding: 14px 20px; min-width: 130px; }
    .kb-featured { padding: 0 24px 24px; display: grid; gap: 12px; }
    .kb-featured-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .kb-path-grid { padding: 0 24px 24px; display:grid; gap:16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .kb-card-grid { padding: 0 24px 24px; display: grid; gap: 16px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .kb-card { border: 1px solid #e3ece8; border-radius: 22px; overflow: hidden; background: #fff; display: grid; min-height: 100%; }
    .kb-card-media { aspect-ratio: 16 / 9; background: linear-gradient(135deg, #dff1ea, #f5fbf8); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .kb-card-media img { width: 100%; height: 100%; object-fit: cover; }
    .kb-card-body { padding: 18px; display: grid; gap: 12px; }
    .kb-meta { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .kb-chip { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; background: #eff7f3; color: #2b5d4e; }
    .kb-chip.required { background: #fff2df; color: #9a4d00; }
    .kb-chip.status-not_started { background: #f4f6f8; color: #526169; }
    .kb-chip.status-in_progress { background: #e8f3ff; color: #0a5cb5; }
    .kb-chip.status-completed { background: #e8f7ee; color: #0d6b35; }
    .kb-chip.status-due_soon { background: #fff7d6; color: #9b6a00; }
    .kb-chip.status-overdue { background: #fff0f0; color: #b42318; }
    .kb-chip.status-completed_late { background: #fff5eb; color: #b45309; }
    .kb-chip.updated { background: #efe9ff; color: #6b21a8; }
    .kb-card-title { margin: 0; font-size: 21px; line-height: 1.25; color: #153b2f; font-weight: 800; }
    .kb-card-summary { margin: 0; color: #60766f; line-height: 1.55; }
    .kb-card-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .kb-link { color: #13513f; font-weight: 800; text-decoration: none; }
    .kb-empty { padding: 48px 24px; text-align: center; color: #617770; }
    .kb-pagination { padding: 0 24px 24px; }

    @media (max-width: 1100px) {
        .kb-filters, .kb-filters.is-assigned, .kb-card-grid, .kb-featured-strip, .kb-path-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 720px) {
        .kb-shell { padding: 16px; }
        .kb-head, .kb-tabs, .kb-filters, .kb-featured, .kb-card-grid, .kb-pagination { padding-left: 16px; padding-right: 16px; }
        .kb-title { font-size: 24px; }
        .kb-filters, .kb-filters.is-assigned, .kb-card-grid, .kb-featured-strip, .kb-path-grid { grid-template-columns: 1fr; }
        .kb-field label { font-size: 11px; letter-spacing: .06em; }
        .kb-input, .kb-select { padding: 13px 14px; }
        .kb-submit { width: 100%; }
        .kb-card-title { font-size: 19px; }
    }
</style>

<div class="kb-shell">
    <section class="kb-panel">
        <div class="kb-head">
            <div>
                <h1 class="kb-title">Knowledge Base</h1>
                <p class="kb-sub">Learn CRM workflows, project training, reports, and SOPs from one place.</p>
            </div>
            @if($canManageKnowledgeBase)
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.knowledge-base.reports.index') }}" class="kb-manage">Reports</a>
                    <a href="{{ route('admin.knowledge-base.paths.index') }}" class="kb-manage">Learning Paths</a>
                    <a href="{{ route('admin.knowledge-base.index') }}" class="kb-manage">Manage Topics</a>
                    <a href="{{ route('admin.knowledge-base.categories.index') }}" class="kb-manage">Manage Categories</a>
                </div>
            @elseif($canViewKnowledgeBaseReports)
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('admin.knowledge-base.reports.index') }}" class="kb-manage">Reports</a>
                </div>
            @endif
        </div>

        <div class="kb-tabs">
            <a href="{{ route('knowledge-base.index', array_merge(request()->except('page'), ['tab' => 'assigned'])) }}" class="kb-tab {{ $tab === 'assigned' ? 'active' : '' }}">Assigned to Me</a>
            <a href="{{ route('knowledge-base.index', array_merge(request()->except('page'), ['tab' => 'library'])) }}" class="kb-tab {{ $tab === 'library' ? 'active' : '' }}">Library</a>
        </div>

        <form method="GET" action="{{ route('knowledge-base.index') }}" class="kb-filters {{ $tab === 'assigned' ? 'is-assigned' : '' }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="kb-field">
                <label for="kbSearch">Search by title</label>
                <input id="kbSearch" type="text" class="kb-input" name="q" value="{{ $search }}" placeholder="Search guides, SOPs, training videos">
            </div>
            <div class="kb-field">
                <label for="kbCategory">Category</label>
                <select id="kbCategory" class="kb-select" name="category_id">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="kb-field">
                <label for="kbProject">Project</label>
                <select id="kbProject" class="kb-select" name="project_id">
                    <option value="">All projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($tab === 'assigned')
                <div class="kb-field">
                    <label for="kbStatusFilter">Assigned status</label>
                    <select id="kbStatusFilter" class="kb-select" name="status_filter">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatusFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="kb-submit">Apply Filters</button>
        </form>

        @if($tab === 'assigned' && $assignedPaths->isNotEmpty())
            <div class="kb-featured">
                <div class="kb-meta"><span class="kb-chip required">Required Learning Paths</span><span style="color:#5c736b;font-weight:700;">Assigned journeys to complete in sequence</span></div>
            </div>
            <div class="kb-path-grid">
                @foreach($assignedPaths as $path)
                    <article class="kb-card">
                        <div class="kb-card-body">
                            <div class="kb-meta">
                                @if($path->current_assignment?->is_required)
                                    <span class="kb-chip required">Required</span>
                                @endif
                                <span class="kb-chip status-{{ $path->display_status }}">{{ $path->display_status_label }}</span>
                                <span class="kb-chip">{{ $path->completed_items_count }}/{{ $path->total_items_count }} Topics</span>
                            </div>
                            <h2 class="kb-card-title">{{ $path->title }}</h2>
                            <p class="kb-card-summary">{{ \Illuminate\Support\Str::limit($path->short_summary ?: 'Complete this learning path topic by topic.', 125) }}</p>
                            <div class="kb-card-foot">
                                <small style="color:#71867f;">@if($path->current_assignment?->due_date) Due {{ $path->current_assignment->due_date->format('d M Y') }} @else No due date @endif</small>
                                <span class="kb-link">Assigned Path</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @elseif($tab === 'library' && $featuredPaths->isNotEmpty())
            <div class="kb-featured">
                <div class="kb-meta"><span class="kb-chip">Featured Paths</span><span style="color:#5c736b;font-weight:700;">Recommended structured learning paths</span></div>
            </div>
            <div class="kb-path-grid">
                @foreach($featuredPaths as $path)
                    <article class="kb-card">
                        <div class="kb-card-body">
                            <div class="kb-meta">
                                <span class="kb-chip">{{ $path->total_items_count }} Topics</span>
                                <span class="kb-chip">{{ ucfirst($path->status) }}</span>
                            </div>
                            <h2 class="kb-card-title">{{ $path->title }}</h2>
                            <p class="kb-card-summary">{{ \Illuminate\Support\Str::limit($path->short_summary ?: 'Recommended training path.', 125) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if($tab === 'library' && $featuredItems->isNotEmpty())
            <div class="kb-featured">
                <div class="kb-meta"><span class="kb-chip">Featured</span><span style="color:#5c736b;font-weight:700;">Top recommended topics for faster onboarding</span></div>
                <div class="kb-featured-strip">
                    @foreach($featuredItems as $featuredItem)
                        <article class="kb-card">
                            <div class="kb-card-media">
                                @if($featuredItem->thumbnail_url)
                                    <img src="{{ $featuredItem->thumbnail_url }}" alt="{{ $featuredItem->title }}">
                                @else
                                    <span style="font-size:48px;color:#93b8aa;"><i class="fas fa-book-open"></i></span>
                                @endif
                            </div>
                            <div class="kb-card-body">
                                <div class="kb-meta">
                                    @if($featuredItem->category)
                                        <span class="kb-chip">{{ $featuredItem->category->name }}</span>
                                    @endif
                                    @foreach($featuredItem->content_types as $type)
                                        <span class="kb-chip">{{ $type }}</span>
                                    @endforeach
                                </div>
                                <h2 class="kb-card-title">{{ $featuredItem->title }}</h2>
                                <p class="kb-card-summary">{{ Str::limit($featuredItem->short_summary ?: 'Training topic for internal team use.', 110) }}</p>
                                <div class="kb-card-foot">
                                    <small style="color:#71867f;">Updated {{ optional($featuredItem->updated_at)->format('d M Y') }}</small>
                                    <a href="{{ route('knowledge-base.show', $featuredItem->slug) }}" class="kb-link">Open Topic</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        @if($items->count())
            <div class="kb-card-grid">
                @foreach($items as $item)
                    @php
                        $assignment = $item->assignments->first();
                        $progress = $item->progressRecords->first();
                        $status = $progress->status ?? 'not_started';
                        $statusLabel = match($status) {
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                            default => 'Not Started',
                        };
                    @endphp
                    <article class="kb-card">
                        <div class="kb-card-media">
                            @if($item->thumbnail_url)
                                <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}">
                            @else
                                <span style="font-size:48px;color:#93b8aa;"><i class="fas fa-graduation-cap"></i></span>
                            @endif
                        </div>
                        <div class="kb-card-body">
                            <div class="kb-meta">
                                @if($assignment)
                                    <span class="kb-chip required">Required</span>
                                @endif
                                <span class="kb-chip status-{{ $item->display_status }}">{{ $item->display_status_label }}</span>
                                @if($item->is_updated_for_user)
                                    <span class="kb-chip updated">Updated</span>
                                @endif
                                @if($item->category)
                                    <span class="kb-chip">{{ $item->category->name }}</span>
                                @endif
                                @if($item->project)
                                    <span class="kb-chip">{{ $item->project->name }}</span>
                                @endif
                            </div>
                            <h2 class="kb-card-title">{{ $item->title }}</h2>
                            <p class="kb-card-summary">{{ Str::limit($item->short_summary ?: 'Open this topic to access article, video, and PDF guidance.', 135) }}</p>
                            <div class="kb-meta">
                                @foreach($item->content_types as $type)
                                    <span class="kb-chip">{{ $type }}</span>
                                @endforeach
                            </div>
                            <div class="kb-card-foot">
                                <small style="color:#71867f;">
                                    Updated {{ optional($item->content_updated_at ?: $item->updated_at)->format('d M Y') }}
                                    @if($assignment?->due_date)
                                        · Due {{ $assignment->due_date->format('d M Y') }}
                                    @endif
                                </small>
                                <a href="{{ route('knowledge-base.show', $item->slug) }}" class="kb-link">Read / Watch</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="kb-pagination">
                {{ $items->links() }}
            </div>
        @else
            <div class="kb-empty">
                <h3 style="margin:0 0 8px; font-size:22px; color:#183e32;">No topics found</h3>
                <p style="margin:0;">Filters match nahi hue. Search ya category/project filter change karke dubara try karo.</p>
            </div>
        @endif
    </section>
</div>
@endsection
