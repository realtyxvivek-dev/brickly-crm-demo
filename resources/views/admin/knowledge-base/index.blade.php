@extends('layouts.app')

@section('title', 'Manage Knowledge Base')
@section('page-title', 'Knowledge Base')
@section('page-subtitle', 'Create, assign, and publish internal training topics')

@section('content')
<style>
    .kb-admin-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-admin-panel { background: #fff; border: 1px solid #e5ece8; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); }
    .kb-admin-head, .kb-admin-tools, .kb-admin-table-wrap { padding: 24px; }
    .kb-admin-head { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center; }
    .kb-admin-head h1 { margin:0; font-size: 30px; color:#0f3b2e; }
    .kb-admin-btn { display:inline-flex; align-items:center; gap:10px; border-radius:16px; padding:12px 18px; text-decoration:none; font-weight:800; }
    .kb-admin-btn.primary { background:#13513f; color:#fff; }
    .kb-admin-btn.secondary { background:#f7fbf9; color:#13513f; border:1px solid #d7e5df; }
    .kb-admin-tools form { display:grid; gap:12px; grid-template-columns:minmax(0,1.5fr) 220px auto; }
    .kb-admin-input, .kb-admin-select { width:100%; border:1px solid #d7e5df; border-radius:14px; padding:13px 15px; }
    .kb-admin-table { width:100%; border-collapse:collapse; }
    .kb-admin-table th, .kb-admin-table td { padding:16px; border-top:1px solid #eef2f0; text-align:left; vertical-align:top; }
    .kb-admin-table th { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#6a7e77; }
    .kb-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:800; background:#eff7f3; color:#2b5d4f; }
    .kb-badge.draft { background:#f5f6f8; color:#55626a; }
    .kb-badge.featured { background:#fff1de; color:#975100; }
    @media (max-width: 860px) {
        .kb-admin-tools form { grid-template-columns:1fr; }
    }
</style>

<div class="kb-admin-shell">
    <section class="kb-admin-panel">
        <div class="kb-admin-head">
            <div>
                <h1>Manage Knowledge Base</h1>
                <p style="margin:6px 0 0;color:#647871;">Draft, publish, assign, and keep training content updated.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('admin.knowledge-base.reports.index') }}" class="kb-admin-btn secondary">Reports</a>
                <a href="{{ route('admin.knowledge-base.paths.index') }}" class="kb-admin-btn secondary">Learning Paths</a>
                <a href="{{ route('admin.knowledge-base.categories.index') }}" class="kb-admin-btn secondary">Categories</a>
                <a href="{{ route('admin.knowledge-base.create') }}" class="kb-admin-btn primary">Create Topic</a>
            </div>
        </div>

        <div class="kb-admin-tools">
            <form method="GET" action="{{ route('admin.knowledge-base.index') }}">
                <input type="text" class="kb-admin-input" name="q" value="{{ $search }}" placeholder="Search topics by title">
                <select class="kb-admin-select" name="status">
                    <option value="">All statuses</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="published" @selected($status === 'published')>Published</option>
                </select>
                <button type="submit" class="kb-admin-btn primary" style="border:none; justify-content:center;">Filter</button>
            </form>
        </div>

        <div class="kb-admin-table-wrap">
            @if(session('success'))
                <div style="margin-bottom:16px;padding:12px 16px;border-radius:14px;background:#ecf8f1;color:#0d6b35;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="margin-bottom:16px;padding:12px 16px;border-radius:14px;background:#fff2f2;color:#a12b2b;">{{ session('error') }}</div>
            @endif

            @if($items->count())
                <table class="kb-admin-table">
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>Category</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <th>Assignments</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>
                                    <div style="font-weight:800;color:#163b2f;">{{ $item->title }}</div>
                                    <div style="margin-top:6px;color:#667a73;">{{ \Illuminate\Support\Str::limit($item->short_summary ?: 'No summary added yet.', 90) }}</div>
                                </td>
                                <td>{{ $item->category?->name ?: 'Uncategorized' }}</td>
                                <td>{{ $item->project?->name ?: 'No project' }}</td>
                                <td>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <span class="kb-badge {{ $item->status }}">{{ ucfirst($item->status) }}</span>
                                        @if($item->is_featured)
                                            <span class="kb-badge featured">Featured</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ ucfirst(str_replace('_', ' ', $item->visibility_type ?? 'all_users')) }}</td>
                                <td>{{ $item->assignments_count }}</td>
                                <td>
                                    <div>{{ optional($item->updated_at)->format('d M Y') }}</div>
                                    <small style="color:#6c8179;">{{ $item->creator?->name ?: 'System' }}</small>
                                </td>
                                <td>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a href="{{ route('knowledge-base.show', $item->slug) }}" class="kb-admin-btn secondary" style="padding:8px 12px;">Open</a>
                                        <a href="{{ route('admin.knowledge-base.edit', $item) }}" class="kb-admin-btn primary" style="padding:8px 12px;">Edit</a>
                                        <form method="POST" action="{{ route('admin.knowledge-base.destroy', $item) }}" onsubmit="return confirm('Delete this topic?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="kb-admin-btn secondary" style="padding:8px 12px;border-color:#f2cdcd;color:#a12b2b;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:20px;">{{ $items->links() }}</div>
            @else
                <div style="padding:30px 0;color:#61756d;">No knowledge topics found for current filters.</div>
            @endif
        </div>
    </section>
</div>
@endsection
