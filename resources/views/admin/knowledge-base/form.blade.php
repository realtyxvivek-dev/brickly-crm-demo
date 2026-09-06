@extends('layouts.app')

@section('title', $mode === 'edit' ? 'Edit Knowledge Topic' : 'Create Knowledge Topic')
@section('page-title', $mode === 'edit' ? 'Edit Knowledge Topic' : 'Create Knowledge Topic')
@section('page-subtitle', 'Build a reusable training topic with article, video, PDF, and assignments')

@section('content')
<style>
    .kb-form-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-form-panel { background: #fff; border: 1px solid #e5ede8; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); padding: 24px; display:grid; gap:20px; }
    .kb-form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .kb-form-field { display:grid; gap:8px; }
    .kb-form-field.full { grid-column: 1 / -1; }
    .kb-form-field label { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#61756d; }
    .kb-form-input, .kb-form-select, .kb-form-textarea { width:100%; border:1px solid #d7e5df; border-radius:16px; padding:14px 16px; font-size:15px; }
    .kb-form-textarea { min-height: 160px; resize: vertical; }
    .kb-assign-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .kb-user-box { border:1px solid #e5ece8; border-radius:16px; padding:12px; display:flex; gap:10px; align-items:flex-start; }
    .kb-file-note { color:#697c75; font-size:13px; }
    .kb-action-row { display:flex; gap:12px; flex-wrap:wrap; }
    .kb-btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; border-radius:16px; padding:14px 18px; text-decoration:none; font-weight:800; }
    .kb-btn.primary { background:#13513f; color:#fff; border:none; }
    .kb-btn.secondary { background:#f8fcfa; color:#13513f; border:1px solid #d7e5df; }
    .kb-warning { padding:16px 18px; border-radius:18px; background:#fff7e8; border:1px solid #f0d7a8; color:#8a4b08; }
    @media (max-width: 980px) { .kb-form-grid, .kb-assign-grid { grid-template-columns: 1fr; } }
</style>

<div class="kb-form-shell">
    <form method="POST" action="{{ $mode === 'edit' ? route('admin.knowledge-base.update', $item) : route('admin.knowledge-base.store') }}" enctype="multipart/form-data" class="kb-form-panel">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if(session('success'))
            <div style="padding:12px 16px;border-radius:14px;background:#ecf8f1;color:#0d6b35;">{{ session('success') }}</div>
        @endif

        @if($similarItems->isNotEmpty())
            <div class="kb-warning">
                <strong>Similar topics found:</strong>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($similarItems as $similarItem)
                        <li>{{ $similarItem->title }} ({{ ucfirst($similarItem->status) }}, updated {{ optional($similarItem->updated_at)->format('d M Y') }})</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="kb-form-grid">
            <div class="kb-form-field full">
                <label for="kbTitle">Title</label>
                <input id="kbTitle" class="kb-form-input" type="text" name="title" value="{{ old('title', $item->title) }}" required>
                @error('title')<div style="color:#b42318;">{{ $message }}</div>@enderror
            </div>

            <div class="kb-form-field">
                <label for="kbCategoryId">Category</label>
                <select id="kbCategoryId" class="kb-form-select" name="category_id">
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="kb-form-field">
                <label for="kbProjectId">Linked project</label>
                <select id="kbProjectId" class="kb-form-select" name="project_id">
                    <option value="">No project link</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) old('project_id', $item->project_id) === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="kb-form-field full">
                <label for="kbShortSummary">Short summary</label>
                <textarea id="kbShortSummary" class="kb-form-textarea" name="short_summary" style="min-height:110px;">{{ old('short_summary', $item->short_summary) }}</textarea>
            </div>

            <div class="kb-form-field full">
                <label for="kbArticleContent">Article content</label>
                <textarea id="kbArticleContent" class="kb-form-textarea" name="article_content">{{ old('article_content', $item->article_content) }}</textarea>
            </div>

            <div class="kb-form-field">
                <label for="kbVideoUrl">Video URL</label>
                <input id="kbVideoUrl" class="kb-form-input" type="url" name="video_url" value="{{ old('video_url', $item->video_url) }}" placeholder="YouTube, Vimeo, or direct video URL">
            </div>

            <div class="kb-form-field">
                <label for="kbPdfFile">PDF upload</label>
                <input id="kbPdfFile" class="kb-form-input" type="file" name="pdf_file" accept=".pdf">
                @if($item->pdf_url)
                    <div class="kb-file-note">Current PDF: <a href="{{ $item->pdf_url }}" target="_blank" rel="noopener">Open current file</a></div>
                @endif
            </div>

            <div class="kb-form-field">
                <label for="kbThumbnailFile">Thumbnail image</label>
                <input id="kbThumbnailFile" class="kb-form-input" type="file" name="thumbnail_file" accept="image/*">
                @if($item->thumbnail_url)
                    <div class="kb-file-note">Current thumbnail available</div>
                @endif
            </div>

            <div class="kb-form-field">
                <label for="kbStatus">Status</label>
                <select id="kbStatus" class="kb-form-select" name="status">
                    <option value="draft" @selected(old('status', $item->status) === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $item->status) === 'published')>Published</option>
                </select>
            </div>

            <div class="kb-form-field">
                <label for="kbVisibilityType">Visibility</label>
                <select id="kbVisibilityType" class="kb-form-select" name="visibility_type">
                    <option value="all_users" @selected(old('visibility_type', $item->visibility_type ?? 'all_users') === 'all_users')>All Users</option>
                    <option value="selected_roles" @selected(old('visibility_type', $item->visibility_type) === 'selected_roles')>Selected Roles</option>
                    <option value="selected_users" @selected(old('visibility_type', $item->visibility_type) === 'selected_users')>Selected Users</option>
                </select>
            </div>

            <div class="kb-form-field">
                <label for="kbDisplayOrder">Display order</label>
                <input id="kbDisplayOrder" class="kb-form-input" type="number" min="0" name="display_order" value="{{ old('display_order', $item->display_order ?? 0) }}">
            </div>

            <div class="kb-form-field full">
                <label for="kbChangeSummary">Change summary</label>
                <textarea id="kbChangeSummary" class="kb-form-textarea" name="change_summary" style="min-height:90px;" placeholder="What changed in this topic?">{{ old('change_summary', $item->change_summary) }}</textarea>
            </div>

            <div class="kb-form-field full">
                <label style="display:flex;align-items:center;gap:12px;text-transform:none;letter-spacing:0;font-size:15px;color:#173b30;">
                    <input type="checkbox" name="is_featured" value="1" @checked((bool) old('is_featured', $item->is_featured))>
                    Mark as featured in library
                </label>
            </div>

            <div class="kb-form-field full" id="kbVisibleRolesField" style="{{ old('visibility_type', $item->visibility_type ?? 'all_users') === 'selected_roles' ? '' : 'display:none;' }}">
                <label>Visible to selected roles</label>
                <div class="kb-assign-grid">
                    @foreach($assignableRoles as $role)
                        <label class="kb-user-box">
                            <input type="checkbox" name="visible_role_slugs[]" value="{{ $role->slug }}" @checked(in_array($role->slug, old('visible_role_slugs', $item->visible_role_slugs_for_form ?? []), true))>
                            <span><strong style="display:block;color:#173b30;">{{ $role->name }}</strong></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="kb-form-field full" id="kbVisibleUsersField" style="{{ old('visibility_type', $item->visibility_type ?? 'all_users') === 'selected_users' ? '' : 'display:none;' }}">
                <label>Visible to selected users</label>
                <div class="kb-assign-grid">
                    @foreach($assignableUsers as $user)
                        <label class="kb-user-box">
                            <input type="checkbox" name="visible_user_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, old('visible_user_ids', $item->visible_user_ids_for_form ?? []), true))>
                            <span>
                                <strong style="display:block;color:#173b30;">{{ $user->name }}</strong>
                                <small style="color:#6f827b;">{{ $user->role?->name ?? ucfirst(str_replace('_', ' ', $user->role?->slug ?? 'user')) }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="kb-form-field">
                <label for="kbDueDate">Assignment due date</label>
                <input id="kbDueDate" class="kb-form-input" type="date" name="due_date" value="{{ old('due_date', optional($item->assignments->first())->due_date?->format('Y-m-d')) }}">
            </div>

            <div class="kb-form-field">
                <label style="display:flex;align-items:center;gap:12px;text-transform:none;letter-spacing:0;font-size:15px;color:#173b30; margin-top:24px;">
                    <input type="checkbox" name="is_required" value="1" @checked((bool) old('is_required', optional($item->assignments->first())->is_required ?? true))>
                    Mark assigned topic as required
                </label>
            </div>

            <div class="kb-form-field full">
                <label>Assign to selected users</label>
                <div class="kb-assign-grid">
                    @foreach($assignableUsers as $user)
                        <label class="kb-user-box">
                            <input type="checkbox" name="assigned_user_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, old('assigned_user_ids', $assignedUserIds), true))>
                            <span>
                                <strong style="display:block;color:#173b30;">{{ $user->name }}</strong>
                                <small style="color:#6f827b;">{{ $user->role?->name ?? ucfirst(str_replace('_', ' ', $user->role?->slug ?? 'user')) }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="kb-action-row">
            <button type="submit" class="kb-btn primary">{{ $mode === 'edit' ? 'Update Topic' : 'Create Topic' }}</button>
            <a href="{{ route('admin.knowledge-base.index') }}" class="kb-btn secondary">Back to List</a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const visibility = document.getElementById('kbVisibilityType');
    const rolesField = document.getElementById('kbVisibleRolesField');
    const usersField = document.getElementById('kbVisibleUsersField');

    const sync = () => {
        rolesField.style.display = visibility.value === 'selected_roles' ? '' : 'none';
        usersField.style.display = visibility.value === 'selected_users' ? '' : 'none';
    };

    visibility?.addEventListener('change', sync);
    sync();
});
</script>
@endsection
