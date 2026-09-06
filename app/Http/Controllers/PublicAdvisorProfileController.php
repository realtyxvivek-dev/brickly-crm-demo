<?php

namespace App\Http\Controllers;

use App\Models\AdvisorPublicProfile;
use App\Models\AdvisorPublicReview;
use App\Models\Builder;
use App\Models\LoanPartnerBank;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicAdvisorProfileController extends Controller
{
    public function show(string $slug)
    {
        $profile = $this->resolvePublishedProfile($slug);

        $profile->load([
            'user.role',
            'reviews' => fn ($query) => $query
                ->where('moderation_status', AdvisorPublicReview::STATUS_APPROVED)
                ->latest('id'),
            'galleryItems' => fn ($query) => $query
                ->where('moderation_status', \App\Models\AdvisorPublicGalleryItem::STATUS_APPROVED)
                ->latest('id'),
        ]);

        $this->attachVisibleBuilders($profile);
        $this->attachActiveLoanPartners($profile);

        return view('advisor.public-show', [
            'profile' => $profile,
        ]);
    }

    /**
     * Resolve the effective builder list for a public profile:
     * show every active builder EXCEPT the ones the admin has hidden for
     * this specific advisor.  Pivot flags (is_featured) are preserved so the
     * Blade view can still bucket featured cards vs. marquee.
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

    public function showReviewForm(string $slug)
    {
        return view('advisor.review', [
            'profile' => $this->resolvePublishedProfile($slug)->load('user'),
        ]);
    }

    public function downloadContact(string $slug)
    {
        $profile = $this->resolvePublishedProfile($slug)->load('user');
        $user = $profile->user;

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->escapeVcardValue((string) $user->name),
            'N:' . $this->escapeVcardValue((string) $user->name) . ';;;;',
        ];

        if ($profile->designation ?: $user->getDisplayRoleName()) {
            $lines[] = 'TITLE:' . $this->escapeVcardValue((string) ($profile->designation ?: $user->getDisplayRoleName()));
        }

        $lines[] = 'ORG:' . $this->escapeVcardValue((string) config('app.name', 'Base CRM'));

        if (!empty($user->phone)) {
            $lines[] = 'TEL;TYPE=CELL:' . $this->escapeVcardValue((string) $user->phone);
        }

        if (!empty($user->email)) {
            $lines[] = 'EMAIL;TYPE=INTERNET:' . $this->escapeVcardValue((string) $user->email);
        }

        $lines[] = 'URL:' . $this->escapeVcardValue(route('advisor.public.show', $profile->public_slug));
        $lines[] = 'NOTE:' . $this->escapeVcardValue('Public profile: ' . route('advisor.public.show', $profile->public_slug));
        $lines[] = 'END:VCARD';

        $filename = 'advisor-' . $profile->public_slug . '.vcf';

        return response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/vcard; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function submitReview(Request $request, string $slug)
    {
        $profile = $this->resolvePublishedProfile($slug);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_text' => ['required', 'string', 'max:1000'],
            'project_name' => ['nullable', 'string', 'max:120'],
        ]);

        [$leadId, $verified] = $this->resolveLeadVerification($profile, $validated['mobile_number']);

        $localAuto = AdvisorPublicProfile::shouldAutoCrmApproveInEnvironment();

        $reviewPayload = [
            'lead_id' => $leadId,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $this->normalizePhone($validated['mobile_number']),
            'customer_phone_masked' => $this->maskPhone($validated['mobile_number']),
            'rating' => $validated['rating'],
            'review_text' => $validated['review_text'],
            'project_name' => $validated['project_name'] ?? null,
            'is_verified_customer' => $verified,
            'submission_source' => AdvisorPublicReview::SOURCE_PUBLIC_FORM,
            'content_type' => AdvisorPublicReview::CONTENT_TEXT,
            'video_url' => null,
            'video_platform' => null,
            'video_thumbnail_url' => null,
            'submitted_by_user_id' => null,
            'customer_consent_confirmed' => false,
            'moderation_status' => $localAuto ? AdvisorPublicReview::STATUS_APPROVED : AdvisorPublicReview::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => $localAuto ? now() : null,
        ];

        $profile->reviews()->create($reviewPayload);

        if ($localAuto) {
            $profile->completion_percentage = $profile->calculateCompletionPercentage();
            $profile->save();
        }

        $success = $localAuto
            ? 'Review submit ho gayi. Local environment: CRM approval auto — public page par dikhegi.'
            : 'Review submit ho gayi. Verification aur CRM approval ke baad public page par dikhegi.';

        return redirect()
            ->route('advisor.public.review.show', $slug)
            ->with('success', $success);
    }

    private function resolvePublishedProfile(string $slug): AdvisorPublicProfile
    {
        $profile = AdvisorPublicProfile::with('user')->where('public_slug', $slug)->firstOrFail();
        abort_unless($profile->isPubliclyVisible(), 404);

        return $profile;
    }

    private function resolveLeadVerification(AdvisorPublicProfile $profile, string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        $lastTen = substr($normalized, -10);

        $lead = Lead::query()
            ->where(function ($query) use ($normalized, $lastTen) {
                $query->where('phone', $normalized);
                if ($lastTen) {
                    $query->orWhere('phone', 'like', '%' . $lastTen);
                }
            })
            ->where(function ($query) use ($profile) {
                $query->whereHas('assignments', function ($assignmentQuery) use ($profile) {
                    $assignmentQuery->where('assigned_to', $profile->user_id);
                })->orWhere('created_by', $profile->user_id);
            })
            ->latest('id')
            ->first();

        return [$lead?->id, (bool) $lead];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function maskPhone(string $phone): string
    {
        $normalized = $this->normalizePhone($phone);
        $length = strlen($normalized);

        if ($length <= 4) {
            return str_repeat('X', $length);
        }

        return substr($normalized, 0, 2) . str_repeat('X', max(0, $length - 4)) . substr($normalized, -2);
    }

    private function escapeVcardValue(string $value): string
    {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\r", "\n"],
            ["\\\\", '\;', '\,', '\n', '\n', '\n'],
            trim($value)
        );
    }
}
