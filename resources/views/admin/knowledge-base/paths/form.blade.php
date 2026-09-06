@extends('layouts.app')

@section('title', $mode === 'edit' ? 'Edit Learning Path' : 'Create Learning Path')
@section('page-title', $mode === 'edit' ? 'Edit Learning Path' : 'Create Learning Path')
@section('page-subtitle', 'Bundle multiple topics into one assignment-ready path')

@section('content')
<style>
    .kb-form-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-form-panel { background: #fff; border: 1px solid #e5ede8; border-radius: 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); padding: 24px; display:grid; gap:20px; }
    .kb-form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .kb-form-field { display:grid; gap:8px; }
    .kb-form-field.full { grid-column: 1 / -1; }
    .kb-form-input, .kb-form-select, .kb-form-textarea { width:100%; border:1px solid #d7e5df; border-radius:16px; padding:14px 16px; font-size:15px; }
    .kb-form-textarea { min-height: 120px; resize: vertical; }
    .kb-assign-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .kb-box { border:1px solid #e5ece8; border-radius:16px; padding:12px; display:flex; gap:10px; align-items:flex-start; }
    .kb-action-row { display:flex; gap:12px; flex-wrap:wrap; }
    .kb-btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; border-radius:16px; padding:14px 18px; text-decoration:none; font-weight:800; }
    .kb-btn.primary { background:#13513f; color:#fff; border:none; }
    .kb-btn.secondary { background:#f8fcfa; color:#13513f; border:1px solid #d7e5df; }
    @media (max-width: 980px) { .kb-form-grid, .kb-assign-grid { grid-template-columns: 1fr; } }
</style>

<div class="kb-form-shell">
    <form method="POST" action="{{ $mode === 'edit' ? route('admin.knowledge-base.paths.update', $path) : route('admin.knowledge-base.paths.store') }}" class="kb-form-panel">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if(session('success'))
            <div style="padding:12px 16px;border-radius:14px;background:#ecf8f1;color:#0d6b35;">{{ session('success') }}</div>
        @endif

        <div class="kb-form-grid">
            <div class="kb-form-field full">
                <label for="pathTitle">Path title</label>
                <input id="pathTitle" class="kb-form-input" type="text" name="title" value="{{ old('title', $path->title) }}" required>
            </div>

            <div class="kb-form-field full">
                <label for="pathSummary">Short summary</label>
                <textarea id="pathSummary" class="kb-form-textarea" name="short_summary">{{ old('short_summary', $path->short_summary) }}</textarea>
            </div>

            <div class="kb-form-field">
                <label for="pathStatus">Status</label>
                <select id="pathStatus" class="kb-form-select" name="status">
                    <option value="draft" @selected(old('status', $path->status) === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $path->status) === 'published')>Published</option>
                </select>
            </div>

            <div class="kb-form-field">
                <label for="pathDisplayOrder">Display order</label>
                <input id="pathDisplayOrder" class="kb-form-input" type="number" min="0" name="display_order" value="{{ old('display_order', $path->display_order ?? 0) }}">
            </div>

            <div class="kb-form-field full">
                <label style="display:flex;align-items:center;gap:12px;text-transform:none;letter-spacing:0;font-size:15px;color:#173b30;">
                    <input type="checkbox" name="is_featured" value="1" @checked((bool) old('is_featured', $path->is_featured))>
                    Mark as featured path
                </label>
            </div>

            <div class="kb-form-field full">
                <label>Select topics in this path</label>
                <div class="kb-assign-grid">
                    @foreach($items as $item)
                        <label class="kb-box">
                            <input type="checkbox" name="knowledge_base_item_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, old('knowledge_base_item_ids', $selectedItemIds), true))>
                            <span><strong style="display:block;color:#173b30;">{{ $item->title }}</strong></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="kb-form-field">
                <label for="pathDueDate">Due date</label>
                <input id="pathDueDate" class="kb-form-input" type="date" name="due_date" value="{{ old('due_date', optional($path->assignments->first())->due_date?->format('Y-m-d')) }}">
            </div>

            <div class="kb-form-field">
                <label style="display:flex;align-items:center;gap:12px;text-transform:none;letter-spacing:0;font-size:15px;color:#173b30; margin-top:24px;">
                    <input type="checkbox" name="is_required" value="1" @checked((bool) old('is_required', optional($path->assignments->first())->is_required ?? true))>
                    Mark assigned path as required
                </label>
            </div>

            <div class="kb-form-field full">
                <label>Assign path to users</label>
                <div class="kb-assign-grid">
                    @foreach($assignableUsers as $user)
                        <label class="kb-box">
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
            <button type="submit" class="kb-btn primary">{{ $mode === 'edit' ? 'Update Path' : 'Create Path' }}</button>
            <a href="{{ route('admin.knowledge-base.paths.index') }}" class="kb-btn secondary">Back to Paths</a>
        </div>
    </form>
</div>
@endsection
