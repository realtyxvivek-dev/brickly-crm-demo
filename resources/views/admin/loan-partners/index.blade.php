@extends('layouts.app')

@section('title', 'Loan Partners — Public Profile Network')
@section('page-title', 'Loan Partner Network')

@section('content')
<div class="max-w-7xl mx-auto">
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

    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#063A1C] via-[#205A44] to-[#2f8060] p-6 text-white shadow-xl">
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute -left-10 -bottom-20 h-56 w-56 rounded-full bg-amber-300/10 blur-3xl"></div>
        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider">
                    <i class="fas fa-building-columns"></i> Loan Partner Network
                </div>
                <h1 class="mt-3 text-2xl md:text-3xl font-bold">Home Loan Partner Logos — Central Upload</h1>
                <p class="mt-1 max-w-2xl text-sm text-white/80 leading-relaxed">
                    Yahan jo bank partner add hoga wo <b>sab advisors ke public profile</b> aur <b>project public page</b> par finance section me dikhega.
                    Interest rate optional hai — filled ho to badge me show hoga, warna normal partner badge rahega.
                </p>
            </div>
            <div class="flex flex-wrap gap-4 text-right">
                <div class="rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Total</div>
                    <div class="text-2xl font-bold">{{ $banks->count() }}</div>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Active</div>
                    <div class="text-2xl font-bold">{{ $banks->where('status', 'active')->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    <i class="fas fa-cloud-upload-alt text-[#205A44] mr-1"></i>
                    Add New Loan Partner
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">Transparent PNG best dikhega. Max 1 MB.</p>
            </div>
        </div>

        <form action="{{ route('admin.loan-partners.store') }}" method="POST" enctype="multipart/form-data"
              class="rounded-2xl border border-dashed border-[#205A44]/30 bg-[#f0f8f3]/50 p-4">
            @csrf
            <div class="grid gap-3 md:grid-cols-[180px,1fr,1fr,180px,auto] md:items-end">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Logo *</label>
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg" required
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Bank Name *</label>
                    <input type="text" name="name" required maxlength="150" value="{{ old('name') }}"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                           placeholder="e.g. HDFC Bank">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Short Offer Text</label>
                    <input type="text" name="short_offer_text" maxlength="120" value="{{ old('short_offer_text') }}"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                           placeholder="e.g. Home Loan Available">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-gray-700">Interest Rate Badge</label>
                    <input type="text" name="interest_rate_text" maxlength="60" value="{{ old('interest_rate_text') }}"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                           placeholder="e.g. Starts at 8.50% p.a.">
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#063A1C] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#205A44] transition">
                    <i class="fas fa-plus text-xs"></i> Upload
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">
                <i class="fas fa-landmark text-[#205A44] mr-1"></i>
                Uploaded Loan Partners
            </h2>
            <div class="relative">
                <input type="text" id="loanPartnerSearch" placeholder="Search…"
                       class="rounded-full border border-gray-200 bg-gray-50 px-4 py-2 pl-9 text-xs focus:border-[#205A44] focus:outline-none focus:ring-2 focus:ring-[#205A44]/20">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400"></i>
            </div>
        </div>

        @if($banks->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-14 text-center text-sm text-gray-500">
                <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                Abhi loan partner library khali hai — pehla bank add karein.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4" id="loanPartnerGrid">
                @foreach($banks as $bank)
                    <div class="loan-partner-tile group relative rounded-2xl border border-gray-200 bg-white p-4 flex flex-col items-center text-center hover:shadow-lg hover:border-[#205A44]/40 transition"
                         data-name="{{ strtolower($bank->name) }}">
                        @if($bank->status !== 'active')
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-gray-500">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Inactive
                            </span>
                        @else
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                            </span>
                        @endif

                        <button type="button"
                                onclick="confirmDeleteLoanPartner({{ $bank->id }}, @js($bank->name))"
                                class="absolute top-2 right-2 h-7 w-7 rounded-full bg-white border border-gray-200 text-gray-400 opacity-0 group-hover:opacity-100 transition hover:bg-red-500 hover:text-white hover:border-red-500"
                                title="Delete loan partner">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>

                        <div class="h-16 w-full flex items-center justify-center overflow-hidden mt-2">
                            @if($bank->logo_url)
                                <img src="{{ $bank->logo_url }}" alt="{{ $bank->name }}" class="max-h-16 max-w-full object-contain">
                            @else
                                <i class="fas fa-building-columns text-gray-300 text-3xl"></i>
                            @endif
                        </div>

                        <div class="mt-3 w-full">
                            <div class="text-sm font-bold text-gray-800 truncate" title="{{ $bank->name }}">{{ $bank->name }}</div>
                            @if($bank->short_offer_text)
                                <div class="mt-0.5 text-[10px] text-gray-500 line-clamp-2">{{ $bank->short_offer_text }}</div>
                            @endif
                            <div class="mt-1 inline-flex min-h-[24px] items-center rounded-full {{ $bank->interest_rate_text ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }} px-2.5 py-1 text-[10px] font-semibold tracking-wide">
                                {{ $bank->interest_rate_text ?: 'Partner Badge Fallback' }}
                            </div>
                        </div>

                        <form action="{{ route('admin.loan-partners.update', $bank) }}" method="POST" class="mt-3 w-full space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $bank->name }}" maxlength="150"
                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-[11px] font-medium text-gray-700"
                                   placeholder="Bank name">
                            <input type="text" name="short_offer_text" value="{{ $bank->short_offer_text }}" maxlength="120"
                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-[11px] text-gray-600"
                                   placeholder="Short offer text">
                            <input type="text" name="interest_rate_text" value="{{ $bank->interest_rate_text }}" maxlength="60"
                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-[11px] text-gray-600"
                                   placeholder="Starts at 8.50% p.a.">
                            <input type="hidden" name="status" value="{{ $bank->status }}">
                            <button type="submit"
                                    class="w-full rounded-lg border border-[#205A44]/15 bg-[#f0f8f3] px-2 py-1.5 text-[10px] font-semibold text-[#205A44] transition hover:bg-[#e2f1e8]">
                                <i class="fas fa-floppy-disk mr-1"></i> Save Details
                            </button>
                        </form>

                        <form action="{{ route('admin.loan-partners.update', $bank) }}" method="POST" class="mt-2 w-full">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name" value="{{ $bank->name }}">
                            <input type="hidden" name="short_offer_text" value="{{ $bank->short_offer_text }}">
                            <input type="hidden" name="interest_rate_text" value="{{ $bank->interest_rate_text }}">
                            <input type="hidden" name="status" value="{{ $bank->status === 'active' ? 'inactive' : 'active' }}">
                            <button type="submit"
                                    class="w-full rounded-lg border px-2 py-1.5 text-[10px] font-semibold transition
                                           {{ $bank->status === 'active'
                                              ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                              : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                @if($bank->status === 'active')
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
</div>

<form id="loan-partner-delete-form" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
(function() {
    window.confirmDeleteLoanPartner = function(id, name) {
        if (!confirm('Loan partner "' + name + '" ko permanently delete karna hai?\nYe sabhi advisor profiles se turant hat jayega.')) return;
        const form = document.getElementById('loan-partner-delete-form');
        form.action = @json(url('admin/loan-partners')) + '/' + id;
        form.submit();
    };

    const search = document.getElementById('loanPartnerSearch');
    const grid = document.getElementById('loanPartnerGrid');
    if (search && grid) {
        search.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            grid.querySelectorAll('.loan-partner-tile').forEach(function(el) {
                const name = el.getAttribute('data-name') || '';
                el.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
})();
</script>
@endpush

@endsection
