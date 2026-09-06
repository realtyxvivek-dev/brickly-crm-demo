@extends('layouts.app')

@section('title', 'Expense Subcategories')
@section('page-title', 'Expense Subcategories')

@section('content')
@php
    $activeSubcategories = $subcategories->where('is_active', true)->count();
@endphp
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <section class="rounded-[28px] border border-[#E5DED4] bg-[radial-gradient(circle_at_top_right,_rgba(32,90,68,0.08),_transparent_34%),linear-gradient(180deg,#FFFDFA_0%,#F8F5EE_100%)] p-7">
        <h2 class="text-[32px] leading-none font-extrabold tracking-[-0.04em] text-brand-primary">Subcategory Master</h2>
        <p class="mt-3 max-w-3xl text-sm text-[#5E6E67]">Detailed heads category ke under map karo. Expense form me category choose karne ke baad sirf related subcategories hi dikhenge.</p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Total Subcategories</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $subcategories->count() }}</div>
            <div class="mt-2 text-sm text-[#697771]">Reusable across all companies</div>
        </div>
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Active</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $activeSubcategories }}</div>
            <div class="mt-2 text-sm text-[#697771]">Available in current expense forms</div>
        </div>
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Categories Linked</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $categories->count() }}</div>
            <div class="mt-2 text-sm text-[#697771]">Parent buckets available</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-5 items-start">
        <section class="rounded-3xl border border-[#E5DED4] bg-white p-6 shadow-sm xl:sticky xl:top-4">
            <div class="mb-5">
                <h3 class="text-2xl font-extrabold tracking-[-0.03em] text-brand-primary">Add Subcategory</h3>
                <p class="mt-2 text-sm text-[#697771]">Parent category sahi choose karo taaki reporting break na ho.</p>
            </div>

            <form method="POST" action="{{ route('admin.expenses.subcategories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Parent Category</label>
                    <select name="expense_category_id" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Subcategory Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Fuel" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="FUEL" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 uppercase text-[#0B2E20]" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}" min="0" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]">
                    </div>
                </div>
                <label class="inline-flex items-center gap-3 text-sm font-semibold text-[#31463D]">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active subcategory
                </label>
                <button type="submit" class="inline-flex rounded-2xl bg-[#205A44] px-5 py-3 font-semibold text-white">Save Subcategory</button>
            </form>
        </section>

        <section class="space-y-4">
            @forelse($subcategories as $subcategory)
                <article class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
                    <div>
                        <h3 class="text-2xl font-extrabold tracking-[-0.03em] text-brand-primary">{{ $subcategory->name }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">{{ $subcategory->category?->name }}</span>
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">Code {{ $subcategory->code }}</span>
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">Sort {{ $subcategory->sort_order }}</span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] {{ $subcategory->is_active ? 'border border-[#C9E8D9] bg-[#EBF7F1] text-[#0F5B42]' : 'border border-[#E7DFD2] bg-[#F6F3EC] text-[#53655D]' }}">{{ $subcategory->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.expenses.subcategories.update', $subcategory) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Category</label>
                                <select name="expense_category_id" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected($subcategory->expense_category_id === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Name</label>
                                <input type="text" name="name" value="{{ $subcategory->name }}" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Code</label>
                                <input type="text" name="code" value="{{ $subcategory->code }}" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 uppercase text-[#0B2E20]" required>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-4">
                                <div>
                                    <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Sort Order</label>
                                    <input type="number" name="sort_order" value="{{ $subcategory->sort_order }}" min="0" class="w-28 rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]">
                                </div>
                                <label class="inline-flex items-center gap-3 text-sm font-semibold text-[#31463D] mt-6">
                                    <input type="checkbox" name="is_active" value="1" @checked($subcategory->is_active)>
                                    Keep active for new expenses
                                </label>
                            </div>
                            <button type="submit" class="inline-flex rounded-2xl border border-[#D8D2C6] px-5 py-3 font-semibold text-brand-primary">Update Subcategory</button>
                        </div>
                    </form>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-[#D6CCBB] bg-[#FFFDF9] p-8 text-center text-[#6B7A74]">No subcategories added yet.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
