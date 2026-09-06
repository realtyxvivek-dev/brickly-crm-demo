<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvisorPublicGalleryItem;
use App\Models\AdvisorPublicProfile;
use App\Models\AdvisorPublicReview;
use App\Models\Builder;
use App\Models\LoanPartnerBank;
use App\Services\AdvisorPublicImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdvisorPublicProfileModerationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin,crm']);
    }

    public function index()
    {
        return view('admin.advisor-profiles.index', [
            'profiles' => AdvisorPublicProfile::with('user', 'approvedBy')->latest()->get(),
            'pendingReviews' => AdvisorPublicReview::with('profile.user', 'submittedBy')->where('moderation_status', AdvisorPublicReview::STATUS_PENDING)->latest()->get(),
            'pendingGallery' => AdvisorPublicGalleryItem::with('profile.user')->where('moderation_status', AdvisorPublicGalleryItem::STATUS_PENDING)->latest()->get(),
        ]);
    }

    public function previewProfile(AdvisorPublicProfile $profile)
    {
        $profile->load([
            'user.role',
            'reviews' => fn ($query) => $query
                ->whereIn('moderation_status', [
                    AdvisorPublicReview::STATUS_APPROVED,
                    AdvisorPublicReview::STATUS_PENDING,
                ])
                ->latest('id'),
            'galleryItems' => fn ($query) => $query
                ->whereIn('moderation_status', [
                    AdvisorPublicGalleryItem::STATUS_APPROVED,
                    AdvisorPublicGalleryItem::STATUS_PENDING,
                ])
                ->latest('id'),
        ]);

        $this->attachVisibleBuilders($profile);
        $this->attachActiveLoanPartners($profile);

        return view('advisor.public-show', [
            'profile' => $profile,
            'previewMode' => true,
        ]);
    }

    public function editProfile(AdvisorPublicProfile $profile)
    {
        $profile->load([
            'user.role',
            'reviews' => fn ($query) => $query->latest('id'),
            'galleryItems' => fn ($query) => $query->latest('id'),
            'builders',
        ]);

        // Show active builders for assignment; inactive ones are globally off.
        $allBuilders = Builder::where('status', 'active')
            ->orderBy('name')
            ->get();

        // Map pivot state for this advisor so the edit view can restore toggles.
        // Default state for any builder NOT in the pivot = visible & not featured.
        $builderStateMap = $profile->builders->keyBy('id')->map(fn ($b) => [
            'is_hidden' => (bool) ($b->pivot->is_hidden ?? false),
            'is_featured' => (bool) ($b->pivot->is_featured ?? false),
            'display_order' => (int) ($b->pivot->display_order ?? 0),
        ]);

        return view('admin.advisor-profiles.edit', [
            'profile' => $profile,
            'allBuilders' => $allBuilders,
            'builderStateMap' => $builderStateMap,
        ]);
    }

    public function updateProfile(Request $request, AdvisorPublicProfile $profile, AdvisorPublicImageOptimizer $optimizer)
    {
        $validated = $request->validate([
            'designation' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'languages' => ['nullable', 'string', 'max:255'],
            'service_areas' => ['nullable', 'string', 'max:500'],
            'specialization_tags' => ['nullable', 'array'],
            'specialization_tags.*' => ['nullable', 'string', 'max:60'],
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
            'hidden_builder_ids' => ['nullable', 'array'],
            'hidden_builder_ids.*' => ['integer', 'exists:builders,id'],
            'featured_builder_ids' => ['nullable', 'array'],
            'featured_builder_ids.*' => ['integer', 'exists:builders,id'],
        ]);

        $user = $profile->user;
        if ($request->hasFile('profile_picture') && $user) {
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $user->update([
                'profile_picture' => $optimizer->storeOptimizedUpload($request->file('profile_picture'), 'advisor-profile-pictures'),
            ]);
        }

        $profile->fill([
            'designation' => $validated['designation'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'experience_years' => $validated['experience_years'] ?? null,
            'languages' => $this->explodeCommaText($validated['languages'] ?? ''),
            'service_areas' => $this->explodeCommaText($validated['service_areas'] ?? ''),
            'specialization_tags' => collect($validated['specialization_tags'] ?? [])
                ->filter()
                ->map(fn ($item) => trim((string) $item))
                ->values()
                ->all(),
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
        ]);

        $profile->completion_percentage = $profile->calculateCompletionPercentage();
        $profile->save();

        // New semantics: every active builder shows by default. A pivot row
        // only exists when this advisor has explicitly HIDDEN or FEATURED a
        // given builder. Rows without either flag set are irrelevant, so we
        // drop them — keeps the table small.
        $hiddenIds = collect($validated['hidden_builder_ids'] ?? [])
            ->filter()->map('intval')->unique()->values();
        $featuredIds = collect($validated['featured_builder_ids'] ?? [])
            ->filter()->map('intval')->unique()->values();

        $touchedIds = $hiddenIds->merge($featuredIds)->unique()->values();

        $syncPayload = $touchedIds->mapWithKeys(function ($id, $index) use ($hiddenIds, $featuredIds) {
            return [
                $id => [
                    'is_hidden' => $hiddenIds->contains($id),
                    'is_featured' => $featuredIds->contains($id),
                    'display_order' => $index,
                ],
            ];
        })->all();

        $profile->builders()->sync($syncPayload);

        return redirect()
            ->route('admin.advisor-profiles.edit', $profile)
            ->with('success', 'Advisor profile CRM se update ho gayi.');
    }

    /**
     * Replace the `builders` relation on the given profile with the effective
     * visible list:  ALL active builders EXCEPT those the admin has hidden
     * for this advisor.  `is_featured` from the pivot is preserved so the
     * public-show view can still bucket featured vs. marquee.
     */
    protected function attachVisibleBuilders(AdvisorPublicProfile $profile): void
    {
        $pivotRows = DB::table('advisor_public_profile_builder')
            ->where('advisor_public_profile_id', $profile->id)
            ->get()
            ->keyBy('builder_id');

        $hiddenIds = $pivotRows->filter(fn ($r) => (bool) $r->is_hidden)->keys()->all();

        $builders = Builder::where('status', 'active')
            ->when(!empty($hiddenIds), fn ($q) => $q->whereNotIn('id', $hiddenIds))
            ->orderBy('name')
            ->get()
            ->map(function (Builder $b) use ($pivotRows) {
                $p = $pivotRows->get($b->id);
                $pivot = new \stdClass();
                $pivot->is_hidden = $p ? (bool) $p->is_hidden : false;
                $pivot->is_featured = $p ? (bool) $p->is_featured : false;
                $pivot->display_order = $p ? (int) $p->display_order : 9999;
                // Eloquent's __set routes this to attributes, which __get
                // will return unchanged — public-show view just reads
                // $b->pivot->is_featured.
                $b->pivot = $pivot;
                return $b;
            })
            ->sortBy(fn ($b) => [$b->pivot->is_featured ? 0 : 1, $b->pivot->display_order, strtolower($b->name)])
            ->values();

        $profile->setRelation('builders', $builders);
    }

    protected function attachActiveLoanPartners(AdvisorPublicProfile $profile): void
    {
        $profile->setRelation('loanPartners', LoanPartnerBank::query()
            ->where('status', 'active')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get());
    }

    public function approveProfile(AdvisorPublicProfile $profile)
    {
        $profile->completion_percentage = $profile->calculateCompletionPercentage();
        $profile->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'completion_percentage' => $profile->completion_percentage,
        ]);

        return back()->with('success', 'Advisor profile CRM-approved.');
    }

    public function rejectProfile(AdvisorPublicProfile $profile)
    {
        $profile->update([
            'is_approved' => false,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return back()->with('success', 'Advisor profile hidden.');
    }

    public function approveReview(AdvisorPublicReview $review)
    {
        $review->update([
            'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);

        $this->refreshCompletion($review->profile);

        return back()->with('success', 'Review CRM-approved.');
    }

    public function rejectReview(AdvisorPublicReview $review)
    {
        $review->update([
            'moderation_status' => AdvisorPublicReview::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->refreshCompletion($review->profile);

        return back()->with('success', 'Review rejected.');
    }

    public function approveGalleryItem(AdvisorPublicGalleryItem $item)
    {
        $item->update([
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);

        $this->refreshCompletion($item->profile);

        return back()->with('success', 'Gallery item CRM-approved.');
    }

    public function rejectGalleryItem(AdvisorPublicGalleryItem $item)
    {
        $item->update([
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->refreshCompletion($item->profile);

        return back()->with('success', 'Gallery item rejected.');
    }

    private function refreshCompletion(?AdvisorPublicProfile $profile): void
    {
        if (!$profile) {
            return;
        }

        $profile->update([
            'completion_percentage' => $profile->calculateCompletionPercentage(),
        ]);
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
