@extends('finance-manager.layout')

@section('title', 'Expense Subcategories')
@section('page_title', 'Expense Subcategories')
@section('page_subtitle', 'Category-wise reusable subcategory structure for all companies.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
@php
    $activeSubcategories = $subcategories->where('is_active', true)->count();
@endphp
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-master-shell">
        <div class="expense-master-intro">
            <h2>Subcategory Master</h2>
            <p>Detailed heads category ke andar maintain karo. Entry form me category choose hote hi isi structure ka filtered dropdown show hoga.</p>
        </div>

        <div class="expense-stat-grid">
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Total Subcategories</div>
                <strong>{{ $subcategories->count() }}</strong>
                <span>Reusable across all companies</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Active</div>
                <strong>{{ $activeSubcategories }}</strong>
                <span>Visible in current expense forms</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Categories Linked</div>
                <strong>{{ $categories->count() }}</strong>
                <span>Parent buckets available</span>
            </div>
        </div>

        <div class="expense-master-grid">
            <section class="expense-card expense-master-create">
                <div class="expense-header">
                    <div>
                        <h2>Add Subcategory</h2>
                        <p>Subhead ko sahi parent category ke andar map karo taaki reports logically grouped rahein.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('finance-manager.expenses.subcategories.store') }}" class="expense-inline-form">
                    @csrf
                    <div class="expense-field">
                        <label>Parent Category</label>
                        <select name="expense_category_id" class="expense-input" required>
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="expense-inline-grid">
                        <div class="expense-field">
                            <label>Subcategory Name</label>
                            <input class="expense-input" type="text" name="name" value="{{ old('name') }}" placeholder="Fuel" required>
                        </div>
                        <div class="expense-field">
                            <label>Code</label>
                            <input class="expense-input" type="text" name="code" value="{{ old('code') }}" placeholder="FUEL" required>
                        </div>
                        <div class="expense-field">
                            <label>Sort Order</label>
                            <input class="expense-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}">
                        </div>
                    </div>
                    <div class="expense-inline-actions">
                        <label class="expense-checkbox">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Active subcategory
                        </label>
                        <button type="submit" class="expense-btn primary">Save Subcategory</button>
                    </div>
                </form>
            </section>

            <section class="expense-master-list">
                @forelse($subcategories as $subcategory)
                    <article class="expense-master-item">
                        <div class="expense-master-topline">
                            <div>
                                <h3 class="expense-master-title">{{ $subcategory->name }}</h3>
                                <div class="expense-master-meta">
                                    <span class="expense-master-pill">{{ $subcategory->category?->name }}</span>
                                    <span class="expense-master-pill">Code {{ $subcategory->code }}</span>
                                    <span class="expense-master-pill">Sort {{ $subcategory->sort_order }}</span>
                                    <span class="expense-master-pill {{ $subcategory->is_active ? 'is-active' : '' }}">{{ $subcategory->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('finance-manager.expenses.subcategories.update', $subcategory) }}" class="expense-inline-form">
                            @csrf
                            @method('PUT')
                            <div class="expense-inline-grid">
                                <div class="expense-field">
                                    <label>Category</label>
                                    <select name="expense_category_id" class="expense-input" required>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected($subcategory->expense_category_id === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="expense-field">
                                    <label>Name</label>
                                    <input class="expense-input" type="text" name="name" value="{{ $subcategory->name }}" required>
                                </div>
                                <div class="expense-field">
                                    <label>Code</label>
                                    <input class="expense-input" type="text" name="code" value="{{ $subcategory->code }}" required>
                                </div>
                            </div>
                            <div class="expense-inline-actions">
                                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                                    <div class="expense-field" style="margin:0;">
                                        <label>Sort Order</label>
                                        <input class="expense-input" style="width:120px;" type="number" min="0" name="sort_order" value="{{ $subcategory->sort_order }}">
                                    </div>
                                    <label class="expense-checkbox">
                                        <input type="checkbox" name="is_active" value="1" @checked($subcategory->is_active)>
                                        Keep active for new expenses
                                    </label>
                                </div>
                                <button type="submit" class="expense-btn soft">Update Subcategory</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="expense-empty-state">No subcategories available yet.</div>
                @endforelse
            </section>
        </div>
    </section>
</div>
@endsection
