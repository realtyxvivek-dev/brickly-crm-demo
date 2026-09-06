<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MetaOAuthService
{
    private string $baseUrl = 'https://graph.facebook.com';

    public function authorizationUrl(string $state): string
    {
        $appId = (string) config('meta_oauth.app_id');
        if ($appId === '') {
            throw new RuntimeException('META_APP_ID is not configured.');
        }

        $version = $this->graphVersion();
        $query = [
            'client_id' => $appId,
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];

        $configId = (string) config('meta_oauth.login_config_id');
        if ($configId !== '') {
            $query['config_id'] = $configId;
        } else {
            $query['scope'] = implode(',', config('meta_oauth.default_scopes', []));
        }

        return "https://www.facebook.com/{$version}/dialog/oauth?" . http_build_query($query);
    }

    public function exchangeCodeForLongLivedToken(string $code): array
    {
        $shortLived = $this->get('oauth/access_token', [
            'client_id' => config('meta_oauth.app_id'),
            'client_secret' => config('meta_oauth.app_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        $token = (string) ($shortLived['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Meta did not return an access token.');
        }

        $longLived = $this->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('meta_oauth.app_id'),
            'client_secret' => config('meta_oauth.app_secret'),
            'fb_exchange_token' => $token,
        ]);

        return [
            'access_token' => $longLived['access_token'] ?? $token,
            'token_type' => $longLived['token_type'] ?? $shortLived['token_type'] ?? null,
            'expires_in' => $longLived['expires_in'] ?? $shortLived['expires_in'] ?? null,
        ];
    }

    public function getUser(string $accessToken): array
    {
        return $this->get('me', [
            'fields' => 'id,name',
            'access_token' => $accessToken,
        ]);
    }

    public function getGrantedScopes(string $accessToken): array
    {
        $result = $this->get('me/permissions', [
            'access_token' => $accessToken,
        ]);

        return collect($result['data'] ?? [])
            ->filter(fn (array $permission) => ($permission['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->filter()
            ->values()
            ->all();
    }

    public function getPages(string $accessToken): array
    {
        $result = $this->get('me/accounts', [
            'fields' => 'id,name,access_token,tasks',
            'access_token' => $accessToken,
        ]);

        $pages = $result['data'] ?? [];
        if (!empty($pages)) {
            return $pages;
        }

        return $this->getBusinessPages($accessToken);
    }

    public function subscribePageToLeadgen(string $pageId, string $pageAccessToken): array
    {
        return $this->post($pageId . '/subscribed_apps', [
            'subscribed_fields' => 'leadgen',
            'access_token' => $pageAccessToken,
        ]);
    }

    public function getLeadDetails(string $leadgenId, string $pageAccessToken): array
    {
        return $this->get($leadgenId, [
            'fields' => 'created_time,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,field_data',
            'access_token' => $pageAccessToken,
        ]);
    }

    public function getPageLeadForms(string $pageId, string $pageAccessToken): array
    {
        $result = $this->get($pageId . '/leadgen_forms', [
            'fields' => 'id,name,status,created_time',
            'limit' => 100,
            'access_token' => $pageAccessToken,
        ]);

        return $result['data'] ?? [];
    }

    private function getBusinessPages(string $accessToken): array
    {
        try {
            $businesses = $this->get('me/businesses', [
                'fields' => 'id,name',
                'access_token' => $accessToken,
            ]);
        } catch (RuntimeException) {
            return [];
        }

        $pages = [];
        foreach ($businesses['data'] ?? [] as $business) {
            $businessId = $business['id'] ?? null;
            if (!$businessId) {
                continue;
            }

            foreach (['owned_pages', 'client_pages'] as $edge) {
                try {
                    $result = $this->get($businessId . '/' . $edge, [
                        'fields' => 'id,name,access_token,tasks',
                        'access_token' => $accessToken,
                    ]);
                } catch (RuntimeException) {
                    continue;
                }

                foreach ($result['data'] ?? [] as $page) {
                    if (!empty($page['id'])) {
                        $pages[(string) $page['id']] = $page;
                    }
                }
            }
        }

        return array_values($pages);
    }

    public function tokenExpiry(?int $expiresIn): ?Carbon
    {
        if (!$expiresIn) {
            return null;
        }

        return now()->addSeconds($expiresIn);
    }

    public function newState(): string
    {
        return Str::random(48);
    }

    private function get(string $path, array $query): array
    {
        try {
            return Http::get($this->url($path), $query)->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException($this->errorMessage($e), 0, $e);
        }
    }

    private function post(string $path, array $payload): array
    {
        try {
            return Http::asForm()->post($this->url($path), $payload)->throw()->json();
        } catch (RequestException $e) {
            throw new RuntimeException($this->errorMessage($e), 0, $e);
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . $this->graphVersion() . '/' . ltrim($path, '/');
    }

    private function graphVersion(): string
    {
        return (string) config('meta_oauth.graph_version', 'v23.0');
    }

    private function redirectUri(): string
    {
        return (string) config('meta_oauth.redirect_uri');
    }

    private function errorMessage(RequestException $e): string
    {
        $response = $e->response;
        $message = $response?->json('error.message');

        return $message ?: $e->getMessage();
    }
}
