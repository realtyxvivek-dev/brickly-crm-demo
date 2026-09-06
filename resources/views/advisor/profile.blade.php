@extends($layout)

@section('title', 'My Public Profile')

@section('content')
@php
    $reviewByStatus = $profile->reviews->groupBy('moderation_status');
    $publishedReviewCount = $reviewByStatus->get(\App\Models\AdvisorPublicReview::STATUS_APPROVED)?->count() ?? 0;
    $panelTestimonials = $profile->reviews->where('submission_source', \App\Models\AdvisorPublicReview::SOURCE_ADVISOR_PANEL)->values();
    $panelByStatus = $panelTestimonials->groupBy('moderation_status');
    $videoTestimonials = $panelTestimonials->where('content_type', \App\Models\AdvisorPublicReview::CONTENT_VIDEO)->values();
    $activeVideoTestimonials = $videoTestimonials->where('moderation_status', '!=', \App\Models\AdvisorPublicReview::STATUS_REJECTED)->values();
    $remainingVideoSlots = max(0, 5 - $activeVideoTestimonials->count());
    $videoLinkValues = old('video_urls', array_fill(0, 5, ''));
    if (count($videoLinkValues) < 5) {
        $videoLinkValues = array_pad($videoLinkValues, 5, '');
    }
    $achievementCats = ['achievement', 'certificate'];
    $customerCats = ['with_customer', 'customer_photo', 'booking_moment'];
    $workCats = ['site_visit', 'meeting'];
    $knownCats = array_merge($achievementCats, $customerCats, $workCats);
    $groups = [
        [
            'key' => 'achievements',
            'title' => 'Achievements & Certificates',
            'icon' => 'A',
            'requires_consent' => false,
            'items' => $profile->galleryItems->filter(fn ($g) => in_array($g->category, $achievementCats, true)),
            'options' => [
                'achievement' => 'Achievement / Award',
                'certificate' => 'Certificate',
            ],
        ],
        [
            'key' => 'with_customers',
            'title' => 'With Customers',
            'icon' => 'C',
            'requires_consent' => true,
            'items' => $profile->galleryItems->filter(fn ($g) => in_array($g->category, $customerCats, true)),
            'options' => [
                'with_customer' => 'With Customer',
                'customer_photo' => 'Customer Photo',
                'booking_moment' => 'Booking Moment',
            ],
        ],
        [
            'key' => 'work',
            'title' => 'Work Moments',
            'icon' => 'W',
            'requires_consent' => false,
            'items' => $profile->galleryItems->filter(fn ($g) => in_array($g->category, $workCats, true)),
            'options' => [
                'site_visit' => 'Site Visit',
                'meeting' => 'Meeting',
            ],
        ],
        [
            'key' => 'general',
            'title' => 'General Gallery',
            'icon' => 'G',
            'requires_consent' => false,
            'items' => $profile->galleryItems->filter(fn ($g) => empty($g->category) || !in_array($g->category, $knownCats, true)),
            'options' => [
                'general' => 'General Photo',
                'other' => 'Other Highlight',
            ],
        ],
    ];
@endphp

<div class="max-w-6xl mx-auto px-3 sm:px-0">
    @if(session('success'))
        <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

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

    <div class="grid gap-4 lg:grid-cols-[320px,1fr]">
        <div class="space-y-4">
            <div class="rounded-3xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex items-center gap-4">
                    <img
                        src="{{ auth()->user()->profile_picture_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=063A1C&color=ffffff&size=200' }}"
                        alt="{{ auth()->user()->name }}"
                        class="h-20 w-20 rounded-2xl object-cover border border-gray-200"
                    >
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold text-gray-900 truncate">My Public Profile</h1>
                        <p class="text-sm text-gray-500 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl bg-gray-50 p-4 border border-gray-100">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="font-semibold text-gray-700">Profile Completion</span>
                        <span class="font-bold text-[#063A1C]">{{ $profile->completion_percentage }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-[#063A1C] to-[#205A44]" style="width: {{ min(100, max(0, $profile->completion_percentage)) }}%"></div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Completion score profile quality dikhata hai. Public page CRM approval ke baad live ho sakta hai.
                    </p>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="text-gray-500 text-xs">Profile Status</div>
                        <div class="mt-1 font-semibold {{ $profile->is_public ? 'text-[#205A44]' : 'text-gray-700' }}">
                            {{ $profile->is_public ? 'Public Requested' : 'Draft Only' }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="text-gray-500 text-xs">CRM Approval</div>
                        <div class="mt-1 font-semibold {{ $profile->is_approved ? 'text-green-700' : 'text-amber-700' }}">
                            {{ $profile->is_approved ? 'Approved' : 'Pending' }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="text-gray-500 text-xs">Published Reviews</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $publishedReviewCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="text-gray-500 text-xs">Gallery Items</div>
                        <div class="mt-1 font-semibold text-gray-900">{{ $profile->galleryItems->count() }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-3">
                        <div class="text-xs text-blue-600">Company Tenure</div>
                        <div class="mt-1 text-sm font-semibold text-blue-900">{{ $profile->company_tenure_label }}</div>
                        <div class="mt-1 text-[11px] text-blue-700">Ye field CRM/system se auto aati hai. Aap isse edit nahi kar sakte.</div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="text-xs text-gray-500">Public Profile Link</div>
                            <button type="button" data-copy-text="{{ $publicLink }}" class="rounded-full border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-700 hover:bg-white">Copy Link</button>
                        </div>
                        <div class="text-sm text-[#063A1C] break-all">{{ $publicLink }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs text-gray-500">Profile QR</div>
                                <div class="mt-1 text-sm font-semibold text-gray-900">Scan se public profile open hogi</div>
                            </div>
                            <button type="button" data-qr-download data-qr-filename="advisor-profile-{{ $profile->public_slug }}.png" class="rounded-full border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-700 hover:bg-white">
                                Download QR
                            </button>
                        </div>
                        <div class="mt-3 flex justify-center rounded-2xl border border-dashed border-gray-200 bg-white p-4">
                            <div data-profile-qr data-qr-text="{{ $publicLink }}"></div>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-500">Is QR ko brochure, WhatsApp ya visiting card par use kar sakte ho.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="text-xs text-gray-500">Customer Review Link</div>
                            <button type="button" data-copy-text="{{ $reviewLink }}" class="rounded-full border border-gray-200 px-3 py-1 text-[11px] font-semibold text-gray-700 hover:bg-white">Copy Link</button>
                        </div>
                        <div class="text-sm text-[#063A1C] break-all">{{ $reviewLink }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm border border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900">Review Summary</h2>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-blue-50 border border-blue-100 p-3">
                        <div class="text-xs text-blue-600">Pending</div>
                        <div class="mt-1 text-xl font-bold text-blue-900">{{ $reviewByStatus->get(\App\Models\AdvisorPublicReview::STATUS_PENDING)?->count() ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl bg-green-50 border border-green-100 p-3">
                        <div class="text-xs text-green-600">Published</div>
                        <div class="mt-1 text-xl font-bold text-green-900">{{ $publishedReviewCount }}</div>
                    </div>
                    <div class="rounded-2xl bg-purple-50 border border-purple-100 p-3">
                        <div class="text-xs text-purple-600">Video</div>
                        <div class="mt-1 text-xl font-bold text-purple-900">{{ $videoTestimonials->count() }}</div>
                    </div>
                </div>
                <p class="mt-4 text-xs text-gray-500">
                    Customer written review ke liye review link share karo. Written ya video testimonial aap niche se CRM approval ke liye submit kar sakte ho.
                </p>
            </div>
        </div>

        <div class="space-y-4">
            <form action="{{ route('advisor.profile.update') }}" method="POST" enctype="multipart/form-data" class="rounded-3xl bg-white p-5 shadow-sm border border-gray-100">
                @csrf
                <div class="flex items-center justify-between gap-3 mb-5">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Basic Profile</h2>
                        <p class="text-sm text-gray-500">Simple profile fill karo. CRM approval ke baad ye public ho jayega.</p>
                    </div>
                    <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" {{ old('is_public', $profile->is_public) ? 'checked' : '' }}>
                        Request Public Visibility
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo</label>
                        <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', $profile->designation) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Senior Property Advisor">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Short Bio</label>
                        <textarea name="bio" rows="4" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Apne baare me short intro likho...">{{ old('bio', $profile->bio) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Real Estate Experience (Years)</label>
                        <input type="number" min="0" max="60" name="experience_years" value="{{ old('experience_years', $profile->experience_years) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="5">
                        <p class="mt-1 text-[11px] text-gray-500">Company tenure alag se CRM auto-calculate karta hai.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Languages</label>
                        <input type="text" name="languages" value="{{ old('languages', implode(', ', $profile->languages ?? [])) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Hindi, English">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Areas</label>
                        <input type="text" name="service_areas" value="{{ old('service_areas', implode(', ', $profile->service_areas ?? [])) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Gomti Nagar, Faizabad Road">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Why Choose Me?</label>
                        <textarea name="why_choose_me" rows="3" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Example: Main first-time buyers ko step by step guide karta hoon.">{{ old('why_choose_me', $profile->why_choose_me) }}</textarea>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Stats</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Successful Closures</label>
                            <input type="number" min="0" name="successful_closures" value="{{ old('successful_closures', $profile->successful_closures) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="30">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Site Visits Handled</label>
                            <input type="number" min="0" name="site_visits_handled" value="{{ old('site_visits_handled', $profile->site_visits_handled) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="120">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sq.Ft Sold</label>
                            <input type="number" min="0" name="sqft_sold" value="{{ old('sqft_sold', $profile->sqft_sold) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="15000">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Happy Families Served</label>
                            <input type="number" min="0" name="happy_families_served" value="{{ old('happy_families_served', $profile->happy_families_served) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Investor Portfolio Value</label>
                            <input type="number" min="0" name="investor_portfolio_value" value="{{ old('investor_portfolio_value', $profile->investor_portfolio_value) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="25000000">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Active Investors</label>
                            <input type="number" min="0" name="active_investors" value="{{ old('active_investors', $profile->active_investors) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="24">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">NRI Investors Assisted</label>
                            <input type="number" min="0" name="nri_investors_assisted" value="{{ old('nri_investors_assisted', $profile->nri_investors_assisted) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="12">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bookings This Quarter</label>
                            <input type="number" min="0" name="bookings_this_quarter" value="{{ old('bookings_this_quarter', $profile->bookings_this_quarter) }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="8">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#205A44]">
                        Save Public Profile
                    </button>
                </div>
            </form>

            <div class="rounded-3xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Testimonials</h2>
                        <p class="text-sm text-gray-500">Yaha sirf YouTube video testimonial links submit hongi. Ek profile par maximum 5 active videos rahengi.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700">Pending {{ $panelByStatus->get(\App\Models\AdvisorPublicReview::STATUS_PENDING)?->count() ?? 0 }}</span>
                        <span class="rounded-full bg-green-50 px-3 py-1 font-semibold text-green-700">Approved {{ $panelByStatus->get(\App\Models\AdvisorPublicReview::STATUS_APPROVED)?->count() ?? 0 }}</span>
                        <span class="rounded-full bg-[#eef4ef] px-3 py-1 font-semibold text-[#063A1C]">Slots Left {{ $remainingVideoSlots }}</span>
                    </div>
                </div>

                <form action="{{ route('advisor.profile.testimonials.store') }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Active Video Limit</label>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            {{ $activeVideoTestimonials->count() }}/5 active video testimonials used
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Display Mode</label>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            Public profile par videos generic testimonial label ke saath dikhenge.
                        </div>
                    </div>
                    <div class="md:col-span-2 rounded-3xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">YouTube Links</div>
                                <div class="text-xs text-gray-500">Sirf YouTube ya YouTube Shorts links allow hain. Link 1, Link 2, Link 3 aise fill kar sakte ho.</div>
                            </div>
                            <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#063A1C] border border-[#d5e7dd]">
                                Max 5 links
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3">
                            @foreach($videoLinkValues as $index => $videoLinkValue)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">YouTube Link {{ $index + 1 }}</label>
                                    <input
                                        type="url"
                                        name="video_urls[]"
                                        value="{{ $videoLinkValue }}"
                                        class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm"
                                        placeholder="https://www.youtube.com/watch?v=..."
                                        {{ $remainingVideoSlots === 0 && trim((string) $videoLinkValue) === '' ? 'disabled' : '' }}
                                    >
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            <input type="checkbox" name="customer_consent_confirmed" value="1" class="mt-1 rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" {{ old('customer_consent_confirmed') ? 'checked' : '' }}>
                            <span>I confirm ki customer ne YouTube testimonial share karne ki permission di hai.</span>
                        </label>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#205A44]" {{ $remainingVideoSlots === 0 ? 'disabled' : '' }}>
                            Save Video Testimonials
                        </button>
                    </div>
                </form>

                @if($remainingVideoSlots === 0)
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Aapki 5 active video testimonial slots full hain. Nayi link add karne ke liye pehle CRM rejection ya pending delete ka use hoga.
                    </div>
                @endif

                <div class="mt-6 space-y-3">
                    @forelse($panelTestimonials as $testimonial)
                        <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-semibold text-gray-900">{{ $testimonial->customer_name ?: 'Client Video Testimonial' }}</div>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 border border-gray-200">
                                            {{ ($testimonial->content_type ?? 'text') === 'video' ? 'Video Testimonial' : 'Written Testimonial (Legacy)' }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $testimonial->moderation_status === 'approved' ? 'bg-green-100 text-green-700' : ($testimonial->moderation_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                            {{ ucfirst($testimonial->moderation_status) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ optional($testimonial->created_at)->format('d M Y') }}
                                    </div>
                                </div>
                                @if(($testimonial->content_type ?? 'text') === 'text' && (int) $testimonial->rating > 0)
                                    <div class="rounded-full bg-[#eef4ef] px-3 py-1 text-xs font-semibold text-[#063A1C]">{{ $testimonial->rating }}/5</div>
                                @endif
                            </div>

                            @if(($testimonial->content_type ?? 'text') === 'video' && $testimonial->video_thumbnail_url)
                                <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <div class="relative">
                                        <img src="{{ $testimonial->video_thumbnail_url }}" alt="{{ $testimonial->customer_name ?: 'Client Video Testimonial' }}" class="h-40 w-full object-cover">
                                        <a href="{{ $testimonial->video_url }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-white/90 text-[#063A1C] shadow-lg">
                                                <i class="fas fa-play ml-1"></i>
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if($testimonial->review_text)
                                <p class="mt-3 text-sm text-gray-700">{{ $testimonial->review_text }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="text-xs text-gray-500">
                                    {{ $testimonial->moderation_status === 'approved' ? 'CRM approved and live.' : ($testimonial->moderation_status === 'rejected' ? 'CRM rejected. Zarurat ho to naya testimonial submit karo.' : 'CRM approval pending.') }}
                                </div>
                                @if($testimonial->moderation_status === \App\Models\AdvisorPublicReview::STATUS_PENDING)
                                    <form action="{{ route('advisor.profile.testimonials.destroy', $testimonial) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" onclick="return confirm('Is pending testimonial ko delete karna hai?')">
                                            Delete Pending
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-5 text-center text-sm text-gray-500">
                            Abhi tak koi self-submitted testimonial nahi hai.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="mb-5">
                    <h2 class="text-xl font-semibold text-gray-900">Gallery</h2>
                    <p class="text-sm text-gray-500">Ab har section me direct upload available hai. Upload ke baad images auto optimize hokar lightweight WebP me save hongi.</p>
                </div>

                @foreach($groups as $group)
                    <div class="mt-6 first:mt-0">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $group['icon'] }} {{ $group['title'] }} <span class="text-xs text-gray-400 font-normal">({{ $group['items']->count() }})</span></h3>
                        </div>

                        @if($group['items']->isEmpty())
                            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-4">
                                <div class="text-center text-sm text-gray-500">Is section mein abhi koi image nahi.</div>
                                <form action="{{ route('advisor.profile.gallery.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Upload Images</label>
                                        <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Section Category</label>
                                        <select name="category" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm">
                                            @foreach($group['options'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Caption</label>
                                        <input type="text" name="caption" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm" placeholder="{{ $group['title'] }} ke liye short caption">
                                    </div>
                                    @if($group['requires_consent'])
                                        <div class="md:col-span-2">
                                            <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                                <input type="checkbox" name="customer_consent_confirmed" value="1" class="mt-1 rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                                                <span>I confirm customer consent for these photos.</span>
                                            </label>
                                        </div>
                                    @endif
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#205A44]">
                                            Upload In {{ $group['title'] }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @else
                            <div class="mb-4 rounded-2xl border border-gray-100 bg-gray-50 p-4">
                                <form action="{{ route('advisor.profile.gallery.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1.2fr,1fr,auto]">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Add More Images</label>
                                        <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                        <select name="category" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm">
                                            @foreach($group['options'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white hover:bg-[#205A44]">
                                            Upload
                                        </button>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Caption</label>
                                        <input type="text" name="caption" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm" placeholder="{{ $group['title'] }} ke liye short caption">
                                    </div>
                                    @if($group['requires_consent'])
                                        <div class="md:col-span-3">
                                            <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                                <input type="checkbox" name="customer_consent_confirmed" value="1" class="mt-1 rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                                                <span>I confirm customer consent for these photos.</span>
                                            </label>
                                        </div>
                                    @endif
                                </form>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                @foreach($group['items'] as $item)
                                    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-gray-50">
                                        <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->caption ?: 'Gallery image' }}" class="h-40 w-full object-cover">
                                        <div class="p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $item->category ?: 'gallery') }}</span>
                                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $item->moderation_status === 'approved' ? 'bg-green-100 text-green-700' : ($item->moderation_status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                                    {{ ucfirst($item->moderation_status) }}
                                                </span>
                                            </div>
                                            @if($item->caption)
                                                <p class="mt-2 text-sm text-gray-700">{{ $item->caption }}</p>
                                            @endif
                                            @if($item->customer_consent_confirmed)
                                                <p class="mt-2 text-xs text-gray-500">Customer consent confirmed</p>
                                            @endif
                                            <form action="{{ route('advisor.profile.gallery.destroy', $item) }}" method="POST" class="mt-3">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" onclick="return confirm('Is photo ko delete karna hai?')">
                                                    Delete Photo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-copy-text]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(button.getAttribute('data-copy-text') || '');
                button.textContent = 'Copied';
            } catch (error) {
                button.textContent = 'Copy failed';
            }

            setTimeout(function () {
                button.textContent = original;
            }, 1800);
        });
    });

    const qrContainer = document.querySelector('[data-profile-qr]');
    const qrDownloadButton = document.querySelector('[data-qr-download]');

    if (qrContainer && typeof QRCode !== 'undefined') {
        const qrText = qrContainer.getAttribute('data-qr-text') || '';
        new QRCode(qrContainer, {
            text: qrText,
            width: 176,
            height: 176,
            colorDark: '#063A1C',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });

        if (qrDownloadButton) {
            qrDownloadButton.addEventListener('click', function () {
                const canvas = qrContainer.querySelector('canvas');
                const image = qrContainer.querySelector('img');
                const source = canvas ? canvas.toDataURL('image/png') : (image ? image.src : null);

                if (!source) {
                    return;
                }

                const link = document.createElement('a');
                link.href = source;
                link.download = qrDownloadButton.getAttribute('data-qr-filename') || 'profile-qr.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    } else if (qrContainer) {
        qrContainer.innerHTML = '<div class="text-center text-xs text-gray-500">QR preview load nahi ho paya.</div>';
        if (qrDownloadButton) {
            qrDownloadButton.disabled = true;
            qrDownloadButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
});
</script>
@endsection
