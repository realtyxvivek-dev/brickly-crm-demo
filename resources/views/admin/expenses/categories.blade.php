@extends('layouts.app')

@section('title', 'Expense Categories')
@section('page-title', 'Expense Categories')

@section('content')
@php
    $activeCategories = $categories->where('is_active', true)->count();
    $totalSubcategories = $categories->sum('subcategories_count');
@endphp
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <section class="rounded-[28px] border border-[#E5DED4] bg-[radial-gradient(circle_at_top_right,_rgba(32,90,68,0.08),_transparent_34%),linear-gradient(180deg,#FFFDFA_0%,#F8F5EE_100%)] p-7">
        <h2 class="text-[32px] leading-none font-extrabold tracking-[-0.04em] text-brand-primary">Category Master</h2>
        <p class="mt-3 max-w-3xl text-sm text-[#5E6E67]">Top-level expense buckets yahi define honge. Clean category layer se company-wise summaries aur future mapping stable rehti hai.</p>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Total Categories</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $categories->count() }}</div>
            <div class="mt-2 text-sm text-[#697771]">Shared structure for all companies</div>
        </div>
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Active</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $activeCategories }}</div>
            <div class="mt-2 text-sm text-[#697771]">Available in expense forms</div>
        </div>
        <div class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
            <div class="text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Mapped Subcategories</div>
            <div class="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-brand-primary">{{ $totalSubcategories }}</div>
            <div class="mt-2 text-sm text-[#697771]">Detailed heads linked below</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-5 items-start">
        <section class="rounded-3xl border border-[#E5DED4] bg-white p-6 shadow-sm xl:sticky xl:top-4">
            <div class="mb-5">
                <h3 class="text-2xl font-extrabold tracking-[-0.03em] text-brand-primary">Add Category</h3>
                <p class="mt-2 text-sm text-[#697771]">Concise names aur short codes use karo.</p>
            </div>
            <form method="POST" action="{{ route('admin.expenses.categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Category Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Travel" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="TRAVEL" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 uppercase text-[#0B2E20]" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}" min="0" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]">
                    </div>
                </div>
                <label class="inline-flex items-center gap-3 text-sm font-semibold text-[#31463D]">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active category
                </label>
                <button type="submit" class="inline-flex rounded-2xl bg-[#205A44] px-5 py-3 font-semibold text-white">Save Category</button>
            </form>
        </section>

        <section class="space-y-4">
            @forelse($categories as $category)
                <article class="rounded-3xl border border-[#E5DED4] bg-white p-5 shadow-sm">
                    <div>
                        <h3 class="text-2xl font-extrabold tracking-[-0.03em] text-brand-primary">{{ $category->name }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">Code {{ $category->code }}</span>
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">{{ $category->subcategories_count }} Subheads</span>
                            <span class="rounded-full border border-[#E7DFD2] bg-[#F6F3EC] px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-[#53655D]">Sort {{ $category->sort_order }}</span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] {{ $category->is_active ? 'border border-[#C9E8D9] bg-[#EBF7F1] text-[#0F5B42]' : 'border border-[#E7DFD2] bg-[#F6F3EC] text-[#53655D]' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.expenses.categories.update', $category) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Name</label>
                                <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Code</label>
                                <input type="text" name="code" value="{{ $category->code }}" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 uppercase text-[#0B2E20]" required>
                            </div>
                            <div>
                                <label class="block mb-2 text-[11px] uppercase tracking-[0.18em] font-extrabold text-[#75847D]">Sort Order</label>
                                <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" class="w-full rounded-2xl border border-[#D8D2C6] bg-[#F8F6F0] px-4 py-3 text-[#0B2E20]">
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-3 text-sm font-semibold text-[#31463D]">
                                <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                                Keep active for new expenses
                            </label>
                            <button type="submit" class="inline-flex rounded-2xl border border-[#D8D2C6] px-5 py-3 font-semibold text-brand-primary">Update Category</button>
                        </div>
                    </form>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-[#D6CCBB] bg-[#FFFDF9] p-8 text-center text-[#6B7A74]">No categories added yet.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
