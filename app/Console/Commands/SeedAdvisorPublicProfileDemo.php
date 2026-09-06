<?php

namespace App\Console\Commands;

use App\Models\AdvisorPublicGalleryItem;
use App\Models\AdvisorPublicProfile;
use App\Models\AdvisorPublicReview;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedAdvisorPublicProfileDemo extends Command
{
    /** Minimal 1×1 PNG (green tint) for demo assets */
    private const DEMO_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAEgwJ/lVnt0AAAAABJRU5ErkJggg==';

    protected $signature = 'advisor:seed-demo-profile {--email=test@gmail.com : User email (must pass canUseAdvisorPublicProfile)}';

    protected $description = 'Fill dummy advisor public profile + approved gallery/reviews so the public page is viewable';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        if (!$user->canUseAdvisorPublicProfile()) {
            $this->error('This user cannot use the advisor public profile feature (see User::canUseAdvisorPublicProfile).');

            return self::FAILURE;
        }

        $png = (string) base64_decode(self::DEMO_PNG_BASE64, true);
        $disk = Storage::disk('public');
        $profilePic = 'advisor-profile-pictures/demo-seed-' . $user->id . '.png';
        $galleryPaths = [
            'advisor-gallery/demo-seed-' . $user->id . '-a.png',
            'advisor-gallery/demo-seed-' . $user->id . '-b.png',
        ];

        foreach (array_merge([$profilePic], $galleryPaths) as $path) {
            if (!$disk->exists($path)) {
                $disk->put($path, $png);
            }
        }

        DB::transaction(function () use ($user, $profilePic, $galleryPaths) {
            if (!$user->profile_picture) {
                $user->update(['profile_picture' => $profilePic]);
            }

            $slug = Str::slug($user->name ?: 'advisor') . '-' . $user->id;

            $profile = AdvisorPublicProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'public_slug' => $slug,
                    'designation' => 'Senior Property Advisor',
                    'bio' => 'Demo bio: 8+ saal ka experience residential aur commercial real estate mein. '
                        . 'Transparent guidance, site visits, aur end-to-end documentation support.',
                    'experience_years' => 8,
                    'languages' => ['Hindi', 'English'],
                    'service_areas' => ['Gomti Nagar', 'Faizabad Road', 'Sushant Golf City'],
                    'specialization_tags' => ['First-Time Buyer Friendly', 'Investment Advisor', 'Apartment Expert'],
                    'why_choose_me' => 'Clear timelines, honest pricing, aur customer-first approach — demo profile text.',
                    'successful_closures' => 42,
                    'site_visits_handled' => 180,
                    'sqft_sold' => 250000,
                    'happy_families_served' => 65,
                    'is_public' => true,
                    'is_approved' => true,
                    'approved_by' => null,
                    'approved_at' => now(),
                ]
            );

            $profile->galleryItems()->delete();
            $profile->reviews()->delete();

            foreach ([
                [$galleryPaths[0], 'Site visit — demo snapshot A', 'site_visit'],
                [$galleryPaths[1], 'Happy family at handover — demo B', 'booking_moment'],
            ] as $row) {
                [$path, $caption, $category] = $row;
                AdvisorPublicGalleryItem::create([
                    'advisor_public_profile_id' => $profile->id,
                    'lead_id' => null,
                    'image_path' => $path,
                    'caption' => $caption,
                    'category' => $category,
                    'customer_consent_confirmed' => true,
                    'moderation_status' => AdvisorPublicGalleryItem::STATUS_APPROVED,
                    'approved_by' => null,
                    'approved_at' => now(),
                ]);
            }

            $reviews = [
                [
                    'customer_name' => 'Rahul S.',
                    'phone' => '919876543210',
                    'rating' => 5,
                    'text' => 'Bahut clear communication. Site visit pe sahi projects dikhaye. Demo review text.',
                    'project' => 'Demo Heights',
                    'verified' => true,
                ],
                [
                    'customer_name' => 'Neha K.',
                    'phone' => '919811122233',
                    'rating' => 5,
                    'text' => 'Documentation aur loan coordination mein help mili. Recommended — demo.',
                    'project' => null,
                    'verified' => false,
                ],
                [
                    'customer_name' => 'Amit V.',
                    'phone' => '919555566677',
                    'rating' => 4,
                    'text' => 'Professional approach. Thoda time negotiation mein laga but outcome achha — demo.',
                    'project' => 'Sample Township',
                    'verified' => true,
                ],
            ];

            foreach ($reviews as $r) {
                AdvisorPublicReview::create([
                    'advisor_public_profile_id' => $profile->id,
                    'lead_id' => null,
                    'customer_name' => $r['customer_name'],
                    'customer_phone' => $r['phone'],
                    'customer_phone_masked' => '91XXXXXXXX' . substr($r['phone'], -2),
                    'rating' => $r['rating'],
                    'review_text' => $r['text'],
                    'project_name' => $r['project'],
                    'is_verified_customer' => $r['verified'],
                    'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
                    'approved_by' => null,
                    'approved_at' => now(),
                ]);
            }

            $profile->completion_percentage = $profile->calculateCompletionPercentage();
            $profile->save();
        });

        $profile = AdvisorPublicProfile::where('user_id', $user->id)->first();
        $publicUrl = route('advisor.public.show', $profile->public_slug);

        $this->info('Advisor demo profile seeded for: ' . $email);
        $this->line('Public page: ' . $publicUrl);
        $this->line('Completion: ' . $profile->completion_percentage . '% | isPubliclyVisible: ' . ($profile->isPubliclyVisible() ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
