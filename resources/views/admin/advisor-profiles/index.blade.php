@extends('layouts.app')

@section('title', 'Advisor Profiles — CRM')
@section('page-title', 'Advisor Profiles — CRM')

@section('content')
<div class="max-w-7xl mx-auto">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Profiles</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $profiles->count() }}</div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Pending Reviews</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $pendingReviews->count() }}</div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Pending Gallery</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $pendingGallery->count() }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900">CRM — Profile approval</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Advisor</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Completion</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Public Requested</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Approval</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($profiles as $profile)
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <div class="font-semibold text-gray-900">{{ $profile->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $profile->user->email }}</div>
                                <div class="text-xs text-gray-500 mt-1">Slug: {{ $profile->public_slug }}</div>
                            </td>
                            <td class="px-4 py-4 align-top text-gray-700">{{ $profile->completion_percentage }}%</td>
                            <td class="px-4 py-4 align-top">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $profile->is_public ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $profile->is_public ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $profile->is_approved ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $profile->is_approved ? 'Approved' : 'Pending' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.advisor-profiles.edit', $profile) }}" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700">Edit</a>
                                    <a href="{{ route('admin.advisor-profiles.preview', $profile) }}" target="_blank" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">Preview</a>
                                    <form action="{{ route('admin.advisor-profiles.approve', $profile) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-[#063A1C] px-3 py-2 text-xs font-semibold text-white">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.advisor-profiles.reject', $profile) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white">Hide</button>
                                    </form>
                                    @if($profile->isPubliclyVisible())
                                        <a href="{{ route('advisor.public.show', $profile->public_slug) }}" target="_blank" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700">Open Live</a>
                                    @else
                                        <span class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500">Not Live Yet</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">No advisor profiles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900">Pending Reviews</h2>
            <div class="mt-4 space-y-4">
                @forelse($pendingReviews as $review)
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $review->customer_name }}</div>
                                <div class="text-xs text-gray-500">Advisor: {{ $review->profile->user->name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                                    <span class="rounded-full bg-white px-2 py-1 font-semibold text-gray-600 border border-gray-200">
                                        {{ ($review->submission_source ?? \App\Models\AdvisorPublicReview::SOURCE_PUBLIC_FORM) === \App\Models\AdvisorPublicReview::SOURCE_ADVISOR_PANEL ? 'Advisor Panel' : 'Public Form' }}
                                    </span>
                                    <span class="rounded-full bg-white px-2 py-1 font-semibold text-gray-600 border border-gray-200">
                                        {{ ($review->content_type ?? \App\Models\AdvisorPublicReview::CONTENT_TEXT) === \App\Models\AdvisorPublicReview::CONTENT_VIDEO ? 'Video' : 'Written' }}
                                    </span>
                                    @if($review->customer_consent_confirmed)
                                        <span class="rounded-full bg-green-100 px-2 py-1 font-semibold text-green-700">Consent Yes</span>
                                    @endif
                                </div>
                                @if($review->customer_phone_masked)
                                    <div class="text-xs text-gray-500 mt-1">{{ $review->customer_phone_masked }}</div>
                                @endif
                                @if($review->submittedBy)
                                    <div class="text-xs text-gray-500 mt-1">Submitted by: {{ $review->submittedBy->name }}</div>
                                @endif
                            </div>
                            @if(($review->content_type ?? \App\Models\AdvisorPublicReview::CONTENT_TEXT) === \App\Models\AdvisorPublicReview::CONTENT_VIDEO)
                                <div class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-700">Video</div>
                            @elseif((int) ($review->rating ?? 0) > 0)
                                <div class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">{{ $review->rating }}/5</div>
                            @endif
                        </div>
                        @if(($review->content_type ?? \App\Models\AdvisorPublicReview::CONTENT_TEXT) === \App\Models\AdvisorPublicReview::CONTENT_VIDEO && $review->video_thumbnail_url)
                            <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                <img src="{{ $review->video_thumbnail_url }}" alt="{{ $review->customer_name }}" class="h-40 w-full object-cover">
                            </div>
                        @endif
                        @if($review->review_text)
                            <p class="mt-3 text-sm text-gray-700">{{ $review->review_text }}</p>
                        @endif
                        @if($review->video_url)
                            <a href="{{ $review->video_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:underline">
                                Open YouTube testimonial <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form action="{{ route('admin.advisor-profiles.reviews.approve', $review) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-xl bg-[#063A1C] px-3 py-2 text-xs font-semibold text-white">Approve</button>
                            </form>
                            <form action="{{ route('admin.advisor-profiles.reviews.reject', $review) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white">Reject</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-gray-500">
                        No pending reviews.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900">Pending Gallery</h2>
            <div class="mt-4 space-y-4">
                @forelse($pendingGallery as $item)
                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <div class="flex gap-4">
                            <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->caption ?: 'Gallery image' }}" class="h-24 w-24 rounded-2xl object-cover">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900">{{ $item->profile->user->name }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ str_replace('_', ' ', $item->category ?: 'gallery') }}</div>
                                @if($item->caption)
                                    <p class="mt-2 text-sm text-gray-700">{{ $item->caption }}</p>
                                @endif
                                @if($item->customer_consent_confirmed)
                                    <div class="mt-2 text-xs text-gray-500">Customer consent confirmed</div>
                                @endif
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <form action="{{ route('admin.advisor-profiles.gallery.approve', $item) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-[#063A1C] px-3 py-2 text-xs font-semibold text-white">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.advisor-profiles.gallery.reject', $item) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-gray-500">
                        No pending gallery items.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
