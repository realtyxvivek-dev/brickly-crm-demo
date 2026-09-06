<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewUserWelcomeNotification;
use App\Support\AppUrl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionUrlGenerationTest extends TestCase
{
    public function test_app_url_helper_uses_configured_production_root(): void
    {
        Config::set('app.url', 'https://crm.bihtech.in');

        $this->assertSame('https://crm.bihtech.in', AppUrl::root());
        $this->assertSame('https://crm.bihtech.in/login', AppUrl::to('/login'));
        $this->assertSame('https://crm.bihtech.in/install-app', AppUrl::to('install-app'));
    }

    public function test_new_user_welcome_mail_uses_configured_production_links(): void
    {
        Config::set('app.url', 'https://crm.bihtech.in');

        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'is_active' => true,
        ]);

        $notification = new NewUserWelcomeNotification($user, 'Secret123!', 'Sales Executive', 'Manager');
        $mailMessage = $notification->toMail($user);

        $this->assertSame('emails.new-user-welcome', $mailMessage->view);
        $this->assertSame('https://crm.bihtech.in/login', $mailMessage->viewData['loginUrl']);
        $this->assertSame('https://crm.bihtech.in/install-app', $mailMessage->viewData['installAppUrl']);
    }

    public function test_lead_assigned_mail_uses_configured_production_links(): void
    {
        Config::set('app.url', 'https://crm.bihtech.in');
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'support@example.com');

        $role = new Role(['slug' => Role::SALES_EXECUTIVE, 'name' => 'Sales Executive']);
        $notifiable = new User(['name' => 'Agent']);
        $notifiable->setRelation('role', $role);

        $lead = new Lead(['name' => 'New Lead']);
        $notification = new LeadAssignedNotification($lead, 1);
        $mailMessage = $notification->toMail($notifiable);

        $this->assertSame('https://crm.bihtech.in/telecaller/tasks?status=pending', $mailMessage->actionUrl);
    }
}
