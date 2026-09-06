@extends('layouts.app')

@section('title', 'Builder Logos — Public Profile Partners')
@section('page-title', 'Builder Logo Library')

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            <i class="fas fa-check-circle mt-0.5"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm">
            <div class="font-semibold mb-1">Kuch fix karna hai:</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Hero header --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#063A1C] via-[#205A44] to-[#2f8060] p-6 text-white shadow-xl">
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute -left-10 -bottom-20 h-56 w-56 rounded-full bg-amber-300/10 blur-3xl"></div>
        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider">
                    <i class="fas fa-building"></i> Builder Logo Library
                </div>
                <h1 class="mt-3 text-2xl md:text-3xl font-bold">Builder Partners — Central Upload</h1>
                <p class="mt-1 max-w-2xl text-sm text-white/80 leading-relaxed">
                    Yahan jo logo upload hoga wo <b>by default har advisor ke public profile</b> par dikhega.
                    Agar kisi particular advisor se koi logo hide karna ho to us advisor ki edit screen par jaakar toggle karein.
                </p>
            </div>
            <div class="flex flex-wrap gap-4 text-right">
                <div class="rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Total</div>
                    <div class="text-2xl font-bold">{{ $builders->count() }}</div>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Active</div>
                    <div class="text-2xl font-bold">{{ $builders->where('status', 'active')->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload new builder --}}
    <div class="mt-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    <i class="fas fa-cloud-upload-alt text-[#205A44] mr-1"></i>
                    Add New Builder Logo
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">Transparent PNG best dikhega. Max 1 MB.</p>
            </div>
        </div>

        <form action="{{ route('admin.builder-logos.store') }}" method="POST" enctype="multipart/form-data"
              class="rounded-2xl border border-dashed border-[#205A44]/30 bg-[#f0f8f3]/50 p-4">
            @csrf
            <div class="grid gap-3 md:grid-cols-[200px,1fr,1fr,auto] md:items-end">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Logo *</label>
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg" required
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Builder Name *</label>
                    <input type="text" name="name" required maxlength="150"
                           value="{{ old('name') }}"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                           placeholder="e.g. Lodha Developers">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Tagline (optional)</label>
                    <input type="text" name="description" maxlength="500"
                           value="{{ old('description') }}"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                           placeholder="Luxury homes since 1980">
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#063A1C] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#205A44] transition">
                    <i class="fas fa-plus text-xs"></i> Upload
                </button>
            </div>
        </form>
    </div>

    {{-- Library --}}
    <div class="mt-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">
                <i class="fas fa-th-large text-[#205A44] mr-1"></i>
                Uploaded Builders
            </h2>
            <div class="relative">
                <input type="text" id="builderSearch" placeholder="Search…"
                       class="rounded-full border border-gray-200 bg-gray-50 px-4 py-2 pl-9 text-xs focus:border-[#205A44] focus:outline-none focus:ring-2 focus:ring-[#205A44]/20">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400"></i>
            </div>
        </div>

        @if($builders->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-14 text-center text-sm text-gray-500">
                <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                Abhi library khali hai — pehla builder upload karein.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4" id="builderGrid">
                @foreach($builders as $builder)
                    <div class="builder-tile group relative rounded-2xl border border-gray-200 bg-white p-4 flex flex-col items-center text-center hover:shadow-lg hover:border-[#205A44]/40 transition"
                         data-name="{{ strtolower($builder->name) }}">

                        {{-- Status badge --}}
                        @if($builder->status !== 'active')
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-gray-500">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Inactive
                            </span>
                        @else
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                            </span>
                        @endif

                        {{-- Delete --}}
                        <button type="button"
                                onclick="confirmDeleteBuilderLogo({{ $builder->id }}, @js($builder->name))"
                                class="absolute top-2 right-2 h-7 w-7 rounded-full bg-white border border-gray-200 text-gray-400 opacity-0 group-hover:opacity-100 transition hover:bg-red-500 hover:text-white hover:border-red-500"
                                title="Delete builder">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>

                        <div class="h-16 w-full flex items-center justify-center overflow-hidden mt-2">
                            @if($builder->logo_url)
                                <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}"
                                     class="max-h-16 max-w-full object-contain">
                            @else
                                <i class="fas fa-building text-gray-300 text-3xl"></i>
                            @endif
                        </div>

                        <div class="mt-3 w-full">
                            <div class="text-sm font-bold text-gray-800 truncate" title="{{ $builder->name }}">
                                {{ $builder->name }}
                            </div>
                            @if($builder->description)
                                <div class="mt-0.5 text-[10px] text-gray-500 line-clamp-2">{{ $builder->description }}</div>
                            @endif
                        </div>

                        {{-- Toggle active --}}
                        <form action="{{ route('admin.builder-logos.update', $builder) }}" method="POST"
                              class="mt-2 w-full">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name" value="{{ $builder->name }}">
                            <input type="hidden" name="description" value="{{ $builder->description }}">
                            <input type="hidden" name="status" value="{{ $builder->status === 'active' ? 'inactive' : 'active' }}">
                            <button type="submit"
                                    class="w-full rounded-lg border px-2 py-1.5 text-[10px] font-semibold transition
                                           {{ $builder->status === 'active'
                                              ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                              : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                @if($builder->status === 'active')
                                    <i class="fas fa-eye-slash mr-1"></i> Disable
                                @else
                                    <i class="fas fa-eye mr-1"></i> Enable
                                @endif
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="mt-4 text-center text-xs text-gray-500">
        <i class="fas fa-info-circle mr-1"></i>
        Per-advisor hide karna hai? &nbsp;<a href="{{ route('admin.advisor-profiles.index') }}" class="font-semibold text-[#205A44] underline">Advisor Profiles</a>&nbsp; par jaakar us advisor ki <b>Edit</b> screen khol ke toggle karein.
    </p>
</div>

{{-- Hidden delete form --}}
<form id="builder-logo-delete-form" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
(function() {
    window.confirmDeleteBuilderLogo = function(id, name) {
        if (!confirm('Builder "' + name + '" ko permanently delete karna hai?\nYe sabhi advisor profiles se turant hat jayega.')) return;
        const f = document.getElementById('builder-logo-delete-form');
        f.action = @json(url('admin/builder-logos')) + '/' + id;
        f.submit();
    };

    const search = document.getElementById('builderSearch');
    const grid = document.getElementById('builderGrid');
    if (search && grid) {
        search.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            grid.querySelectorAll('.builder-tile').forEach(function(el) {
                const n = el.getAttribute('data-name') || '';
                el.style.display = (!q || n.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
})();
</script>
@endpush

@endsection
