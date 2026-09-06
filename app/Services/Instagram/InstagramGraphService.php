<?php

namespace App\Services\Instagram;

use App\Models\IgApiLog;
use App\Models\InstagramAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InstagramGraphService
{
    public function __construct(private readonly InstagramConfig $config)
    {
    }

    public function fetchProfile(string $accessToken, ?InstagramAccount $account = null): array
    {
        $response = Http::get($this->url('/me'), [
            'fields' => 'user_id,username,account_type,profile_picture_url',
            'access_token' => $accessToken,
        ]);

        $this->log($account, 'GET', '/me', ['fields' => 'user_id,username,account_type,profile_picture_url'], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Unable to fetch Instagram profile.');
        }

        $json = $response->json();
        $profile = $json['data'][0] ?? $json['data'] ?? $json;

        if (!is_array($profile) || empty($profile['user_id'])) {
            return [
                'success' => false,
                'error' => 'Instagram profile response did not include a user_id.',
                'data' => $json,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'ig_user_id' => (string) $profile['user_id'],
                'ig_username' => $profile['username'] ?? null,
                'account_type' => $profile['account_type'] ?? null,
                'profile_picture_url' => $profile['profile_picture_url'] ?? null,
            ],
        ];
    }

    public function refreshLongLivedToken(InstagramAccount $account): array
    {
        $response = Http::get($this->url('/refresh_access_token', false), [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $account->access_token,
        ]);

        $this->log($account, 'GET', '/refresh_access_token', ['grant_type' => 'ig_refresh_token'], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram token refresh failed.');
        }

        $json = $response->json();
        $accessToken = $json['access_token'] ?? null;

        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Instagram token refresh response did not include access_token.',
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

    public function replyToComment(InstagramAccount $account, string $commentId, string $message): array
    {
        $payload = [
            'message' => $message,
            'access_token' => $account->access_token,
        ];

        $response = Http::post($this->url('/' . $commentId . '/replies'), $payload);
        $this->log($account, 'POST', '/' . $commentId . '/replies', ['message' => $message], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram public comment reply failed.');
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    public function sendPrivateReply(InstagramAccount $account, string $commentId, string $message): array
    {
        $payload = [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $message],
            'access_token' => $account->access_token,
        ];

        $response = Http::post($this->url('/messages'), $payload);
        $this->log($account, 'POST', '/messages', [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $message],
        ], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram private reply failed.');
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    public function sendTextMessage(InstagramAccount $account, string $instagramUserId, string $message): array
    {
        $payload = [
            'recipient' => ['id' => $instagramUserId],
            'message' => ['text' => $message],
            'access_token' => $account->access_token,
        ];

        $response = Http::post($this->url('/messages'), $payload);
        $this->log($account, 'POST', '/messages', [
            'recipient' => ['id' => $instagramUserId],
            'message' => ['text' => $message],
        ], $response);

        if (!$response->successful()) {
            return $this->failure($response, 'Instagram DM send failed.');
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    public function log(?InstagramAccount $account, string $method, string $endpoint, array $payload, Response $response): void
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

    private function url(string $path, bool $withVersion = true): string
    {
        $path = ltrim($path, '/');

        if ($withVersion) {
            return $this->config->baseUrl() . '/' . trim($this->config->graphVersion(), '/') . '/' . $path;
        }

        return $this->config->baseUrl() . '/' . $path;
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
