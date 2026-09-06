@extends('finance-manager.layout')

@section('title', 'Expense Categories')
@section('page_title', 'Expense Categories')
@section('page_subtitle', 'Global category layer reused across every company expense.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
@php
    $activeCategories = $categories->where('is_active', true)->count();
    $totalSubcategories = $categories->sum('subcategories_count');
@endphp
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-master-shell">
        <div class="expense-master-intro">
            <h2>Category Master</h2>
            <p>Top-level expense buckets yahi define honge. Ek strong category layer se company-wise reporting aur future income mapping dono predictable rehte hain.</p>
        </div>

        <div class="expense-stat-grid">
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Total Categories</div>
                <strong>{{ $categories->count() }}</strong>
                <span>Shared structure for all companies</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Active</div>
                <strong>{{ $activeCategories }}</strong>
                <span>Available in current expense flow</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Mapped Subcategories</div>
                <strong>{{ $totalSubcategories }}</strong>
                <span>Detailed heads attached below</span>
            </div>
        </div>

        <div class="expense-master-grid">
            <section class="expense-card expense-master-create">
                <div class="expense-header">
                    <div>
                        <h2>Add Category</h2>
                        <p>Category naming concise rakho. Code short aur reusable hona chahiye.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('finance-manager.expenses.categories.store') }}" class="expense-inline-form">
                    @csrf
                    <div class="expense-field">
                        <label>Category Name</label>
                        <input class="expense-input" type="text" name="name" value="{{ old('name') }}" placeholder="Travel" required>
                    </div>
                    <div class="expense-inline-grid">
                        <div class="expense-field">
                            <label>Code</label>
                            <input class="expense-input" type="text" name="code" value="{{ old('code') }}" placeholder="TRAVEL" required>
                        </div>
                        <div class="expense-field">
                            <label>Sort Order</label>
                            <input class="expense-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}">
                        </div>
                        <div class="expense-field">
                            <label>Status</label>
                            <label class="expense-checkbox" style="min-height:52px;">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active category
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="expense-btn primary">Save Category</button>
                </form>
            </section>

            <section class="expense-master-list">
                @forelse($categories as $category)
                    <article class="expense-master-item">
                        <div class="expense-master-topline">
                            <div>
                                <h3 class="expense-master-title">{{ $category->name }}</h3>
                                <div class="expense-master-meta">
                                    <span class="expense-master-pill">Code {{ $category->code }}</span>
                                    <span class="expense-master-pill">{{ $category->subcategories_count }} Subheads</span>
                                    <span class="expense-master-pill">Sort {{ $category->sort_order }}</span>
                                    <span class="expense-master-pill {{ $category->is_active ? 'is-active' : '' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('finance-manager.expenses.categories.update', $category) }}" class="expense-inline-form">
                            @csrf
                            @method('PUT')
                            <div class="expense-inline-grid">
                                <div class="expense-field">
                                    <label>Name</label>
                                    <input class="expense-input" type="text" name="name" value="{{ $category->name }}" required>
                                </div>
                                <div class="expense-field">
                                    <label>Code</label>
                                    <input class="expense-input" type="text" name="code" value="{{ $category->code }}" required>
                                </div>
                                <div class="expense-field">
                                    <label>Sort Order</label>
                                    <input class="expense-input" type="number" min="0" name="sort_order" value="{{ $category->sort_order }}">
                                </div>
                            </div>
                            <div class="expense-inline-actions">
                                <label class="expense-checkbox">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                                    Keep active for new expenses
                                </label>
                                <button type="submit" class="expense-btn soft">Update Category</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="expense-empty-state">No categories available yet.</div>
                @endforelse
            </section>
        </div>
    </section>
</div>
@endsection
