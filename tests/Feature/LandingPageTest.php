<?php

namespace Tests\Feature;

use App\Mail\DemoRequestMail;
use App\Models\MailDeliveryLog;
use App\Services\MailDeliveryLogger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge();
        DB::setDefaultConnection('sqlite');

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
        });

        DB::table('company_settings')->insert([
            'setting_key' => 'company_name',
            'setting_value' => 'Brickly CRM',
            'setting_type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aarav Mehta',
            'company' => 'Northstar Realty',
            'email' => 'aarav@example.com',
            'phone' => '+91 98765 43210',
            'message' => 'We need lead automation for a 20-person team.',
            'website' => '',
        ], $overrides);
    }

    public function test_preview_route_is_public_and_root_redirect_is_unchanged(): void
    {
        $this->get('/landing-preview')->assertOk()
            ->assertSee('Brickly CRM')
            ->assertSee('images/landing/brickly-ai-command-center.webp', false)
            ->assertSee('images/landing/brickly-ai-lead-intelligence.webp', false)
            ->assertSee('images/landing/brickly-ai-project-sharing.webp', false)
            ->assertSee('images/landing/brickly-ai-operations-control.webp', false)
            ->assertDontSee('brickly-sales-overview.png', false)
            ->assertDontSee('enterprise-crm-hero.png', false)
            ->assertSee('One platform to run')
            ->assertSeeInOrder(['Lead Management', 'Team &amp; HR', 'Lead Bank', 'Ads &amp; Quality', 'Projects &amp; Sharing', 'Finance', 'Post-Sales', 'Automation'], false)
            ->assertSeeInOrder(['Smart Project Link', 'Native apps & calling', 'Meta Quality Loop', 'Advisor Public Profile', 'Daily Intelligence Email', 'Data Intelligence Workspace'], false)
            ->assertSee('On-device call recording is available on supported Android devices and OS versions.');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_valid_demo_request_uses_configured_recipient_and_reply_to(): void
    {
        config()->set('crm.demo_request_email', 'realtyxvivek@gmail.com');

        $this->mock(MailDeliveryLogger::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMailable')->once()->withArgs(function ($type, $subject, $recipient, $mailable) {
                $replyTo = $mailable->envelope()->replyTo[0] ?? null;

                return $type === MailDeliveryLog::TYPE_DEMO_REQUEST
                    && $subject === '['.brand_name().'] New Demo Request — Northstar Realty'
                    && $recipient === 'realtyxvivek@gmail.com'
                    && $mailable instanceof DemoRequestMail
                    && $replyTo?->address === 'aarav@example.com';
            })->andReturn((new MailDeliveryLog())->forceFill(['status' => MailDeliveryLog::STATUS_SENT]));
        });

        $this->post('/demo-request', $this->validPayload())
            ->assertRedirectContains('#book-demo')
            ->assertSessionHas('demo_success');
    }

    public function test_invalid_and_honeypot_requests_do_not_send_mail(): void
    {
        $this->mock(MailDeliveryLogger::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sendMailable'));

        $this->post('/demo-request', $this->validPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->post('/demo-request', $this->validPayload(['website' => 'spam.example']))
            ->assertSessionHas('demo_success');
    }

    public function test_mail_failure_returns_error_state(): void
    {
        $this->mock(MailDeliveryLogger::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMailable')->once()
                ->andReturn((new MailDeliveryLog())->forceFill(['status' => MailDeliveryLog::STATUS_FAILED]));
        });

        $this->post('/demo-request', $this->validPayload())
            ->assertRedirectContains('#book-demo')
            ->assertSessionHasErrors('demo_request')
            ->assertSessionMissing('demo_success');
    }

    public function test_demo_request_is_rate_limited_after_five_submissions(): void
    {
        $this->mock(MailDeliveryLogger::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMailable')->times(5)
                ->andReturn((new MailDeliveryLog())->forceFill(['status' => MailDeliveryLog::STATUS_SENT]));
        });

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/demo-request', $this->validPayload(['email' => "visitor{$attempt}@example.com"]))
                ->assertRedirectContains('#book-demo');
        }

        $this->post('/demo-request', $this->validPayload(['email' => 'sixth@example.com']))->assertTooManyRequests();
    }
}
