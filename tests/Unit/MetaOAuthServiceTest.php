<?php

namespace Tests\Unit;

use App\Services\MetaOAuthService;
use Tests\TestCase;

class MetaOAuthServiceTest extends TestCase
{
    public function test_authorization_url_uses_login_config_id_when_configured(): void
    {
        config()->set('meta_oauth.app_id', 'app_123');
        config()->set('meta_oauth.login_config_id', 'config_456');
        config()->set('meta_oauth.graph_version', 'v23.0');
        config()->set('meta_oauth.redirect_uri', 'https://crm.example.test/integrations/facebook-connector/callback');

        $url = app(MetaOAuthService::class)->authorizationUrl('state_789');

        $this->assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $url);
        $this->assertStringContainsString('client_id=app_123', $url);
        $this->assertStringContainsString('config_id=config_456', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('state=state_789', $url);
        $this->assertStringNotContainsString('scope=', $url);
    }
}
