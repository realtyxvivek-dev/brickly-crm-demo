@extends('layouts.app')

@section('title', 'Learning Paths')
@section('page-title', 'Learning Paths')
@section('page-subtitle', 'Bundle multiple knowledge topics into structured onboarding journeys')

@section('content')
<style>
    .kb-admin-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-admin-panel { background: #fff; border: 1px solid #e5ece8; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); }
    .kb-admin-head, .kb-admin-table-wrap { padding: 24px; }
    .kb-admin-head { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center; }
    .kb-admin-btn { display:inline-flex; align-items:center; gap:10px; border-radius:16px; padding:12px 18px; text-decoration:none; font-weight:800; }
    .kb-admin-btn.primary { background:#13513f; color:#fff; }
    .kb-admin-btn.secondary { background:#f7fbf9; color:#13513f; border:1px solid #d7e5df; }
    .kb-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:800; background:#eff7f3; color:#2b5d4f; }
    .kb-badge.draft { background:#f5f6f8; color:#55626a; }
    .kb-badge.featured { background:#fff1de; color:#975100; }
    .kb-table { width:100%; border-collapse:collapse; }
    .kb-table th, .kb-table td { padding:16px; border-top:1px solid #eef2f0; text-align:left; vertical-align:top; }
    .kb-table th { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#6a7e77; }
</style>

<div class="kb-admin-shell">
    <section class="kb-admin-panel">
        <div class="kb-admin-head">
            <div>
                <h1 style="margin:0; font-size:30px; color:#0f3b2e;">Learning Paths</h1>
                <p style="margin:6px 0 0;color:#647871;">Group multiple topics and assign full training journeys to users.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('admin.knowledge-base.index') }}" class="kb-admin-btn secondary">Topics</a>
                <a href="{{ route('admin.knowledge-base.paths.create') }}" class="kb-admin-btn primary">Create Path</a>
            </div>
        </div>

        <div class="kb-admin-table-wrap">
            @if(session('success'))
                <div style="margin-bottom:16px;padding:12px 16px;border-radius:14px;background:#ecf8f1;color:#0d6b35;">{{ session('success') }}</div>
            @endif

            @if($paths->count())
                <table class="kb-table">
                    <thead>
                        <tr>
                            <th>Path</th>
                            <th>Status</th>
                            <th>Topics</th>
                            <th>Assignments</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paths as $path)
                            <tr>
                                <td>
                                    <div style="font-weight:800;color:#163b2f;">{{ $path->title }}</div>
                                    <div style="margin-top:6px;color:#667a73;">{{ \Illuminate\Support\Str::limit($path->short_summary ?: 'No summary added yet.', 100) }}</div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <span class="kb-badge {{ $path->status }}">{{ ucfirst($path->status) }}</span>
                                        @if($path->is_featured)
                                            <span class="kb-badge featured">Featured</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $path->path_items_count }}</td>
                                <td>{{ $path->assignments_count }}</td>
                                <td>{{ optional($path->updated_at)->format('d M Y') }}</td>
                                <td>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a href="{{ route('admin.knowledge-base.paths.edit', $path) }}" class="kb-admin-btn primary" style="padding:8px 12px;">Edit</a>
                                        <form method="POST" action="{{ route('admin.knowledge-base.paths.destroy', $path) }}" onsubmit="return confirm('Delete this learning path?');">
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
                <div style="margin-top:20px;">{{ $paths->links() }}</div>
            @else
                <div style="padding:30px 0;color:#61756d;">No learning paths created yet.</div>
            @endif
        </div>
    </section>
</div>
@endsection
