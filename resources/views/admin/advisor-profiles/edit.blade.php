@extends('layouts.app')

@section('title', 'Edit Advisor Profile')
@section('page-title', 'Edit Advisor Profile')

@section('content')
@php
    $specializationOptions = [
        'Budget Expert',
        'Luxury Specialist',
        'Plot Specialist',
        'Villa Expert',
        'Apartment Expert',
        'First-Time Buyer Friendly',
        'Investment Advisor',
        'Family Home Specialist',
        'Local Area Expert',
    ];
@endphp

<div class="max-w-5xl mx-auto">
    @if($errors->any())
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <div class="font-semibold mb-1">Please fix these fields:</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">{{ $profile->user->name }}</h2>
            <p class="text-sm text-gray-500">{{ $profile->user->email }} | {{ $profile->designation ?: $profile->user->getDisplayRoleName() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.advisor-profiles.preview', $profile) }}" target="_blank" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700">Preview</a>
            <a href="{{ route('admin.advisor-profiles.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Back</a>
        </div>
    </div>

    <form action="{{ route('admin.advisor-profiles.update', $profile) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div class="grid gap-4 lg:grid-cols-[320px,1fr]">
            <div class="space-y-4">
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-4">
                        <img
                            src="{{ $profile->user->profile_picture_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($profile->user->name) . '&background=063A1C&color=ffffff&size=200' }}"
                            alt="{{ $profile->user->name }}"
                            class="h-20 w-20 rounded-2xl border border-gray-200 object-cover"
                        >
                        <div class="min-w-0">
                            <div class="text-lg font-semibold text-gray-900 truncate">{{ $profile->user->name }}</div>
                            <div class="text-sm text-gray-500 truncate">{{ $profile->user->email }}</div>
                            <div class="text-xs text-gray-400 mt-1">Slug: {{ $profile->public_slug }}</div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50 p-4">
                        <div class="text-xs text-blue-600">Company Tenure</div>
                        <div class="mt-1 text-lg font-semibold text-blue-900">{{ $profile->company_tenure_label }}</div>
                        <div class="mt-1 text-[11px] text-blue-700">Ye CRM/system controlled value hai. User account create date ke basis par auto-calculate hoti hai.</div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">Completion</div>
                            <div class="mt-1 font-semibold text-gray-900">{{ $profile->completion_percentage }}%</div>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">Approval</div>
                            <div class="mt-1 font-semibold {{ $profile->is_approved ? 'text-green-700' : 'text-amber-700' }}">
                                {{ $profile->is_approved ? 'Approved' : 'Pending' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">CRM Edit Mode</h3>
                        <p class="text-sm text-gray-500">CRM advisor ke filled profile ko directly correct/update kar sakta hai.</p>
                    </div>
                    <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" {{ old('is_public', $profile->is_public) ? 'checked' : '' }}>
                        Public Requested
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label>
                        <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', $profile->designation) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Senior Property Advisor">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Short Bio</label>
                        <textarea name="bio" rows="4" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Advisor intro">{{ old('bio', $profile->bio) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Total Real Estate Experience (Years)</label>
                        <input type="number" min="0" max="60" name="experience_years" value="{{ old('experience_years', $profile->experience_years) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="5">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Languages</label>
                        <input type="text" name="languages" value="{{ old('languages', implode(', ', $profile->languages ?? [])) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Hindi, English">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Service Areas</label>
                        <input type="text" name="service_areas" value="{{ old('service_areas', implode(', ', $profile->service_areas ?? [])) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Gomti Nagar, Faizabad Road">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Specialization Tags</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($specializationOptions as $option)
                                <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                    <input type="checkbox" name="specialization_tags[]" value="{{ $option }}" class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" {{ in_array($option, old('specialization_tags', $profile->specialization_tags ?? []), true) ? 'checked' : '' }}>
                                    {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Builder Partners: per-advisor visibility toggle --}}
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800">
                                    <i class="fas fa-building mr-1 text-[#205A44]"></i>
                                    Builder Partners
                                    <span class="ml-1.5 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                        Default: All visible
                                    </span>
                                </label>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    By default har builder logo is advisor ke public profile par dikhega.
                                    <b class="text-gray-700">Eye icon</b> dabake kisi ko hide karo, <b class="text-amber-600">⭐</b> se featured card mein pin karo.
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-[11px] font-semibold">
                                <span class="text-emerald-600"><span id="visibleBuilderCount">0</span> visible</span>
                                <span class="text-rose-500"><span id="hiddenBuilderCount">0</span> hidden</span>
                                <span class="text-amber-500">⭐ <span id="featuredBuilderCount">0</span> featured</span>
                            </div>
                        </div>

                        @php
                            $oldHiddenIds = old('hidden_builder_ids', $builderStateMap->filter(fn($v) => $v['is_hidden'])->keys()->map(fn($k) => (int) $k)->all());
                            $oldFeaturedIds = old('featured_builder_ids', $builderStateMap->filter(fn($v) => $v['is_featured'])->keys()->map(fn($k) => (int) $k)->all());
                        @endphp

                        @if($allBuilders->isEmpty())
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                                <i class="fas fa-inbox text-xl text-gray-300 block mb-1"></i>
                                Abhi koi builder library mein nahi hai.
                                <a href="{{ route('admin.builder-logos.index') }}" class="ml-1 font-semibold text-[#205A44] underline">Builder Logos page</a> par jaakar upload karein.
                            </div>
                        @else
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5" data-builder-pill-grid>
                                @foreach($allBuilders as $builder)
                                    @php
                                        $isHidden = in_array((int) $builder->id, array_map('intval', $oldHiddenIds), true);
                                        $isFeatured = in_array((int) $builder->id, array_map('intval', $oldFeaturedIds), true);
                                    @endphp
                                    <div class="builder-pill relative flex items-center gap-2.5 rounded-2xl border bg-white px-3 py-2.5 transition hover:shadow-sm {{ $isHidden ? 'border-rose-200 bg-rose-50/40 opacity-75' : 'border-emerald-200 bg-[#f0f8f3]' }}"
                                         data-builder-pill>
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-white border border-gray-100 overflow-hidden {{ $isHidden ? 'grayscale opacity-60' : '' }}">
                                            @if($builder->logo_url)
                                                <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}" class="h-full w-full object-contain">
                                            @else
                                                <i class="fas fa-building text-gray-300 text-sm"></i>
                                            @endif
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-semibold text-gray-800 truncate">{{ $builder->name }}</span>
                                            <span class="text-[10px] font-semibold" data-builder-state-label>
                                                @if($isHidden)
                                                    <span class="text-rose-500"><i class="fas fa-eye-slash mr-0.5"></i> Hidden</span>
                                                @elseif($isFeatured)
                                                    <span class="text-amber-600"><i class="fas fa-star mr-0.5"></i> Featured</span>
                                                @else
                                                    <span class="text-emerald-600"><i class="fas fa-eye mr-0.5"></i> Visible</span>
                                                @endif
                                            </span>
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button type="button"
                                                    class="builder-star flex h-7 w-7 items-center justify-center rounded-full border text-[11px] transition {{ $isFeatured ? 'bg-amber-400 border-amber-400 text-white' : 'bg-white border-gray-200 text-gray-300 hover:text-amber-400 hover:border-amber-300' }}"
                                                    data-builder-star
                                                    title="Mark as Featured"
                                                    aria-pressed="{{ $isFeatured ? 'true' : 'false' }}">
                                                <i class="fas fa-star"></i>
                                            </button>
                                            <button type="button"
                                                    class="builder-eye flex h-7 w-7 items-center justify-center rounded-full border text-[11px] transition {{ $isHidden ? 'bg-rose-500 border-rose-500 text-white' : 'bg-white border-gray-200 text-gray-400 hover:text-rose-500 hover:border-rose-300' }}"
                                                    data-builder-eye
                                                    title="Hide from this advisor"
                                                    aria-pressed="{{ $isHidden ? 'true' : 'false' }}">
                                                <i class="fas {{ $isHidden ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                            </button>
                                        </div>
                                        <input type="checkbox"
                                               name="hidden_builder_ids[]"
                                               value="{{ $builder->id }}"
                                               class="hidden"
                                               data-builder-hidden-input
                                               {{ $isHidden ? 'checked' : '' }}>
                                        <input type="checkbox"
                                               name="featured_builder_ids[]"
                                               value="{{ $builder->id }}"
                                               class="hidden"
                                               data-builder-featured-input
                                               {{ $isFeatured ? 'checked' : '' }}>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-3 rounded-xl bg-gray-50 border border-gray-200 px-3 py-2 text-[11px] text-gray-600">
                            <i class="fas fa-lightbulb text-amber-500 mr-1"></i>
                            Naya builder add karna hai? &nbsp;<a href="{{ route('admin.builder-logos.index') }}" class="font-semibold text-[#205A44] underline">Builder Logos library</a> mein jaakar ek baar upload kar do — har advisor par automatically aa jayega.
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Why Choose Me?</label>
                        <textarea name="why_choose_me" rows="3" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Advisor value proposition">{{ old('why_choose_me', $profile->why_choose_me) }}</textarea>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="mb-3 text-lg font-semibold text-gray-900">Stats</h4>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Successful Closures</label>
                            <input type="number" min="0" name="successful_closures" value="{{ old('successful_closures', $profile->successful_closures) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Site Visits Handled</label>
                            <input type="number" min="0" name="site_visits_handled" value="{{ old('site_visits_handled', $profile->site_visits_handled) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Sq.Ft Sold</label>
                            <input type="number" min="0" name="sqft_sold" value="{{ old('sqft_sold', $profile->sqft_sold) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Happy Families Served</label>
                            <input type="number" min="0" name="happy_families_served" value="{{ old('happy_families_served', $profile->happy_families_served) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Investor Portfolio Value</label>
                            <input type="number" min="0" name="investor_portfolio_value" value="{{ old('investor_portfolio_value', $profile->investor_portfolio_value) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Active Investors</label>
                            <input type="number" min="0" name="active_investors" value="{{ old('active_investors', $profile->active_investors) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">NRI Investors Assisted</label>
                            <input type="number" min="0" name="nri_investors_assisted" value="{{ old('nri_investors_assisted', $profile->nri_investors_assisted) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Bookings This Quarter</label>
                            <input type="number" min="0" name="bookings_this_quarter" value="{{ old('bookings_this_quarter', $profile->bookings_this_quarter) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('admin.advisor-profiles.index') }}" class="inline-flex items-center rounded-2xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700">Cancel</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#205A44]">
                        Save CRM Changes
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Shortcut to centralised library --}}
    <div class="mt-4 rounded-3xl border border-[#205A44]/20 bg-gradient-to-r from-[#f0f8f3] to-white p-5 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#205A44] text-white">
                <i class="fas fa-building"></i>
            </span>
            <div>
                <div class="text-sm font-bold text-gray-900">Builder Logo Library</div>
                <div class="text-xs text-gray-500">New builders yahan se upload karein — sabhi advisors par apply ho jayenge.</div>
            </div>
        </div>
        <a href="{{ route('admin.builder-logos.index') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-[#063A1C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#205A44] transition">
            <i class="fas fa-external-link-alt text-[11px]"></i> Open Library
        </a>
    </div>
</div>

@push('scripts')
<script>
    (function() {
        const pillGrid = document.querySelector('[data-builder-pill-grid]');
        if (!pillGrid) return;

        const visibleCountEl = document.getElementById('visibleBuilderCount');
        const hiddenCountEl  = document.getElementById('hiddenBuilderCount');
        const featuredCountEl = document.getElementById('featuredBuilderCount');

        const pills = pillGrid.querySelectorAll('[data-builder-pill]');

        function recount() {
            let hidden = 0, featured = 0;
            pills.forEach(p => {
                const h = p.querySelector('[data-builder-hidden-input]');
                const f = p.querySelector('[data-builder-featured-input]');
                if (h && h.checked) hidden++;
                if (f && f.checked)  featured++;
            });
            if (hiddenCountEl)   hiddenCountEl.textContent   = hidden;
            if (visibleCountEl)  visibleCountEl.textContent  = (pills.length - hidden);
            if (featuredCountEl) featuredCountEl.textContent = featured;
        }

        function paintPill(pill) {
            const hiddenInput   = pill.querySelector('[data-builder-hidden-input]');
            const featuredInput = pill.querySelector('[data-builder-featured-input]');
            const eyeBtn  = pill.querySelector('[data-builder-eye]');
            const starBtn = pill.querySelector('[data-builder-star]');
            const stateLbl = pill.querySelector('[data-builder-state-label]');
            const logoBox = pill.querySelector('span.flex.h-10');

            const isHidden   = hiddenInput && hiddenInput.checked;
            const isFeatured = featuredInput && featuredInput.checked;

            // pill border / bg
            pill.classList.remove('border-rose-200', 'bg-rose-50/40', 'opacity-75',
                                  'border-emerald-200', 'bg-[#f0f8f3]');
            if (isHidden) {
                pill.classList.add('border-rose-200', 'bg-rose-50/40', 'opacity-75');
            } else {
                pill.classList.add('border-emerald-200', 'bg-[#f0f8f3]');
            }

            // logo greyscale
            if (logoBox) {
                logoBox.classList.toggle('grayscale', !!isHidden);
                logoBox.classList.toggle('opacity-60', !!isHidden);
            }

            // eye button
            if (eyeBtn) {
                const icon = eyeBtn.querySelector('i');
                eyeBtn.classList.toggle('bg-rose-500',   !!isHidden);
                eyeBtn.classList.toggle('border-rose-500', !!isHidden);
                eyeBtn.classList.toggle('text-white',    !!isHidden);
                eyeBtn.classList.toggle('bg-white',      !isHidden);
                eyeBtn.classList.toggle('border-gray-200', !isHidden);
                eyeBtn.classList.toggle('text-gray-400', !isHidden);
                eyeBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                if (icon) icon.className = 'fas ' + (isHidden ? 'fa-eye-slash' : 'fa-eye');
            }

            // star button
            if (starBtn) {
                starBtn.classList.toggle('bg-amber-400',    !!isFeatured);
                starBtn.classList.toggle('border-amber-400', !!isFeatured);
                starBtn.classList.toggle('text-white',      !!isFeatured);
                starBtn.classList.toggle('bg-white',        !isFeatured);
                starBtn.classList.toggle('border-gray-200', !isFeatured);
                starBtn.classList.toggle('text-gray-300',   !isFeatured);
                starBtn.setAttribute('aria-pressed', isFeatured ? 'true' : 'false');
            }

            // state label
            if (stateLbl) {
                if (isHidden) {
                    stateLbl.innerHTML = '<span class="text-rose-500"><i class="fas fa-eye-slash mr-0.5"></i> Hidden</span>';
                } else if (isFeatured) {
                    stateLbl.innerHTML = '<span class="text-amber-600"><i class="fas fa-star mr-0.5"></i> Featured</span>';
                } else {
                    stateLbl.innerHTML = '<span class="text-emerald-600"><i class="fas fa-eye mr-0.5"></i> Visible</span>';
                }
            }
        }

        pills.forEach(pill => {
            const hiddenInput   = pill.querySelector('[data-builder-hidden-input]');
            const featuredInput = pill.querySelector('[data-builder-featured-input]');
            const eyeBtn  = pill.querySelector('[data-builder-eye]');
            const starBtn = pill.querySelector('[data-builder-star]');

            if (eyeBtn && hiddenInput) {
                eyeBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    hiddenInput.checked = !hiddenInput.checked;
                    // Hidden + Featured together doesn't make sense -> unfeature.
                    if (hiddenInput.checked && featuredInput && featuredInput.checked) {
                        featuredInput.checked = false;
                    }
                    paintPill(pill); recount();
                });
            }

            if (starBtn && featuredInput) {
                starBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    featuredInput.checked = !featuredInput.checked;
                    // Auto-unhide if featuring a hidden one.
                    if (featuredInput.checked && hiddenInput && hiddenInput.checked) {
                        hiddenInput.checked = false;
                    }
                    paintPill(pill); recount();
                });
            }
        });

        recount();
    })();
</script>
@endpush

@endsection
