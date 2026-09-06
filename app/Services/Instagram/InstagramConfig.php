<?php

namespace App\Services\Instagram;

use App\Models\SystemSettings;

class InstagramConfig
{
    public function graphVersion(): string
    {
        return $this->value('graph_version', 'v21.0');
    }

    public function clientId(): ?string
    {
        return $this->value('client_id');
    }

    public function clientSecret(): ?string
    {
        return $this->value('client_secret');
    }

    public function redirectUri(): string
    {
        return $this->value('redirect_uri') ?: route('admin.instagram-automation.callback');
    }

    public function scopes(): array
    {
        $stored = $this->value('scopes');

        if (is_string($stored) && trim($stored) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $stored))));
        }

        return config('instagram.scopes', []);
    }

    public function baseUrl(): string
    {
        return rtrim($this->value('base_url', 'https://graph.instagram.com'), '/');
    }

    public function authUrl(): string
    {
        return $this->value('auth_url', 'https://www.instagram.com/oauth/authorize');
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function webhookVerifyToken(): ?string
    {
        return $this->value('webhook_verify_token');
    }

    public function appSecret(): ?string
    {
        return $this->value('app_secret') ?: $this->clientSecret();
    }

    public function webhookSignatureEnabled(): bool
    {
        return filter_var($this->value('webhook_signature_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function status(): array
    {
        return [
            'client_id_configured' => filled($this->clientId()),
            'client_secret_configured' => filled($this->clientSecret()),
            'redirect_uri' => $this->redirectUri(),
            'scopes' => $this->scopes(),
            'graph_version' => $this->graphVersion(),
            'base_url' => $this->baseUrl(),
            'auth_url' => $this->authUrl(),
            'webhook_url' => url('/api/webhooks/instagram'),
            'webhook_verify_token_configured' => filled($this->webhookVerifyToken()),
            'app_secret_configured' => filled($this->appSecret()),
            'webhook_signature_enabled' => $this->webhookSignatureEnabled(),
        ];
    }

    private function value(string $key, mixed $default = null): mixed
    {
        $systemValue = SystemSettings::get('instagram_' . $key);

        if ($systemValue !== null && $systemValue !== '') {
            return $systemValue;
        }

        return config('instagram.' . $key, $default);
    }
}
