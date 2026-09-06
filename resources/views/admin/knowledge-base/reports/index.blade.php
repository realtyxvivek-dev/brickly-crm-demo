@extends('layouts.app')

@section('title', 'Knowledge Base Reports')
@section('page-title', 'Knowledge Base Reports')
@section('page-subtitle', 'Track completion, overdue items, and category/project adoption')

@section('content')
<style>
    .kb-report-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-panel { background: #fff; border: 1px solid #e5ece8; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); padding: 24px; }
    .kb-stats { display:grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; }
    .kb-stat { border:1px solid #e5ece8; border-radius:18px; padding:16px; }
    .kb-stat small { display:block; text-transform:uppercase; letter-spacing:.08em; color:#6a7e77; font-weight:800; }
    .kb-stat strong { display:block; margin-top:10px; font-size:28px; color:#143b2f; }
    .kb-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px; }
    .kb-table { width:100%; border-collapse:collapse; }
    .kb-table th, .kb-table td { padding:14px 10px; border-top:1px solid #eef2f0; text-align:left; vertical-align:top; }
    .kb-table th { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#6a7e77; }
    .kb-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:800; background:#eff7f3; color:#2b5d4f; }
    .kb-badge.overdue { background:#fff1f1; color:#b42318; }
    .kb-filters { display:grid; gap:14px; grid-template-columns: repeat(2, minmax(220px, 1fr)) auto; align-items:end; }
    .kb-input { width:100%; border-radius:14px; border:1px solid #d7e5df; padding:13px 15px; }
    .kb-btn { display:inline-flex; align-items:center; justify-content:center; border:none; border-radius:14px; background:#13513f; color:#fff; font-weight:800; padding:13px 18px; }
    @media (max-width: 1100px) { .kb-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } .kb-grid { grid-template-columns: 1fr; } }
    @media (max-width: 720px) { .kb-report-shell { padding:16px; } .kb-stats, .kb-filters { grid-template-columns: 1fr; } }
</style>

<div class="kb-report-shell">
    <section class="kb-panel">
        <form method="GET" class="kb-filters">
            <div>
                <label style="display:block;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6a7e77;font-weight:800;">Category</label>
                <select class="kb-input" name="category_id">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6a7e77;font-weight:800;">Project</label>
                <select class="kb-input" name="project_id">
                    <option value="">All projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="kb-btn" type="submit">Apply Filters</button>
        </form>
    </section>

    <section class="kb-stats">
        @foreach(['assigned' => 'Assigned', 'not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'overdue' => 'Overdue', 'completed_late' => 'Completed Late'] as $key => $label)
            <article class="kb-stat">
                <small>{{ $label }}</small>
                <strong>{{ $statusCounts[$key] ?? 0 }}</strong>
            </article>
        @endforeach
    </section>

    <section class="kb-grid">
        <article class="kb-panel">
            <h2 style="margin:0 0 10px; color:#13392d;">User-wise Completion</h2>
            <table class="kb-table">
                <thead><tr><th>User</th><th>Assigned</th><th>Completed</th><th>Overdue</th></tr></thead>
                <tbody>
                    @forelse($userStats as $row)
                        <tr>
                            <td>{{ $row['user']->name }}</td>
                            <td>{{ $row['assigned'] }}</td>
                            <td>{{ $row['completed'] }}</td>
                            <td>{{ $row['overdue'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No assignment data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="kb-panel">
            <h2 style="margin:0 0 10px; color:#13392d;">Topic-wise Completion</h2>
            <table class="kb-table">
                <thead><tr><th>Topic</th><th>Assigned</th><th>Completed</th><th>Overdue</th></tr></thead>
                <tbody>
                    @forelse($topicStats as $row)
                        <tr>
                            <td>{{ $row['item']->title }}</td>
                            <td>{{ $row['assigned'] }}</td>
                            <td>{{ $row['completed'] }}</td>
                            <td>{{ $row['overdue'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No topic data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="kb-panel">
            <h2 style="margin:0 0 10px; color:#13392d;">Overdue Assignments</h2>
            <table class="kb-table">
                <thead><tr><th>User</th><th>Topic</th><th>Due Date</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($overdueAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->user?->name }}</td>
                            <td>{{ $assignment->item?->title }}</td>
                            <td>{{ optional($assignment->due_date)->format('d M Y') }}</td>
                            <td><span class="kb-badge overdue">Overdue</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No overdue assignments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="kb-panel">
            <h2 style="margin:0 0 10px; color:#13392d;">Category / Project Adoption</h2>
            <div style="display:grid; gap:18px;">
                <div>
                    <h3 style="margin:0 0 8px; font-size:16px; color:#173e31;">By Category</h3>
                    <table class="kb-table">
                        <thead><tr><th>Category</th><th>Assigned</th><th>Completed</th></tr></thead>
                        <tbody>
                            @foreach($categoryStats as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['assigned'] }}</td>
                                    <td>{{ $row['completed'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3 style="margin:0 0 8px; font-size:16px; color:#173e31;">By Project</h3>
                    <table class="kb-table">
                        <thead><tr><th>Project</th><th>Assigned</th><th>Completed</th></tr></thead>
                        <tbody>
                            @foreach($projectStats as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['assigned'] }}</td>
                                    <td>{{ $row['completed'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>
</div>
@endsection
