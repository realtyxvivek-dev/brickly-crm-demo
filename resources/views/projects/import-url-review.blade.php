@extends('layouts.app')

@section('title', 'Review URL Import - ' . brand_name())
@section('page-title', 'Review Project URL Import')

@section('header-actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('projects.import-url.index') }}" class="px-4 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4] transition-colors duration-200 text-sm font-medium">
            Import Another URL
        </a>
        @php
            $headerGalleryAssets = collect($review['assets'] ?? [])->filter(fn ($asset) => ($asset['asset_type'] ?? null) === 'gallery_image')->values();
        @endphp
        @if($headerGalleryAssets->count() > 0)
            <a href="{{ route('projects.import-url.download-images', $review['token']) }}" class="px-4 py-2 bg-[#F3F7F4] border border-[#205A44]/10 text-[#205A44] rounded-lg hover:bg-[#EAF2ED] transition-colors duration-200 text-sm font-medium">
                Download Images ({{ $headerGalleryAssets->count() }})
            </a>
        @endif
        <a href="{{ route('projects.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
            Back to Projects
        </a>
    </div>
@endsection

@push('styles')
<style>
    .review-shell {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        gap: 24px;
    }
    .review-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 12px 34px rgba(6, 58, 28, 0.05);
    }
    .stage-chip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 16px;
        background: #f8faf8;
        border: 1px solid #e5efe7;
        font-size: 13px;
    }
    .stage-chip.is-done {
        background: #f3f7f4;
        color: #205A44;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .summary-card {
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: #fafaf9;
        padding: 18px;
    }
    .section-card {
        border-radius: 24px;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 24px;
    }
    .field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 18px;
    }
    .field-block label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #51695c;
        margin-bottom: 8px;
    }
    .field-block input,
    .field-block textarea,
    .field-block select {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 16px;
        padding: 12px 14px;
        background: #fff;
        font-size: 14px;
    }
    .field-block textarea {
        min-height: 110px;
        resize: vertical;
    }
    .confidence-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: none;
    }
    .confidence-badge.high {
        background: #eaf6ee;
        color: #205A44;
    }
    .confidence-badge.medium {
        background: #fff7e6;
        color: #a16207;
    }
    .confidence-badge.review {
        background: #fff1f2;
        color: #be123c;
    }
    .inline-table {
        width: 100%;
        border-collapse: collapse;
    }
    .inline-table th,
    .inline-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 13px;
        vertical-align: top;
    }
    .inline-table th {
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280;
    }
    .inline-table tr:last-child td {
        border-bottom: none;
    }
    .notice-box {
        border-radius: 16px;
        padding: 16px 18px;
    }
    .notice-box.warning {
        background: #fffbea;
        border: 1px solid #fde68a;
        color: #92400e;
    }
    .notice-box.error {
        background: #fff5f5;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    @media (max-width: 1200px) {
        .review-shell {
            grid-template-columns: 1fr;
        }
        .summary-grid,
        .field-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@php
    $reviewToken = $review['token'];
    $project = $review['project'];
    $pricing = $review['pricing'];
    $variants = $review['variants'];
    $assets = $review['assets'];
    $landmarks = $review['landmarks'];
    $amenities = $review['amenities'];
    $confidence = $review['confidence'];
    $requiredFields = ['builder_name', 'project_name', 'city', 'area'];
    $galleryAssets = collect($assets)->filter(fn ($asset) => ($asset['asset_type'] ?? null) === 'gallery_image')->values();
@endphp

@section('content')
<div class="review-shell">
    <div class="space-y-6">
        <div class="review-card">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Extraction Summary</h2>
                    <p class="text-sm text-gray-500 mt-2">{{ ucfirst($review['source_key']) }} detected</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-semibold text-[#205A44]">{{ $review['progress_percent'] }}%</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $review['progress_stage'] }}</div>
                </div>
            </div>

            <div class="space-y-3">
                @foreach($review['progress_steps'] as $step)
                    <div class="stage-chip {{ $step['status'] === 'done' ? 'is-done' : '' }}">
                        <span>{{ $step['label'] }}</span>
                        <span>{{ $step['percent'] }}%</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 text-xs text-gray-500 break-all">
                {{ $review['normalized_url'] }}
            </div>
        </div>

        @if(count($review['warnings']) > 0)
            <div class="review-card notice-box warning">
                <div class="font-semibold mb-2">Warnings</div>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    @foreach($review['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(count($review['errors']) > 0)
            <div class="review-card notice-box error">
                <div class="font-semibold mb-2">Errors</div>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    @foreach($review['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Extracted Fields</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['extracted_fields'] }}</div>
            </div>
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Needs Review</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['needs_review'] }}</div>
            </div>
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Warnings</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['warnings'] }}</div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Variants</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['variants'] }}</div>
            </div>
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Media Assets</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['assets'] }}</div>
            </div>
            <div class="summary-card">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Amenities</div>
                <div class="text-2xl font-semibold text-gray-900 mt-2">{{ $review['stats']['amenities'] }}</div>
            </div>
        </div>

        <form id="url-import-review-form" class="space-y-6">
            @csrf
            <input type="hidden" name="review_token" value="{{ $reviewToken }}">

            <div class="section-card">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Project Info</h3>
                        <p class="text-sm text-gray-500 mt-2">Create Draft sirf required fields par block hoga. Baaki optional hai.</p>
                    </div>
                </div>

                <div class="field-grid">
                    @foreach([
                        'builder_name' => 'Builder',
                        'project_name' => 'Project Name',
                        'public_title' => 'Public Title',
                        'subtitle' => 'Subtitle',
                        'city' => 'City',
                        'area' => 'Area / Locality',
                        'project_status' => 'Project Status',
                        'possession_date' => 'Possession',
                        'rera_no' => 'RERA',
                        'base_rate_per_sqft' => 'Base Rate / Sq.ft.',
                        'address' => 'Address',
                        'location_summary' => 'Location Summary',
                        'project_highlights' => 'Project Highlights',
                    ] as $field => $label)
                        <div class="field-block">
                            <label>
                                <span>{{ $label }} @if(in_array($field, $requiredFields, true))*@endif</span>
                                @php
                                    $fieldConfidence = $confidence['project.' . $field] ?? 'Needs Review';
                                    $badgeClass = $fieldConfidence === 'High' ? 'high' : ($fieldConfidence === 'Medium' ? 'medium' : 'review');
                                @endphp
                                <span class="confidence-badge {{ $badgeClass }}">{{ $fieldConfidence }}</span>
                            </label>
                            <input name="project[{{ $field }}]" value="{{ old('project.' . $field, $project[$field] ?? '') }}" data-required="{{ in_array($field, $requiredFields, true) ? '1' : '0' }}">
                        </div>
                    @endforeach

                    <div class="field-block" style="grid-column: 1 / -1;">
                        <label>
                            <span>Short Overview</span>
                            @php
                                $fieldConfidence = $confidence['project.short_overview'] ?? 'Medium';
                                $badgeClass = $fieldConfidence === 'High' ? 'high' : ($fieldConfidence === 'Medium' ? 'medium' : 'review');
                            @endphp
                            <span class="confidence-badge {{ $badgeClass }}">{{ $fieldConfidence }}</span>
                        </label>
                        <textarea name="project[short_overview]">{{ old('project.short_overview', $project['short_overview'] ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <h3 class="text-xl font-semibold text-gray-900 mb-5">Pricing & Specs</h3>
                <div class="field-grid">
                    @foreach([
                        'starting_price' => 'Starting Price',
                        'price_range' => 'Price Range',
                        'rate_per_sqft' => 'Rate / Sq.ft.',
                        'size_range' => 'Size Range',
                    ] as $field => $label)
                        <div class="field-block">
                            <label>
                                <span>{{ $label }}</span>
                                @php
                                    $fieldConfidence = $confidence['pricing.' . $field] ?? 'Needs Review';
                                    $badgeClass = $fieldConfidence === 'High' ? 'high' : ($fieldConfidence === 'Medium' ? 'medium' : 'review');
                                @endphp
                                <span class="confidence-badge {{ $badgeClass }}">{{ $fieldConfidence }}</span>
                            </label>
                            <input name="pricing[{{ $field }}]" value="{{ old('pricing.' . $field, $pricing[$field] ?? '') }}">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="section-card">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Unit Variants</h3>
                        <p class="text-sm text-gray-500 mt-2">Weak extraction hone par bhi yahin edit karke draft create kar sakte ho.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="inline-table">
                        <thead>
                            <tr>
                                <th>Unit Type</th>
                                <th>Size</th>
                                <th>Built-up</th>
                                <th>Carpet</th>
                                <th>Manual Price</th>
                                <th>Display Price</th>
                                <th>Price on Request</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($variants as $index => $variant)
                                <tr>
                                    <td><input name="variants[{{ $index }}][unit_type]" value="{{ $variant['unit_type'] }}"></td>
                                    <td><input name="variants[{{ $index }}][size_label]" value="{{ $variant['size_label'] }}"></td>
                                    <td><input name="variants[{{ $index }}][builtup_area_sqft]" value="{{ $variant['builtup_area_sqft'] }}"></td>
                                    <td><input name="variants[{{ $index }}][carpet_area_sqft]" value="{{ $variant['carpet_area_sqft'] }}"></td>
                                    <td><input name="variants[{{ $index }}][manual_price_override]" value="{{ $variant['manual_price_override'] ?? $variant['display_price'] }}"></td>
                                    <td><input value="{{ $variant['display_price'] ?? '' }}" readonly></td>
                                    <td>
                                        <select name="variants[{{ $index }}][is_price_on_request]">
                                            <option value="0" @selected(empty($variant['is_price_on_request']))>No</option>
                                            <option value="1" @selected(!empty($variant['is_price_on_request']))>Yes</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="variants[{{ $index }}][status]">
                                            @foreach(['available' => 'Available', 'hold' => 'Hold', 'sold_out' => 'Sold Out', 'hidden' => 'Hidden'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($variant['status'] ?? 'available') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="variants[{{ $index }}][visible_on_public_page]" value="{{ !empty($variant['visible_on_public_page']) ? 1 : 0 }}">
                                        <input type="hidden" name="variants[{{ $index }}][is_featured]" value="{{ !empty($variant['is_featured']) ? 1 : 0 }}">
                                        <input type="hidden" name="variants[{{ $index }}][base_rate_per_sqft]" value="{{ $variant['base_rate_per_sqft'] }}">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-gray-500">No variant rows detected. Draft phir bhi create ho sakta hai agar required fields valid hain.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <h3 class="text-xl font-semibold text-gray-900 mb-5">Media Links</h3>
                @if($galleryAssets->count() > 0)
                    <div class="mb-4 flex items-center justify-between gap-4 rounded-2xl border border-[#E5EFE7] bg-[#F8FAF8] px-4 py-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Detected gallery and plan images</div>
                            <div class="text-xs text-gray-500 mt-1">ZIP me saari detected images download kar sakte ho, phir wizard me upload ya verify kar lena.</div>
                        </div>
                        <a href="{{ route('projects.import-url.download-images', $reviewToken) }}" class="px-4 py-2 bg-[#205A44] text-white rounded-lg text-sm font-medium hover:bg-[#184533]">
                            Download Images ZIP
                        </a>
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="inline-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>URL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assets as $index => $asset)
                                <tr>
                                    <td>
                                        <select name="assets[{{ $index }}][asset_type]">
                                            @foreach(['brochure' => 'Brochure', 'video' => 'Video', 'tour_360' => '360 Tour', 'price_sheet' => 'Price Sheet', 'gallery_image' => 'Gallery Image'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($asset['asset_type'] ?? '') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input name="assets[{{ $index }}][title]" value="{{ $asset['title'] }}"></td>
                                    <td><input name="assets[{{ $index }}][external_url]" value="{{ $asset['external_url'] }}"></td>
                                    <input type="hidden" name="assets[{{ $index }}][value]" value="{{ $asset['value'] }}">
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-gray-500">No media links detected. Images/PDFs baad me wizard me upload honge.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <h3 class="text-xl font-semibold text-gray-900 mb-5">Landmarks / Amenities</h3>
                @if(count($amenities) > 0)
                    <div class="mb-5">
                        <div class="text-sm font-semibold text-gray-900 mb-3">Detected Amenities</div>
                        <div class="overflow-x-auto">
                            <table class="inline-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Icon URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($amenities as $amenity)
                                        <tr>
                                            <td>{{ $amenity['name'] }}</td>
                                            <td class="break-all text-xs text-gray-500">{{ $amenity['icon_url'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="inline-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Distance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($landmarks as $index => $landmark)
                                <tr>
                                    <td><input name="landmarks[{{ $index }}][title]" value="{{ $landmark['title'] }}"></td>
                                    <td><input name="landmarks[{{ $index }}][value]" value="{{ $landmark['value'] }}"></td>
                                    <td><input name="landmarks[{{ $index }}][distance]" value="{{ $landmark['distance'] }}"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-gray-500">No landmarks detected. Yeh later wizard se bhi add kiye ja sakte hain.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <div id="draft-readiness" class="text-sm text-gray-500">
                    Required fields valid hote hi partial draft create available rahega.
                </div>
                <button id="create-draft-btn" type="button" class="px-6 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-xl text-sm font-semibold">
                    Create Draft Project
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const reviewForm = document.getElementById('url-import-review-form');
const createDraftBtn = document.getElementById('create-draft-btn');
const readiness = document.getElementById('draft-readiness');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function requiredFieldsValid() {
    const requiredInputs = reviewForm.querySelectorAll('[data-required="1"]');
    return Array.from(requiredInputs).every((input) => input.value.trim() !== '');
}

function updateDraftState() {
    const valid = requiredFieldsValid();
    createDraftBtn.disabled = !valid;
    createDraftBtn.className = valid
        ? 'px-6 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-xl text-sm font-semibold'
        : 'px-6 py-3 bg-gray-300 text-white rounded-xl text-sm font-semibold cursor-not-allowed';
    readiness.textContent = valid
        ? 'Required fields ready hain. Chahe partial data ho, draft create ho jayega.'
        : 'Builder, Project Name, City, aur Area required hain.';
}

reviewForm.addEventListener('input', updateDraftState);
updateDraftState();

createDraftBtn.addEventListener('click', async function () {
    if (!requiredFieldsValid()) {
        return;
    }

    createDraftBtn.disabled = true;
    createDraftBtn.textContent = 'Creating Draft...';

    const formData = new FormData(reviewForm);
    const payload = {
        review_token: formData.get('review_token'),
        project: {},
        pricing: {},
        variants: [],
        assets: [],
        landmarks: [],
    };

    for (const [key, value] of formData.entries()) {
        if (key === 'review_token') {
            continue;
        }

        if (key.startsWith('project[')) {
            payload.project[key.replace(/^project\[|\]$/g, '')] = value;
        } else if (key.startsWith('pricing[')) {
            payload.pricing[key.replace(/^pricing\[|\]$/g, '')] = value;
        } else if (key.startsWith('variants[') || key.startsWith('assets[') || key.startsWith('landmarks[')) {
            assignNested(payload, key, value);
        }
    }

    try {
        const response = await fetch('{{ route('projects.import-url.create') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Draft creation failed.');
        }

        window.location.href = data.wizard_url;
    } catch (error) {
        alert(error.message);
        updateDraftState();
        createDraftBtn.textContent = 'Create Draft Project';
    }
});

function assignNested(target, path, value) {
    const matches = [...path.matchAll(/([a-z_]+)|\[(.*?)\]/gi)].map((match) => match[1] ?? match[2]).filter(Boolean);
    let cursor = target;

    matches.forEach((segment, index) => {
        const isLast = index === matches.length - 1;
        const nextSegment = matches[index + 1];

        if (isLast) {
            cursor[segment] = value;
            return;
        }

        const shouldBeArray = nextSegment !== undefined && /^\d+$/.test(nextSegment);
        if (!(segment in cursor)) {
            cursor[segment] = shouldBeArray ? [] : {};
        }

        cursor = cursor[segment];

        if (Array.isArray(cursor) && /^\d+$/.test(nextSegment) && cursor[Number(nextSegment)] === undefined) {
            cursor[Number(nextSegment)] = {};
        }
    });
}
</script>
@endpush
