@extends('layouts.app')

@section('title', 'Knowledge Base Categories')
@section('page-title', 'Knowledge Base Categories')
@section('page-subtitle', 'Keep categories clean and reusable across all training topics')

@section('content')
<style>
    .kb-cat-shell { padding:24px; display:grid; gap:20px; }
    .kb-cat-panel { background:#fff; border:1px solid #e5ede8; border-radius:22px; box-shadow:0 18px 48px rgba(15,23,42,.06); padding:24px; display:grid; gap:18px; }
    .kb-cat-grid { display:grid; grid-template-columns: 380px minmax(0, 1fr); gap:20px; }
    .kb-cat-form, .kb-cat-list { display:grid; gap:14px; }
    .kb-cat-input, .kb-cat-textarea { width:100%; border:1px solid #d7e5df; border-radius:14px; padding:13px 15px; }
    .kb-cat-textarea { min-height:100px; }
    .kb-cat-card { border:1px solid #e8efeb; border-radius:18px; padding:16px; display:grid; gap:12px; }
    .kb-cat-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:14px; padding:11px 14px; font-weight:800; text-decoration:none; }
    .kb-cat-btn.primary { background:#13513f; color:#fff; border:none; }
    .kb-cat-btn.secondary { background:#f8fcfa; color:#13513f; border:1px solid #d7e5df; }
    @media (max-width: 960px) { .kb-cat-grid { grid-template-columns: 1fr; } }
</style>

<div class="kb-cat-shell">
    <section class="kb-cat-panel">
        @if(session('success'))
            <div style="padding:12px 16px;border-radius:14px;background:#ecf8f1;color:#0d6b35;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div style="padding:12px 16px;border-radius:14px;background:#fff2f2;color:#a12b2b;">{{ session('error') }}</div>
        @endif

        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <div>
                <h1 style="margin:0;font-size:28px;color:#123b2d;">Manage Categories</h1>
                <p style="margin:6px 0 0;color:#657972;">Create stable categories before publishing training content.</p>
            </div>
            <a href="{{ route('admin.knowledge-base.index') }}" class="kb-cat-btn secondary">Back to Topics</a>
        </div>

        <div class="kb-cat-grid">
            <form method="POST" action="{{ route('admin.knowledge-base.categories.store') }}" class="kb-cat-form">
                @csrf
                <input class="kb-cat-input" type="text" name="name" placeholder="Category name" required>
                <textarea class="kb-cat-textarea" name="description" placeholder="Short description"></textarea>
                <input class="kb-cat-input" type="number" min="0" name="display_order" value="0" placeholder="Display order">
                <label style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active category
                </label>
                <button type="submit" class="kb-cat-btn primary">Create Category</button>
            </form>

            <div class="kb-cat-list">
                @foreach($categories as $category)
                    <div class="kb-cat-card">
                        <form method="POST" action="{{ route('admin.knowledge-base.categories.update', $category) }}" style="display:grid;gap:12px;">
                            @csrf
                            @method('PUT')
                            <div style="display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:12px;">
                                <input class="kb-cat-input" type="text" name="name" value="{{ $category->name }}" required>
                                <input class="kb-cat-input" type="number" min="0" name="display_order" value="{{ $category->display_order }}">
                            </div>
                            <textarea class="kb-cat-textarea" name="description">{{ $category->description }}</textarea>
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                                <label style="display:flex;align-items:center;gap:10px;">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                                    Active
                                </label>
                                <span style="color:#6a7e77;">{{ $category->items_count }} topics</span>
                            </div>
                            <button type="submit" class="kb-cat-btn primary" style="width:max-content;">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.knowledge-base.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="kb-cat-btn secondary" style="border-color:#f1d0d0;color:#a12b2b;">Delete</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
