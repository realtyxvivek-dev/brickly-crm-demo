<?php

namespace App\Services\Instagram;

use App\Models\IgApiLog;
use App\Models\InstagramAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class InstagramOAuthService
{
    public function __construct(
        private readonly InstagramConfig $config,
        private readonly InstagramGraphService $graphService
    ) {
    }

    public function configStatus(): array
    {
        return $this->config->status();
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Instagram app credentials are not configured.');
        }

        return $this->config->authUrl() . '?' . http_build_query([
            'client_id' => $this->config->clientId(),
            'redirect_uri' => $this->config->redirectUri(),
            'scope' => implode(',', $this->config->scopes()),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function makeState(): string
    {
        return Str::random(48);
    }

    public function exchangeCodeForShortLivedToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->config->clientId(),
            'client_secret' => $this->config->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->config->redirectUri(),
            'code' => $code,
        ]);

        $this->log(null, 'POST', 'https://api.instagram.com/oauth/access_token', ['grant_type' => 'authorization_code'], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram authorization code exchange failed.');
        }

        $json = $response->json();
        $accessToken = $json['access_token'] ?? null;

        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Instagram authorization response did not include access_token.',
                'data' => $json,
            ];
        }

        return [
            'success' => true,
            'access_token' => $accessToken,
            'user_id' => $json['user_id'] ?? null,
            'data' => $json,
        ];
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::asForm()->post($this->graphUrl('/access_token', false), [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->config->clientSecret(),
            'access_token' => $shortLivedToken,
        ]);

        $this->log(null, 'POST', '/access_token', ['grant_type' => 'ig_exchange_token'], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram long-lived token exchange failed.');
        }

        $json = $response->json();
        $accessToken = $json['access_token'] ?? null;

        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Instagram long-lived token response did not include access_token.',
                'data' => $json,
            ];
        }

        return [
            'success' => true,
            'access_token' => $accessToken,
            'expires_in' => isset($json['expires_in']) ? (int) $json['expires_in'] : 60 * 24 * 60 * 60,
            'data' => $json,
        ];
    }

    public function connectFromCode(string $code, int $userId): array
    {
        $short = $this->exchangeCodeForShortLivedToken($code);
        if (!($short['success'] ?? false)) {
            return $short;
        }

        $long = $this->exchangeForLongLivedToken($short['access_token']);
        if (!($long['success'] ?? false)) {
            return $long;
        }

        $profile = $this->graphService->fetchProfile($long['access_token']);
        if (!($profile['success'] ?? false)) {
            return $profile;
        }

        $data = $profile['data'];
        $accountType = strtoupper((string) ($data['account_type'] ?? ''));

        if ($accountType !== '' && !in_array($accountType, ['BUSINESS', 'MEDIA_CREATOR'], true)) {
            return [
                'success' => false,
                'error' => 'Only Instagram Business or Creator accounts can be connected.',
                'data' => $data,
            ];
        }

        $account = InstagramAccount::withTrashed()->where('ig_user_id', $data['ig_user_id'])->first();

        if ($account?->trashed()) {
            $account->restore();
        }

        $account = InstagramAccount::query()->updateOrCreate(
            ['ig_user_id' => $data['ig_user_id']],
            [
                'ig_username' => $data['ig_username'] ?? null,
                'account_type' => $data['account_type'] ?? null,
                'profile_picture_url' => $data['profile_picture_url'] ?? null,
                'access_token' => $long['access_token'],
                'refresh_token' => null,
                'token_expiry' => now()->addSeconds((int) ($long['expires_in'] ?? 60 * 24 * 60 * 60)),
                'status' => 'connected',
                'connected_by' => $userId,
            ]
        );

        return [
            'success' => true,
            'account' => $account,
        ];
    }

    public function refreshAccountToken(InstagramAccount $account): array
    {
        if (!$account->access_token) {
            return [
                'success' => false,
                'error' => 'Instagram account has no access token saved.',
            ];
        }

        $result = $this->graphService->refreshLongLivedToken($account);

        if (!($result['success'] ?? false)) {
            $account->update([
                'status' => $account->token_expiry && $account->token_expiry->isFuture() ? $account->status : 'token_expired',
            ]);

            return $result;
        }

        $account->update([
            'access_token' => $result['access_token'],
            'token_expiry' => now()->addSeconds((int) ($result['expires_in'] ?? 60 * 24 * 60 * 60)),
            'status' => 'connected',
        ]);

        return [
            'success' => true,
            'account' => $account->fresh(),
        ];
    }

    private function graphUrl(string $path, bool $withVersion = true): string
    {
        $path = ltrim($path, '/');

        if ($withVersion) {
            return $this->config->baseUrl() . '/' . trim($this->config->graphVersion(), '/') . '/' . $path;
        }

        return $this->config->baseUrl() . '/' . $path;
    }

    private function log(?InstagramAccount $account, string $method, string $endpoint, array $payload, Response $response): void
    {
        IgApiLog::query()->create([
            'instagram_account_id' => $account?->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'payload' => $payload,
            'response' => $response->json() ?? ['body' => $response->body()],
            'status_code' => $response->status(),
            'status' => $response->successful() ? 'success' : 'failed',
            'error_message' => $response->successful() ? null : $response->json('error.message', $response->body()),
        ]);
    }

    private function failure(Response $response, string $fallback): array
    {
        return [
            'success' => false,
            'error' => $response->json('error.message', $fallback),
            'data' => $response->json(),
            'status_code' => $response->status(),
        ];
    }
}
