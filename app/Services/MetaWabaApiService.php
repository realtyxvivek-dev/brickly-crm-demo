<?php

namespace App\Services;

use App\Models\MetaWabaAccount;
use App\Models\MetaWabaSettings;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWabaApiService
{
    private MetaWabaSettings|MetaWabaAccount $settings;

    public function __construct(MetaWabaSettings|MetaWabaAccount|null $settings = null)
    {
        $this->settings = ($settings && $settings->exists)
            ? $settings
            : (MetaWabaAccount::defaultAccount() ?: MetaWabaSettings::getSettings());
    }

    public function forAccount(MetaWabaAccount $account): self
    {
        return new self($account);
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->phone_number_id)
            && filled($this->settings->access_token)
            && filled($this->settings->graph_version);
    }

    public function verifyConnection(): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        $result = $this->getGraph('/' . $this->settings->phone_number_id, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,platform_type,throughput',
        ]);

        if ($result['success'] ?? false) {
            $data = $result['data'] ?? [];
            $this->settings->update([
                'is_verified' => true,
                'verified_at' => now(),
                'display_phone_number' => $data['display_phone_number'] ?? null,
                'verified_name' => $data['verified_name'] ?? null,
                'quality_rating' => $data['quality_rating'] ?? null,
                'last_verified_response' => $data,
            ]);
            if ($this->settings instanceof MetaWabaAccount && $this->settings->is_default) {
                $this->settings->fresh()->syncToSettings();
            }
            $result['message'] = 'Meta WABA connection verified successfully';
        }

        return $result;
    }

    public function getPhoneCallSettings(): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        $phoneResult = $this->getGraph('/' . $this->settings->phone_number_id, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,platform_type,throughput',
        ]);

        return [
            'success' => (bool) ($phoneResult['success'] ?? false),
            'provider' => 'meta_waba',
            'data' => [
                'phone' => $phoneResult['data'] ?? null,
                'crm_preferences' => [
                    'voice_calls_enabled' => (bool) $this->settings->voice_calls_enabled,
                    'display_call_buttons' => (bool) $this->settings->display_call_buttons,
                    'callbacks_enabled' => (bool) $this->settings->callbacks_enabled,
                    'call_hours' => $this->settings->call_hours,
                    'call_pause_until' => optional($this->settings->call_pause_until)->toIso8601String(),
                ],
                'meta_settings_api_available' => false,
            ],
            'message' => ($phoneResult['success'] ?? false)
                ? 'Phone details refreshed. Live call toggles may still need WhatsApp Manager.'
                : ($phoneResult['error'] ?? 'Phone details fetch failed.'),
        ];
    }

    public function savePhoneCallPreferences(array $preferences): array
    {
        $this->settings->update([
            'voice_calls_enabled' => (bool) ($preferences['voice_calls_enabled'] ?? false),
            'display_call_buttons' => (bool) ($preferences['display_call_buttons'] ?? false),
            'callbacks_enabled' => (bool) ($preferences['callbacks_enabled'] ?? false),
            'call_hours' => $preferences['call_hours'] ?? null,
            'call_pause_until' => $preferences['call_pause_until'] ?? null,
            'call_settings_last_response' => [
                'saved_at' => now()->toIso8601String(),
                'note' => 'CRM preferences saved. Manage live WhatsApp calling toggles in WhatsApp Manager if Meta API does not expose them for this account.',
            ],
        ]);

        return [
            'success' => true,
            'provider' => 'meta_waba',
            'message' => 'WABA call preferences saved in CRM. If live Meta toggles differ, update them in WhatsApp Manager.',
            'data' => $this->settings->fresh(),
        ];
    }

    public function sendTextMessage(string $to, string $message): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        $phone = $this->normalizePhone($to);
        if (!$this->isWithinCustomerServiceWindow($phone)) {
            return [
                'success' => false,
                'provider' => 'meta_waba',
                'meta_waba_account_id' => $this->settings instanceof MetaWabaAccount ? $this->settings->id : null,
                'phone_number_id' => $this->settings->phone_number_id,
                'error' => 'WABA 24-hour customer service window is closed. Please send an approved template message.',
            ];
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ]);
    }

    public function sendTemplateMessage(string $to, string $templateName, array $parameters = [], ?string $language = null): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($parameter) => $this->normalizeTemplateParameter($parameter), $parameters),
            ];
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => array_filter([
                'name' => $templateName,
                'language' => ['code' => $language ?: 'en_US'],
                'components' => $components,
            ], fn ($value) => !($value === [] || $value === null)),
        ]);
    }

    public function getTemplates(): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        if (blank($this->settings->waba_id)) {
            return [
                'success' => false,
                'provider' => 'meta_waba',
                'error' => 'WABA ID is required to sync templates from Meta.',
            ];
        }

        $result = $this->getGraph('/' . $this->settings->waba_id . '/message_templates', [
            'fields' => 'id,name,status,category,language,components',
            'limit' => 100,
        ]);

        if ($result['success'] ?? false) {
            $result['data'] = Arr::get($result, 'data.data', []);
        }

        return $result;
    }

    public function createTemplate(array $payload): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        if (blank($this->settings->waba_id)) {
            return [
                'success' => false,
                'provider' => 'meta_waba',
                'error' => 'WABA ID is required to create templates.',
            ];
        }

        return $this->postGraph('/' . $this->settings->waba_id . '/message_templates', $payload);
    }

    public function deleteTemplate(string $templateName): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        if (blank($this->settings->waba_id)) {
            return [
                'success' => false,
                'provider' => 'meta_waba',
                'error' => 'WABA ID is required to delete templates from Meta.',
            ];
        }

        return $this->deleteGraph('/' . $this->settings->waba_id . '/message_templates', [
            'name' => $templateName,
        ]);
    }

    public function registerPhoneNumber(?string $pin = null): array
    {
        if (!$this->isConfigured()) {
            return $this->notConfigured();
        }

        $payload = [
            'messaging_product' => 'whatsapp',
        ];

        if (filled($pin)) {
            $payload['pin'] = $pin;
        }

        return $this->postGraph('/' . $this->settings->phone_number_id . '/register', $payload);
    }

    public function getTemplate(string $templateId): array
    {
        $templates = $this->getTemplates();
        if (!($templates['success'] ?? false)) {
            return $templates;
        }

        $template = collect($templates['data'] ?? [])->first(function ($item) use ($templateId) {
            return (string) ($item['id'] ?? '') === $templateId
                || (string) ($item['name'] ?? '') === $templateId;
        });

        return $template
            ? ['success' => true, 'provider' => 'meta_waba', 'data' => $template]
            : ['success' => false, 'provider' => 'meta_waba', 'error' => 'Template not found'];
    }

    private function postMessage(array $payload): array
    {
        try {
            $response = Http::withToken(trim((string) $this->settings->access_token))
                ->acceptJson()
                ->timeout(30)
                ->post($this->graphUrl('/' . $this->settings->phone_number_id . '/messages'), $payload);

            return $this->normalizeResponse($response->status(), $response->json(), $response->body(), 'POST');
        } catch (\Throwable $e) {
            Log::error('Meta WABA send failed: ' . $e->getMessage(), ['payload' => $payload]);

            return ['success' => false, 'provider' => 'meta_waba', 'error' => $e->getMessage()];
        }
    }

    private function getGraph(string $path, array $query = []): array
    {
        try {
            $response = Http::withToken(trim((string) $this->settings->access_token))
                ->acceptJson()
                ->timeout(30)
                ->get($this->graphUrl($path), $query);

            return $this->normalizeResponse($response->status(), $response->json(), $response->body(), 'GET');
        } catch (\Throwable $e) {
            Log::error('Meta WABA Graph request failed: ' . $e->getMessage(), ['path' => $path]);

            return ['success' => false, 'provider' => 'meta_waba', 'error' => $e->getMessage()];
        }
    }

    private function postGraph(string $path, array $payload = []): array
    {
        try {
            $response = Http::withToken(trim((string) $this->settings->access_token))
                ->acceptJson()
                ->timeout(30)
                ->post($this->graphUrl($path), $payload);

            return $this->normalizeResponse($response->status(), $response->json(), $response->body(), 'POST');
        } catch (\Throwable $e) {
            Log::error('Meta WABA Graph POST failed: ' . $e->getMessage(), ['path' => $path, 'payload' => $payload]);

            return ['success' => false, 'provider' => 'meta_waba', 'error' => $e->getMessage()];
        }
    }

    private function deleteGraph(string $path, array $query = []): array
    {
        try {
            $response = Http::withToken(trim((string) $this->settings->access_token))
                ->acceptJson()
                ->timeout(30)
                ->delete($this->graphUrl($path), $query);

            return $this->normalizeResponse($response->status(), $response->json(), $response->body(), 'DELETE');
        } catch (\Throwable $e) {
            Log::error('Meta WABA Graph DELETE failed: ' . $e->getMessage(), ['path' => $path, 'query' => $query]);

            return ['success' => false, 'provider' => 'meta_waba', 'error' => $e->getMessage()];
        }
    }

    private function normalizeResponse(int $status, mixed $json, string $body, string $method): array
    {
        $data = is_array($json) ? $json : [];
        if ($status >= 200 && $status < 300) {
            $messageId = Arr::get($data, 'messages.0.id');

            return [
                'success' => true,
                'provider' => 'meta_waba',
                'meta_waba_account_id' => $this->settings instanceof MetaWabaAccount ? $this->settings->id : null,
                'phone_number_id' => $this->settings->phone_number_id,
                'data' => $messageId ? ($data + ['id' => $messageId, 'message_id' => $messageId, 'status' => 'sent']) : $data,
                'status' => $status,
                'method_used' => $method,
            ];
        }

        return [
            'success' => false,
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $this->settings instanceof MetaWabaAccount ? $this->settings->id : null,
            'phone_number_id' => $this->settings->phone_number_id,
            'error' => Arr::get($data, 'error.message') ?: Arr::get($data, 'message') ?: ($body ?: "HTTP {$status}"),
            'status' => $status,
            'response' => $data ?: $body,
            'method_used' => $method,
        ];
    }

    private function normalizeTemplateParameter(mixed $parameter): array
    {
        if (is_array($parameter) && isset($parameter['type'])) {
            return $parameter;
        }

        if (is_array($parameter) && array_key_exists('text', $parameter)) {
            return ['type' => 'text', 'text' => (string) $parameter['text']];
        }

        return ['type' => 'text', 'text' => (string) $parameter];
    }

    private function isWithinCustomerServiceWindow(string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $lastTen = substr($digits, -10);

        $conversation = WhatsAppConversation::query()
            ->where(function ($query) use ($digits, $lastTen) {
                $query->where('phone_number', $digits);

                if ($lastTen !== '') {
                    $query->orWhere('phone_number', $lastTen)
                        ->orWhere('phone_number', 'like', '%' . $lastTen);
                }
            })
            ->latest('last_inbound_at')
            ->first();

        return $conversation?->last_inbound_at
            ? $conversation->last_inbound_at->greaterThanOrEqualTo(now()->subHours(24))
            : false;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        return strlen($phone) === 10 ? '91' . $phone : $phone;
    }

    private function graphUrl(string $path): string
    {
        $version = trim((string) ($this->settings->graph_version ?: 'v20.0'), '/');

        return 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/');
    }

    private function notConfigured(): array
    {
        return [
            'success' => false,
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $this->settings instanceof MetaWabaAccount ? $this->settings->id : null,
            'phone_number_id' => $this->settings->phone_number_id,
            'error' => 'Meta WABA is not configured. Phone Number ID, Graph version, and access token are required.',
        ];
    }
}
