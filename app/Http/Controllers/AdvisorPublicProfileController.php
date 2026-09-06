<?php

namespace App\Http\Controllers;

use App\Helpers\CollateralHelper;
use App\Models\AdvisorPublicGalleryItem;
use App\Models\AdvisorPublicProfile;
use App\Models\AdvisorPublicReview;
use App\Services\AdvisorPublicImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AdvisorPublicProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show()
    {
        $user = auth()->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);

        $profile = $this->resolveProfile($user);

        if (AdvisorPublicProfile::shouldAutoCrmApproveInEnvironment()) {
            $profile->forceFill([
                'is_approved' => true,
                'approved_at' => $profile->approved_at ?? now(),
                'approved_by' => null,
            ]);
        }

        $profile->completion_percentage = $profile->calculateCompletionPercentage();
        $profile->save();

        return view('advisor.profile', [
            'layout' => $this->resolveLayout($user),
            'profile' => $profile->load([
                'reviews' => fn ($query) => $query->latest('id'),
                'galleryItems' => fn ($query) => $query->latest('id'),
            ]),
            'publicLink' => route('advisor.public.show', $profile->public_slug),
            'reviewLink' => route('advisor.public.review.show', $profile->public_slug),
        ]);
    }

    public function update(Request $request, AdvisorPublicImageOptimizer $optimizer)
    {
        $user = $request->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);

        $validated = $request->validate([
            'designation' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'languages' => ['nullable', 'string', 'max:255'],
            'service_areas' => ['nullable', 'string', 'max:500'],
            'why_choose_me' => ['nullable', 'string', 'max:800'],
            'successful_closures' => ['nullable', 'integer', 'min:0'],
            'site_visits_handled' => ['nullable', 'integer', 'min:0'],
            'sqft_sold' => ['nullable', 'integer', 'min:0'],
            'happy_families_served' => ['nullable', 'integer', 'min:0'],
            'investor_portfolio_value' => ['nullable', 'integer', 'min:0'],
            'active_investors' => ['nullable', 'integer', 'min:0'],
            'nri_investors_assisted' => ['nullable', 'integer', 'min:0'],
            'bookings_this_quarter' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $profile = $this->resolveProfile($user);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $user->update([
                'profile_picture' => $optimizer->storeOptimizedUpload($request->file('profile_picture'), 'advisor-profile-pictures'),
            ]);
        }

        $localAuto = AdvisorPublicProfile::shouldAutoCrmApproveInEnvironment();

        $profile->fill(array_merge([
            'designation' => $validated['designation'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'experience_years' => $validated['experience_years'] ?? null,
            'languages' => $this->explodeCommaText($validated['languages'] ?? ''),
            'service_areas' => $this->explodeCommaText($validated['service_areas'] ?? ''),
            'why_choose_me' => $validated['why_choose_me'] ?? null,
            'successful_closures' => $validated['successful_closures'] ?? 0,
            'site_visits_handled' => $validated['site_visits_handled'] ?? 0,
            'sqft_sold' => $validated['sqft_sold'] ?? 0,
            'happy_families_served' => $validated['happy_families_served'] ?? 0,
            'investor_portfolio_value' => $validated['investor_portfolio_value'] ?? 0,
            'active_investors' => $validated['active_investors'] ?? 0,
            'nri_investors_assisted' => $validated['nri_investors_assisted'] ?? 0,
            'bookings_this_quarter' => $validated['bookings_this_quarter'] ?? 0,
            'is_public' => (bool) ($validated['is_public'] ?? false),
        ], $localAuto
            ? ['is_approved' => true, 'approved_by' => null, 'approved_at' => now()]
            : ['is_approved' => false, 'approved_by' => null, 'approved_at' => null]));

        $profile->completion_percentage = $profile->calculateCompletionPercentage();
        $profile->save();

        $success = $localAuto
            ? 'Public profile save ho gayi. Profile photo auto optimize ho gayi hai aur local environment me CRM approval auto apply ho chuki hai.'
            : 'Public profile save ho gayi. Profile photo auto optimize ho gayi hai. CRM approval ke baad hi public page live hoga.';

        return redirect()->route('advisor.profile.show')->with('success', $success);
    }

    public function storeGallery(Request $request, AdvisorPublicImageOptimizer $optimizer)
    {
        $user = $request->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'customer_consent_confirmed' => ['nullable', 'boolean'],
        ]);

        $customerCategories = ['customer_photo', 'with_customer', 'booking_moment'];
        if (in_array($validated['category'] ?? null, $customerCategories, true) && !$request->boolean('customer_consent_confirmed')) {
            return redirect()->route('advisor.profile.show')
                ->withErrors(['customer_consent_confirmed' => 'Customer ke saath wali photos ke liye consent confirmation required hai.'])
                ->withInput();
        }

        $profile = $this->resolveProfile($user);
        $localAuto = AdvisorPublicProfile::shouldAutoCrmApproveInEnvironment();
        $uploadedCount = 0;

        foreach ($request->file('images', []) as $image) {
            $profile->galleryItems()->create([
                'image_path' => $optimizer->storeOptimizedUpload($image, 'advisor-gallery'),
                'caption' => $validated['caption'] ?? null,
                'category' => $validated['category'] ?? null,
                'customer_consent_confirmed' => $request->boolean('customer_consent_confirmed'),
                'moderation_status' => $localAuto ? AdvisorPublicGalleryItem::STATUS_APPROVED : AdvisorPublicGalleryItem::STATUS_PENDING,
                'approved_by' => null,
                'approved_at' => $localAuto ? now() : null,
            ]);

            $uploadedCount++;
        }

        $profile->completion_percentage = $profile->calculateCompletionPercentage();
        $profile->save();

        $msg = $localAuto
            ? $uploadedCount . ' gallery photo upload ho gayi. Images auto optimize ho gayi hain aur local environment me CRM approval auto apply ho chuki hai.'
            : $uploadedCount . ' gallery photo upload ho gayi. Images auto optimize ho gayi hain. CRM approval ke baad public page par dikhegi.';

        return redirect()->route('advisor.profile.show')->with('success', $msg);
    }

    public function destroyGallery(Request $request, AdvisorPublicGalleryItem $item)
    {
        $user = $request->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);
        abort_unless((int) $item->profile?->user_id === (int) $user->id, 403);

        if ($item->image_path && Storage::disk('public')->exists($item->image_path)) {
            Storage::disk('public')->delete($item->image_path);
        }

        $profile = $item->profile;
        $item->delete();

        if ($profile) {
            $profile->completion_percentage = $profile->calculateCompletionPercentage();
            $profile->save();
        }

        return redirect()->route('advisor.profile.show')->with('success', 'Gallery photo delete ho gayi.');
    }

    public function storeTestimonial(Request $request)
    {
        $user = $request->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);

        $profile = $this->resolveProfile($user);
        $activeVideoTestimonials = $profile->reviews()
            ->where('submission_source', AdvisorPublicReview::SOURCE_ADVISOR_PANEL)
            ->where('content_type', AdvisorPublicReview::CONTENT_VIDEO)
            ->where('moderation_status', '!=', AdvisorPublicReview::STATUS_REJECTED)
            ->count();
        $availableSlots = max(0, 5 - $activeVideoTestimonials);

        $validated = $request->validate([
            'video_urls' => [
                'required',
                'array',
                'size:5',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $filledLinks = collect($value)
                        ->map(fn (mixed $url) => trim((string) $url))
                        ->filter();

                    if ($filledLinks->isEmpty()) {
                        $fail('Kam se kam ek YouTube link deni hogi.');
                    }
                },
            ],
            'video_urls.*' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $url = trim((string) $value);
                    if ($url === '') {
                        return;
                    }

                    if (!CollateralHelper::validateYouTubeLink($url)) {
                        $fail('Sirf valid YouTube link allow hai.');
                    }
                },
            ],
            'customer_consent_confirmed' => ['accepted'],
        ]);

        $videoUrls = collect($validated['video_urls'] ?? [])
            ->map(fn (mixed $url) => trim((string) $url))
            ->filter()
            ->values();

        if ($availableSlots <= 0) {
            throw ValidationException::withMessages([
                'video_urls' => 'Aap maximum 5 active video testimonials hi rakh sakte ho.',
            ]);
        }

        if ($videoUrls->count() > $availableSlots) {
            throw ValidationException::withMessages([
                'video_urls' => 'Aap abhi sirf ' . $availableSlots . ' aur video link submit kar sakte ho.',
            ]);
        }

        if ($videoUrls->unique()->count() !== $videoUrls->count()) {
            throw ValidationException::withMessages([
                'video_urls' => 'Duplicate YouTube links allow nahi hain.',
            ]);
        }

        foreach ($videoUrls as $videoUrl) {
            $videoTitle = CollateralHelper::getYouTubeTitle($videoUrl);

            $profile->reviews()->create([
                'customer_name' => 'Client Video Testimonial',
                'customer_phone' => '',
                'customer_phone_masked' => '',
                'rating' => 0,
                'review_text' => '',
                'project_name' => $videoTitle,
                'is_verified_customer' => false,
                'submission_source' => AdvisorPublicReview::SOURCE_ADVISOR_PANEL,
                'content_type' => AdvisorPublicReview::CONTENT_VIDEO,
                'video_url' => $videoUrl,
                'video_platform' => 'youtube',
                'video_thumbnail_url' => CollateralHelper::getYouTubeThumbnailUrl($videoUrl),
                'submitted_by_user_id' => $user->id,
                'customer_consent_confirmed' => true,
                'moderation_status' => AdvisorPublicReview::STATUS_PENDING,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }

        return redirect()->route('advisor.profile.show')->with(
            'success',
            $videoUrls->count() === 1
                ? 'Video testimonial submit ho gayi. CRM approval ke baad public page par dikhegi.'
                : $videoUrls->count() . ' video testimonials submit ho gayi hain. CRM approval ke baad public page par dikhenge.'
        );
    }

    public function destroyTestimonial(Request $request, AdvisorPublicReview $review)
    {
        $user = $request->user()->loadMissing('role');
        abort_unless($user->canUseAdvisorPublicProfile(), 403);
        abort_unless((int) $review->profile?->user_id === (int) $user->id, 403);
        abort_unless($review->submission_source === AdvisorPublicReview::SOURCE_ADVISOR_PANEL, 403);
        abort_unless($review->moderation_status === AdvisorPublicReview::STATUS_PENDING, 403);

        $profile = $review->profile;
        $review->delete();

        if ($profile) {
            $profile->completion_percentage = $profile->calculateCompletionPercentage();
            $profile->save();
        }

        return redirect()->route('advisor.profile.show')->with('success', 'Pending testimonial delete ho gayi.');
    }

    private function resolveProfile($user): AdvisorPublicProfile
    {
        return AdvisorPublicProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'public_slug' => Str::slug($user->name ?: 'advisor') . '-' . $user->id,
                'designation' => $user->getDisplayRoleName(),
                'completion_percentage' => 0,
            ]
        );
    }

    private function resolveLayout($user): string
    {
        if ($user->isSalesExecutive()) {
            return 'sales-executive.layout';
        }

        if ($user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager()) {
            return 'sales-manager.layout';
        }

        return 'layouts.app';
    }

    private function explodeCommaText(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
