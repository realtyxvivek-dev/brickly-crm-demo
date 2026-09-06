@extends('finance-manager.layout')

@section('title', 'Expense Companies')
@section('page_title', 'Expense Companies')
@section('page_subtitle', 'Shared company master for expense entry, reporting, and future finance mapping.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
@php
    $activeCompanies = $companies->where('is_active', true)->count();
@endphp
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-master-shell">
        <div class="expense-master-intro">
            <h2>Company Master</h2>
            <p>Expense entry ka first dropdown yahan se control hoga. Clean naming, short code, aur sort order maintain rakhne se finance reports consistent rahengi.</p>
        </div>

        <div class="expense-stat-grid">
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Total Companies</div>
                <strong>{{ $companies->count() }}</strong>
                <span>Shared across admin and finance</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Active</div>
                <strong>{{ $activeCompanies }}</strong>
                <span>Visible in new expense forms</span>
            </div>
            <div class="expense-stat-card">
                <div class="expense-kpi-label">Inactive</div>
                <strong>{{ $companies->count() - $activeCompanies }}</strong>
                <span>Hidden from fresh entries</span>
            </div>
        </div>

        <div class="expense-master-grid">
            <section class="expense-card expense-master-create">
                <div class="expense-header">
                    <div>
                        <h2>Add Company</h2>
                        <p>Short and stable company records banao taaki reporting aur filtering clean rahe.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('finance-manager.expenses.companies.store') }}" class="expense-inline-form">
                    @csrf
                    <div class="expense-field">
                        <label>Company Name</label>
                        <input class="expense-input" type="text" name="name" value="{{ old('name') }}" placeholder="Base Infra Solution" required>
                    </div>
                    <div class="expense-inline-grid">
                        <div class="expense-field">
                            <label>Code</label>
                            <input class="expense-input" type="text" name="code" value="{{ old('code') }}" placeholder="BIS" required>
                        </div>
                        <div class="expense-field">
                            <label>Sort Order</label>
                            <input class="expense-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}">
                        </div>
                        <div class="expense-field">
                            <label>Status</label>
                            <label class="expense-checkbox" style="min-height:52px;">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active company
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="expense-btn primary">Save Company</button>
                </form>
            </section>

            <section class="expense-master-list">
                @forelse($companies as $company)
                    <article class="expense-master-item">
                        <div class="expense-master-topline">
                            <div>
                                <h3 class="expense-master-title">{{ $company->name }}</h3>
                                <div class="expense-master-meta">
                                    <span class="expense-master-pill">Code {{ $company->code }}</span>
                                    <span class="expense-master-pill">Sort {{ $company->sort_order }}</span>
                                    <span class="expense-master-pill {{ $company->is_active ? 'is-active' : '' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('finance-manager.expenses.companies.update', $company) }}" class="expense-inline-form">
                            @csrf
                            @method('PUT')
                            <div class="expense-inline-grid">
                                <div class="expense-field">
                                    <label>Name</label>
                                    <input class="expense-input" type="text" name="name" value="{{ $company->name }}" required>
                                </div>
                                <div class="expense-field">
                                    <label>Code</label>
                                    <input class="expense-input" type="text" name="code" value="{{ $company->code }}" required>
                                </div>
                                <div class="expense-field">
                                    <label>Sort Order</label>
                                    <input class="expense-input" type="number" min="0" name="sort_order" value="{{ $company->sort_order }}">
                                </div>
                            </div>
                            <div class="expense-inline-actions">
                                <label class="expense-checkbox">
                                    <input type="checkbox" name="is_active" value="1" @checked($company->is_active)>
                                    Keep active for expense entry
                                </label>
                                <button type="submit" class="expense-btn soft">Update Company</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="expense-empty-state">No companies available yet.</div>
                @endforelse
            </section>
        </div>
    </section>
</div>
@endsection
