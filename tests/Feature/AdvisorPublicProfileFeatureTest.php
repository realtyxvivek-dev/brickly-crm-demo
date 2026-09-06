<?php

namespace Tests\Feature;

use App\Models\AdvisorPublicGalleryItem;
use App\Models\AdvisorPublicProfile;
use App\Models\AdvisorPublicReview;
use App\Models\LoanPartnerBank;
use App\Models\Role;
use App\Models\User;
use App\Services\AdvisorPublicImageOptimizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvisorPublicProfileFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('app.advisor_public_auto_approve', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_self_service_page_hides_specialization_tags_and_shows_qr_card(): void
    {
        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        $response = $this->actingAs($user)->get(route('advisor.profile.show'));

        $response->assertOk();
        $response->assertDontSee('Specialization Tags');
        $response->assertSee('Profile QR');
        $response->assertSee('Download QR');
        $response->assertSee('General Gallery');
        $response->assertSee('Upload In Achievements & Certificates');
    }

    public function test_public_profile_shows_investor_stat_cards(): void
    {
        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'is_approved' => true,
            'investor_portfolio_value' => 25000000,
            'active_investors' => 18,
            'nri_investors_assisted' => 7,
            'bookings_this_quarter' => 5,
            'completion_percentage' => 0,
        ]);
        $profile->setRelation('user', $user);

        $response = $this->get(route('advisor.public.show', $profile->public_slug));

        $response->assertOk();
        $response->assertSee('Investor Portfolio Value');
        $response->assertSee('Active Investors');
        $response->assertSee('NRI Investors Assisted');
        $response->assertSee('Bookings This Quarter');
    }

    public function test_public_profile_renders_active_loan_partner_section_below_builders(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'phone' => '9999999999',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'is_approved' => true,
            'completion_percentage' => 0,
        ]);

        DB::table('builders')->insert([
            'name' => 'Alpha Builders',
            'logo' => 'alpha.png',
            'description' => 'Trusted homes',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LoanPartnerBank::create([
            'name' => 'Axis Bank',
            'logo' => 'axis.png',
            'short_offer_text' => 'Home Loan Available',
            'status' => 'active',
            'display_order' => 1,
        ]);

        LoanPartnerBank::create([
            'name' => 'Hidden Bank',
            'logo' => 'hidden.png',
            'short_offer_text' => 'Should not render',
            'status' => 'inactive',
            'display_order' => 2,
        ]);

        $response = $this->get(route('advisor.public.show', $profile->public_slug));

        $response->assertOk();
        $response->assertSee('Builders I work with');
        $response->assertSee('Home Loan Available With');
        $response->assertSee('Axis Bank');
        $response->assertSee('Home Loan Available');
        $response->assertSee('Offers subject to bank policy and approval.');
        $response->assertSee('Request Callback');
        $response->assertDontSee('Hidden Bank');
    }

    public function test_admin_can_manage_loan_partner_banks(): void
    {
        Storage::fake('public');

        $admin = $this->createUser($this->createRole(Role::ADMIN));

        $create = $this->actingAs($admin)->post(route('admin.loan-partners.store'), [
            'name' => 'LIC HFL',
            'short_offer_text' => 'Starting from 8.5%*',
            'logo' => $this->fakePngUpload('lic.png'),
        ]);

        $create->assertRedirect(route('admin.loan-partners.index'));
        $this->assertDatabaseHas('loan_partner_banks', [
            'name' => 'LIC HFL',
            'short_offer_text' => 'Starting from 8.5%*',
            'status' => 'active',
        ]);

        $bank = LoanPartnerBank::firstOrFail();

        $update = $this->actingAs($admin)->put(route('admin.loan-partners.update', $bank), [
            'name' => 'LIC HFL',
            'short_offer_text' => 'Balance Transfer Support',
            'status' => 'inactive',
        ]);

        $update->assertRedirect(route('admin.loan-partners.index'));
        $this->assertDatabaseHas('loan_partner_banks', [
            'id' => $bank->id,
            'status' => 'inactive',
            'short_offer_text' => 'Balance Transfer Support',
        ]);

        $delete = $this->actingAs($admin)->delete(route('admin.loan-partners.destroy', $bank));

        $delete->assertRedirect(route('admin.loan-partners.index'));
        $this->assertSoftDeleted('loan_partner_banks', ['id' => $bank->id]);
    }

    public function test_public_profile_shows_unified_media_gallery_with_tags(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'gallery@example.test',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'is_approved' => true,
            'completion_percentage' => 0,
        ]);

        $profile->galleryItems()->create([
            'image_path' => 'advisor-gallery/achievement.jpg',
            'caption' => 'Top broker award night',
            'category' => 'achievement',
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_APPROVED,
        ]);

        $profile->galleryItems()->create([
            'image_path' => 'advisor-gallery/site-visit.jpg',
            'caption' => 'Premium site visit moment',
            'category' => 'site_visit',
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_APPROVED,
        ]);

        $response = $this->get(route('advisor.public.show', $profile->public_slug));

        $response->assertOk();
        $response->assertSee('A journey of');
        $response->assertSee('Handpicked highlights');
        $response->assertSee('Top broker award night');
        $response->assertSee('Achievement');
        $response->assertSee('data-media-showcase', false);
        $response->assertDontSee('Work Moments');
    }

    public function test_public_profile_contact_route_downloads_vcard(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'phone' => '9876543210',
            'email' => 'advisor@example.test',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'designation' => 'Senior Property Advisor',
            'is_approved' => true,
            'completion_percentage' => 0,
        ]);

        $response = $this->get(route('advisor.public.contact', $profile->public_slug));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/vcard; charset=UTF-8');
        $response->assertHeader('content-disposition', 'inline; filename="advisor-' . $profile->public_slug . '.vcf"');
        $response->assertSee('BEGIN:VCARD');
        $response->assertSee('FN:' . $user->name);
        $response->assertSee('TEL;TYPE=CELL:9876543210');
    }

    public function test_asm_can_submit_up_to_five_video_testimonials_from_panel(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.testimonials.store'), [
            'video_urls' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'https://www.youtube.com/watch?v=ysz5S6PUM-U',
                '',
                '',
                '',
            ],
            'customer_consent_confirmed' => '1',
        ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHas('success', '2 video testimonials submit ho gayi hain. CRM approval ke baad public page par dikhenge.');

        $this->assertDatabaseCount('advisor_public_reviews', 2);
        $review = AdvisorPublicReview::first();
        $this->assertSame(AdvisorPublicReview::SOURCE_ADVISOR_PANEL, $review->submission_source);
        $this->assertSame(AdvisorPublicReview::CONTENT_VIDEO, $review->content_type);
        $this->assertSame(AdvisorPublicReview::STATUS_PENDING, $review->moderation_status);
        $this->assertSame($user->id, $review->submitted_by_user_id);
    }

    public function test_video_testimonial_requires_valid_youtube_link_and_consent(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->from(route('advisor.profile.show'))
            ->actingAs($user)
            ->post(route('advisor.profile.testimonials.store'), [
                'video_urls' => [
                    'https://example.com/video',
                    '',
                    '',
                    '',
                    '',
                ],
            ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHasErrors(['video_urls.0', 'customer_consent_confirmed']);
        $this->assertDatabaseCount('advisor_public_reviews', 0);
    }

    public function test_video_testimonial_submission_is_limited_to_five_active_links_per_user(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        foreach (range(1, 4) as $i) {
            $profile->reviews()->create([
                'customer_name' => 'Existing Client ' . $i,
                'customer_phone' => '',
                'customer_phone_masked' => '',
                'rating' => 0,
                'review_text' => '',
                'submission_source' => AdvisorPublicReview::SOURCE_ADVISOR_PANEL,
                'content_type' => AdvisorPublicReview::CONTENT_VIDEO,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'video_thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'submitted_by_user_id' => $user->id,
                'customer_consent_confirmed' => true,
                'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
            ]);
        }

        $response = $this->from(route('advisor.profile.show'))
            ->actingAs($user)
            ->post(route('advisor.profile.testimonials.store'), [
                'video_urls' => [
                    'https://www.youtube.com/watch?v=ysz5S6PUM-U',
                    'https://www.youtube.com/watch?v=jNQXAC9IVRw',
                    '',
                    '',
                    '',
                ],
                'customer_consent_confirmed' => '1',
            ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHasErrors(['video_urls']);
        $this->assertDatabaseCount('advisor_public_reviews', 4);
    }

    public function test_profile_page_shows_youtube_only_five_slot_testimonial_form(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('advisor.profile.show'));

        $response->assertOk();
        $response->assertSee('YouTube Links');
        $response->assertSee('YouTube Link 5');
        $response->assertSee('Save Video Testimonials');
        $response->assertDontSee('Customer Name');
        $response->assertDontSee('Project / Context');
        $response->assertDontSee('Written Testimonial');
        $this->assertDatabaseCount('advisor_public_reviews', 0);
    }

    public function test_public_profile_renders_written_reviews_and_video_testimonials_separately(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'is_approved' => true,
            'completion_percentage' => 0,
        ]);

        $profile->reviews()->create([
            'customer_name' => 'Written Client',
            'customer_phone' => '9999999999',
            'customer_phone_masked' => '99XXXXXX99',
            'rating' => 5,
            'review_text' => 'Excellent support and regular updates.',
            'submission_source' => AdvisorPublicReview::SOURCE_PUBLIC_FORM,
            'content_type' => AdvisorPublicReview::CONTENT_TEXT,
            'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
        ]);

        $profile->reviews()->create([
            'customer_name' => 'Video Client',
            'customer_phone' => '',
            'customer_phone_masked' => '',
            'rating' => 0,
            'review_text' => 'Shared a quick testimonial video.',
            'project_name' => 'Oro Constella Construction Update',
            'submission_source' => AdvisorPublicReview::SOURCE_ADVISOR_PANEL,
            'content_type' => AdvisorPublicReview::CONTENT_VIDEO,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'submitted_by_user_id' => $user->id,
            'customer_consent_confirmed' => true,
            'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
        ]);

        $response = $this->get(route('advisor.public.show', $profile->public_slug));

        $response->assertOk();
        $response->assertSee('What Clients Say');
        $response->assertSee('Recent written feedback');
        $response->assertSee('Written Client');
        $response->assertSee('Oro Constella Construction Update');
        $response->assertDontSee('Client Video Stories');
        $response->assertDontSee('Watch recent testimonials');
        $response->assertSee('video-showcase', false);
        $response->assertSee('dQw4w9WgXcQ');
    }

    public function test_public_profile_video_testimonials_show_more_overlay_after_five_items(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'is_approved' => true,
            'completion_percentage' => 0,
        ]);

        foreach (range(1, 6) as $i) {
            $profile->reviews()->create([
                'customer_name' => 'Video Client ' . $i,
                'customer_phone' => '',
                'customer_phone_masked' => '',
                'rating' => 0,
                'review_text' => 'Video testimonial ' . $i,
                'project_name' => 'Video Title ' . $i,
                'submission_source' => AdvisorPublicReview::SOURCE_ADVISOR_PANEL,
                'content_type' => AdvisorPublicReview::CONTENT_VIDEO,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'video_thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'submitted_by_user_id' => $user->id,
                'customer_consent_confirmed' => true,
                'moderation_status' => AdvisorPublicReview::STATUS_APPROVED,
            ]);
        }

        $response = $this->get(route('advisor.public.show', $profile->public_slug));

        $response->assertOk();
        $response->assertSee('+1 more');
        $response->assertSee('Video Client 6');
        $response->assertSee('video-mini-grid', false);
        $response->assertSee('Video Title 2');
    }

    public function test_user_can_delete_only_their_pending_panel_testimonial(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $review = $profile->reviews()->create([
            'customer_name' => 'Pending Client',
            'customer_phone' => '',
            'customer_phone_masked' => '',
            'rating' => 5,
            'review_text' => 'Pending testimonial',
            'submission_source' => AdvisorPublicReview::SOURCE_ADVISOR_PANEL,
            'content_type' => AdvisorPublicReview::CONTENT_VIDEO,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'submitted_by_user_id' => $user->id,
            'customer_consent_confirmed' => true,
            'moderation_status' => AdvisorPublicReview::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.testimonials.destroy', $review));

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHas('success', 'Pending testimonial delete ho gayi.');
        $this->assertDatabaseCount('advisor_public_reviews', 0);
    }

    public function test_self_service_update_cannot_override_specialization_tags(): void
    {
        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'specialization_tags' => ['Budget Expert'],
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.update'), [
            'designation' => 'Senior Property Advisor',
            'specialization_tags' => ['Luxury Specialist', 'Villa Expert'],
        ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $this->assertSame(['Budget Expert'], $profile->fresh()->specialization_tags);
    }

    public function test_crm_can_still_update_specialization_tags(): void
    {
        $this->mockOptimizer();

        $crm = $this->createUser($this->createRole(Role::CRM));
        $advisor = $this->createUser(null, [
            'email' => 'advisor@example.test',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $advisor->id,
            'public_slug' => 'advisor-' . $advisor->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($crm)->post(route('admin.advisor-profiles.update', $profile), [
            'designation' => 'Senior Property Advisor',
            'specialization_tags' => ['Luxury Specialist', 'Villa Expert'],
        ]);

        $response->assertRedirect(route('admin.advisor-profiles.edit', $profile));
        $this->assertSame(['Luxury Specialist', 'Villa Expert'], $profile->fresh()->specialization_tags);
    }

    public function test_batch_gallery_upload_creates_one_pending_item_per_image(): void
    {
        Storage::fake('public');
        $this->mockOptimizer(['optimized/advisor-gallery-one.webp', 'optimized/advisor-gallery-two.webp']);

        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.gallery.store'), [
            'images' => [
                $this->fakePngUpload('photo-1.png'),
                $this->fakePngUpload('photo-2.png'),
            ],
            'caption' => 'Weekend site visit',
            'category' => 'site_visit',
        ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHas('success', '2 gallery photo upload ho gayi. Images auto optimize ho gayi hain. CRM approval ke baad public page par dikhegi.');

        $this->assertSame(2, AdvisorPublicGalleryItem::count());
        $this->assertSame(2, AdvisorPublicGalleryItem::where('moderation_status', AdvisorPublicGalleryItem::STATUS_PENDING)->count());
        $this->assertSame('optimized/advisor-gallery-one.webp', AdvisorPublicGalleryItem::query()->orderBy('id')->first()->image_path);
    }

    public function test_batch_gallery_upload_requires_customer_consent_for_customer_categories(): void
    {
        Storage::fake('public');
        $this->mockOptimizer();

        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $response = $this->from(route('advisor.profile.show'))
            ->actingAs($user)
            ->post(route('advisor.profile.gallery.store'), [
                'images' => [
                    $this->fakePngUpload('photo-1.png'),
                    $this->fakePngUpload('photo-2.png'),
                ],
                'category' => 'with_customer',
            ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHasErrors('customer_consent_confirmed');
        $this->assertSame(0, AdvisorPublicGalleryItem::count());
    }

    public function test_user_can_delete_their_gallery_photo(): void
    {
        Storage::fake('public');

        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        Storage::disk('public')->put('advisor-gallery/photo.png', 'demo');

        $item = $profile->galleryItems()->create([
            'image_path' => 'advisor-gallery/photo.png',
            'caption' => 'To be deleted',
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.gallery.destroy', $item));

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHas('success', 'Gallery photo delete ho gayi.');
        $this->assertDatabaseCount('advisor_public_gallery_items', 0);
        Storage::disk('public')->assertMissing('advisor-gallery/photo.png');
    }

    public function test_profile_photo_upload_stores_optimized_webp_path(): void
    {
        Storage::fake('public');
        $this->mockOptimizer(['optimized/advisor-profile-picture.webp']);

        $user = $this->createUser(null, [
            'email' => 'test@gmail.com',
        ]);

        $response = $this->actingAs($user)->post(route('advisor.profile.update'), [
            'designation' => 'Senior Property Advisor',
            'profile_picture' => $this->fakePngUpload('profile.png'),
            'investor_portfolio_value' => 1000000,
            'active_investors' => 4,
            'nri_investors_assisted' => 2,
            'bookings_this_quarter' => 1,
        ]);

        $response->assertRedirect(route('advisor.profile.show'));
        $response->assertSessionHas('success', 'Public profile save ho gayi. Profile photo auto optimize ho gayi hai. CRM approval ke baad hi public page live hoga.');
        $this->assertSame('optimized/advisor-profile-picture.webp', $user->fresh()->profile_picture);
    }

    public function test_recompress_command_updates_existing_advisor_asset_paths(): void
    {
        $optimizer = \Mockery::mock(AdvisorPublicImageOptimizer::class);
        $optimizer->shouldReceive('recompressStoredPath')->once()
            ->with('advisor-profile-pictures/original.png', 'advisor-profile-pictures', false)
            ->andReturn(['status' => 'optimized', 'path' => 'advisor-profile-pictures/original.webp']);
        $optimizer->shouldReceive('recompressStoredPath')->once()
            ->with('advisor-gallery/original.png', 'advisor-gallery', false)
            ->andReturn(['status' => 'optimized', 'path' => 'advisor-gallery/original.webp']);
        $this->app->instance(AdvisorPublicImageOptimizer::class, $optimizer);

        $user = $this->createUser(null, [
            'email' => 'advisor@example.test',
            'profile_picture' => 'advisor-profile-pictures/original.png',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'completion_percentage' => 0,
        ]);

        $profile->galleryItems()->create([
            'image_path' => 'advisor-gallery/original.png',
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_PENDING,
        ]);

        $this->artisan('advisor:recompress-public-images')
            ->expectsOutput('Advisor public image recompression complete.')
            ->assertExitCode(0);

        $this->assertSame('advisor-profile-pictures/original.webp', $user->fresh()->profile_picture);
        $this->assertSame('advisor-gallery/original.webp', $profile->galleryItems()->first()->image_path);
    }

    public function test_completion_percentage_does_not_depend_on_specialization_tags(): void
    {
        $user = $this->createUser(null, [
            'email' => 'advisor@example.test',
            'profile_picture' => 'advisor-profile-pictures/avatar.jpg',
        ]);

        $profile = AdvisorPublicProfile::create([
            'user_id' => $user->id,
            'public_slug' => 'advisor-' . $user->id,
            'bio' => 'Short bio',
            'experience_years' => 6,
            'languages' => ['Hindi', 'English'],
            'service_areas' => ['Gomti Nagar'],
            'why_choose_me' => 'Clear practical guidance',
            'successful_closures' => 12,
            'site_visits_handled' => 40,
            'sqft_sold' => 12000,
            'happy_families_served' => 18,
            'completion_percentage' => 0,
        ]);

        $profile->galleryItems()->create([
            'image_path' => 'advisor-gallery/photo.jpg',
            'moderation_status' => AdvisorPublicGalleryItem::STATUS_APPROVED,
        ]);

        $profile->reviews()->create([
            'customer_name' => 'Customer Name',
            'customer_phone' => '9999999999',
            'customer_phone_masked' => '99XXXXXX99',
            'rating' => 5,
            'review_text' => 'Great experience',
            'moderation_status' => 'approved',
        ]);

        $profile->setRelation('user', $user);

        $this->assertSame(100, $profile->calculateCompletionPercentage());
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->text('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('advisor_public_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('public_slug')->unique();
            $table->string('designation')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('experience_years')->nullable();
            $table->text('languages')->nullable();
            $table->text('service_areas')->nullable();
            $table->text('specialization_tags')->nullable();
            $table->text('why_choose_me')->nullable();
            $table->unsignedInteger('successful_closures')->default(0);
            $table->unsignedInteger('site_visits_handled')->default(0);
            $table->unsignedInteger('sqft_sold')->default(0);
            $table->unsignedInteger('happy_families_served')->default(0);
            $table->unsignedBigInteger('investor_portfolio_value')->default(0);
            $table->unsignedInteger('active_investors')->default(0);
            $table->unsignedInteger('nri_investors_assisted')->default(0);
            $table->unsignedInteger('bookings_this_quarter')->default(0);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamps();
        });

        Schema::create('advisor_public_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advisor_public_profile_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_phone_masked');
            $table->unsignedTinyInteger('rating');
            $table->text('review_text');
            $table->string('project_name')->nullable();
            $table->boolean('is_verified_customer')->default(false);
            $table->string('submission_source')->default('public_form');
            $table->string('content_type')->default('text');
            $table->string('video_url')->nullable();
            $table->string('video_platform')->nullable();
            $table->string('video_thumbnail_url')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->boolean('customer_consent_confirmed')->default(false);
            $table->string('moderation_status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('advisor_public_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advisor_public_profile_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->string('category')->nullable();
            $table->boolean('customer_consent_confirmed')->default(false);
            $table->string('moderation_status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('builders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('advisor_public_profile_builder', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advisor_public_profile_id');
            $table->unsignedBigInteger('builder_id');
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('loan_partner_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->string('short_offer_text', 120)->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(?Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'advisor-profile-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role?->id,
            'is_active' => true,
        ], $attributes));
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlAbWQAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function mockOptimizer(array $paths = ['optimized/mock-image.webp']): void
    {
        $optimizer = \Mockery::mock(AdvisorPublicImageOptimizer::class);
        $optimizer->shouldReceive('storeOptimizedUpload')
            ->andReturnValues($paths);

        $this->app->instance(AdvisorPublicImageOptimizer::class, $optimizer);
    }
}
