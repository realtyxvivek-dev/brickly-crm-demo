<?php

namespace App\Http\Controllers;

use App\Models\MetaWabaAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppQuickReply;
use App\Models\WhatsAppTemplate;
use App\Models\SystemSettings;
use App\Services\MetaWabaApiService;
use App\Services\WhatsAppApiService;
use App\Services\WhatsAppConversationScopeService;
use App\Services\WhatsAppLeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppChatController extends Controller
{
    protected $whatsappService;
    protected $metaWabaService;
    protected $scopeService;
    protected $automationService;

    public function __construct(
        WhatsAppApiService $whatsappService,
        MetaWabaApiService $metaWabaService,
        WhatsAppConversationScopeService $scopeService,
        WhatsAppLeadAutomationService $automationService
    )
    {
        $this->whatsappService = $whatsappService;
        $this->metaWabaService = $metaWabaService;
        $this->scopeService = $scopeService;
        $this->automationService = $automationService;
    }

    private function defaultSender(): string
    {
        return SystemSettings::get('whatsapp_default_sender', 'third_party') === 'meta_waba'
            ? 'meta_waba'
            : 'third_party';
    }

    private function metaServiceForConversation(?WhatsAppConversation $conversation = null, ?MetaWabaAccount $selectedAccount = null): MetaWabaApiService
    {
        $conversationAccount = $selectedAccount ?: $conversation?->metaWabaAccount;
        $account = $conversationAccount ?: MetaWabaAccount::defaultAccount();

        return $account
            ? $this->metaWabaService->forAccount($account)
            : $this->metaWabaService;
    }

    private function resolveMetaSendAccount(?WhatsAppConversation $conversation = null, ?int $accountId = null): ?MetaWabaAccount
    {
        return $accountId
            ? MetaWabaAccount::find($accountId)
            : ($conversation?->metaWabaAccount ?: MetaWabaAccount::defaultAccount());
    }

    private function sendOutboundText(string $phone, string $message, ?WhatsAppConversation $conversation = null, ?int $accountId = null): array
    {
        if ($this->defaultSender() === 'meta_waba') {
            $account = $this->resolveMetaSendAccount($conversation, $accountId);
            if (!$account?->is_active || !$account?->is_verified) {
                return [
                    'success' => false,
                    'provider' => 'meta_waba',
                    'meta_waba_account_id' => $account?->id,
                    'phone_number_id' => $account?->phone_number_id,
                    'error' => 'This API number is not verified for sending. Open API Settings and verify/register this number first.',
                ];
            }

            $result = $this->metaServiceForConversation($conversation, $account)->sendTextMessage($phone, $message);

            return $this->retryMetaSendWithDefaultAccount($result, $account, fn (MetaWabaAccount $fallbackAccount) => $this->metaServiceForConversation($conversation, $fallbackAccount)->sendTextMessage($phone, $message));
        }

        return $this->whatsappService->sendTextMessage($phone, $message);
    }

    private function sendOutboundTemplate(string $phone, string $templateName, array $parameters = [], ?string $language = null, ?WhatsAppConversation $conversation = null, ?int $accountId = null): array
    {
        if ($this->defaultSender() === 'meta_waba') {
            $account = $this->resolveMetaSendAccount($conversation, $accountId);
            if (!$account?->is_active || !$account?->is_verified) {
                return [
                    'success' => false,
                    'provider' => 'meta_waba',
                    'meta_waba_account_id' => $account?->id,
                    'phone_number_id' => $account?->phone_number_id,
                    'error' => 'This API number is not verified for sending. Open API Settings and verify/register this number first.',
                ];
            }

            $result = $this->metaServiceForConversation($conversation, $account)->sendTemplateMessage($phone, $templateName, $parameters, $language);

            return $this->retryMetaSendWithDefaultAccount($result, $account, fn (MetaWabaAccount $fallbackAccount) => $this->metaServiceForConversation($conversation, $fallbackAccount)->sendTemplateMessage($phone, $templateName, $parameters, $language));
        }

        return $this->whatsappService->sendTemplateMessage($phone, $templateName, $parameters, $language);
    }

    private function retryMetaSendWithDefaultAccount(array $result, ?MetaWabaAccount $attemptedAccount, callable $send): array
    {
        if (($result['success'] ?? false) || !$this->isMetaPermissionError((string) ($result['error'] ?? ''))) {
            if (!($result['success'] ?? false) && isset($result['error'])) {
                $result['error'] = $this->friendlyMetaSendError((string) $result['error']);
            }

            return $result;
        }

        $attemptedAccount?->forceFill([
            'connection_status' => 'failed',
            'last_error' => $this->friendlyMetaSendError((string) $result['error']),
        ])->save();

        $fallbackAccount = MetaWabaAccount::defaultAccount();
        if (!$fallbackAccount || (int) $fallbackAccount->id === (int) $attemptedAccount?->id || !$fallbackAccount->is_active || !$fallbackAccount->is_verified) {
            $result['error'] = $this->friendlyMetaSendError((string) ($result['error'] ?? 'Permission denied.'));

            return $result;
        }

        $fallbackResult = $send($fallbackAccount);
        if ($fallbackResult['success'] ?? false) {
            $fallbackResult['warning'] = 'Selected API number does not have Meta send permission. Message was sent from default API number instead.';
            $fallbackResult['fallback_from_meta_waba_account_id'] = $attemptedAccount?->id;
        } elseif (isset($fallbackResult['error'])) {
            $fallbackResult['error'] = $this->friendlyMetaSendError((string) $fallbackResult['error']);
        }

        return $fallbackResult;
    }

    private function isMetaPermissionError(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, '#200')
            || str_contains($error, 'necessary permissions')
            || str_contains($error, 'on behalf of this whatsapp business account');
    }

    private function friendlyMetaSendError(string $error): string
    {
        if ($this->isMetaPermissionError($error)) {
            return 'Selected API number ke Meta token me send permission nahi hai. API Settings me is number ko reconnect/verify/register karo, ya default API number use karo.';
        }

        return $error;
    }

    private function normalizeDirection(?string $direction): string
    {
        $value = strtolower(trim((string) $direction));

        return match ($value) {
            'sent', 'send', 'outgoing', 'from_me' => 'sent',
            default => 'received',
        };
    }

    private function formatMessage(WhatsAppMessage $message): array
    {
        $media = $this->extractMediaMetaFromStoredPayload($message->api_response);
        $displayMessage = $this->buildDisplayMessage($message->message, $media['type'], $media['is_voice']);

        return [
            'id' => $message->id,
            'direction' => $this->normalizeDirection($message->direction),
            'message' => $displayMessage,
            'status' => $message->status,
            'template_id' => $message->template_id,
            'type' => $media['type'],
            'media_url' => $media['url'],
            'mime_type' => $media['mime_type'],
            'is_voice' => $media['is_voice'],
            'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            'sent_at' => $message->sent_at ? $message->sent_at->format('Y-m-d H:i:s') : null,
        ];
    }

    private function serializeConversation(WhatsAppConversation $conversation, ?MetaWabaAccount $contextWabaAccount = null): array
    {
        $conversation->loadMissing([
            'lead.activeAssignments.assignedTo',
            'assignedTo',
            'user',
            'metaWabaAccount',
        ]);

        $latestMessage = $contextWabaAccount
            ? (WhatsAppMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('meta_waba_account_id', $contextWabaAccount->id)
                ->latest()
                ->first() ?: $conversation->getLatestMessage())
            : $conversation->getLatestMessage();
        $activeAssignment = $conversation->lead?->activeAssignments->first();
        $assignedUser = $conversation->assignedTo ?: $activeAssignment?->assignedTo;
        $wabaAccount = $contextWabaAccount ?: $conversation->metaWabaAccount;
        $wabaPhone = $wabaAccount?->display_phone_number ?: $wabaAccount?->name;
        $wabaCanSend = $this->defaultSender() !== 'meta_waba'
            || ($wabaAccount
                ? ((bool) $wabaAccount->is_active && (bool) $wabaAccount->is_verified)
                : (bool) MetaWabaAccount::defaultAccount()?->is_verified);

        return [
            'id' => $conversation->id,
            'phone_number' => $conversation->phone_number,
            'contact_name' => $conversation->contact_name,
            'user_id' => $conversation->user_id,
            'user_name' => $conversation->user ? $conversation->user->name : null,
            'status' => $conversation->status ?: 'open',
            'lead_id' => $conversation->lead_id,
            'assigned_user' => $assignedUser ? [
                'id' => $assignedUser->id,
                'name' => $assignedUser->name,
            ] : null,
            'lead' => $conversation->lead ? [
                'id' => $conversation->lead->id,
                'name' => $conversation->lead->name,
                'email' => $conversation->lead->email,
                'status' => $conversation->lead->status,
                'source' => $conversation->lead->source_label,
                'phone' => $conversation->lead->phone,
                'url' => route('leads.show', $conversation->lead->id),
            ] : null,
            'unread_count' => $conversation->getUnreadCount(),
            'latest_message' => $latestMessage ? $this->formatMessage($latestMessage) : null,
            'updated_at' => $conversation->updated_at->format('Y-m-d H:i:s'),
            'meta_waba_account_id' => $wabaAccount?->id,
            'waba_display_phone_number' => $wabaPhone,
            'waba_phone_number_id' => $wabaAccount?->phone_number_id,
            'waba_is_verified' => (bool) $wabaAccount?->is_verified,
            'waba_is_active' => (bool) $wabaAccount?->is_active,
            'waba_can_send' => $wabaCanSend,
            'waba_settings_url' => $wabaAccount
                ? route('integrations.meta-waba.index', ['account_id' => $wabaAccount->id])
                : route('integrations.meta-waba.index'),
        ];
    }

    private function buildDisplayMessage(?string $messageText, ?string $type, bool $isVoice = false): string
    {
        $text = trim((string) $messageText);
        $normalizedType = strtolower(trim((string) $type));

        if ($normalizedType === 'audio') {
            if ($isVoice) {
                return 'Voice message';
            }

            return 'Audio message';
        }

        if ($normalizedType === 'image' && ($text === '' || str_starts_with($text, '[Unsupported message type:'))) {
            return 'Image';
        }

        if ($normalizedType === 'interactive' && ($text === '' || str_starts_with($text, '[Unsupported message type:'))) {
            return 'Interactive reply';
        }

        if ($normalizedType === 'sticker' && ($text === '' || str_starts_with($text, '[Unsupported message type:'))) {
            return 'Sticker';
        }

        if ($normalizedType === 'video' && ($text === '' || str_starts_with($text, '[Unsupported message type:'))) {
            return 'Video';
        }

        if ($normalizedType === 'document' && ($text === '' || str_starts_with($text, '[Unsupported message type:'))) {
            return 'Document';
        }

        if ($normalizedType === 'unsupported') {
            return 'Unsupported WhatsApp message';
        }

        if ($normalizedType === 'image' && strcasecmp($text, '[Image]') === 0) {
            return 'Image';
        }

        if ($normalizedType === 'video' && strcasecmp($text, '[Video]') === 0) {
            return 'Video';
        }

        return $text !== '' ? $text : 'Message';
    }

    private function extractMediaMetaFromStoredPayload(?array $payload): array
    {
        $rawMessage = data_get($payload, 'parsed_message');

        if (!is_array($rawMessage)) {
            $rawMessage = data_get($payload, 'webhook_payload.entry.0.changes.0.value.messages.0');
        }

        return $this->extractMediaMetaFromRawMessage(is_array($rawMessage) ? $rawMessage : []);
    }

    private function extractMediaMetaFromRawMessage(array $message): array
    {
        $type = strtolower((string) ($message['type'] ?? 'text'));
        $audio = is_array($message['audio'] ?? null) ? $message['audio'] : [];
        $document = is_array($message['document'] ?? null) ? $message['document'] : [];
        $image = is_array($message['image'] ?? null) ? $message['image'] : [];
        $sticker = is_array($message['sticker'] ?? null) ? $message['sticker'] : [];
        $video = is_array($message['video'] ?? null) ? $message['video'] : [];

        $url = null;
        $mimeType = null;
        $isVoice = false;

        if ($type === 'audio') {
            $url = $this->resolveMediaUrl($audio);
            $mimeType = $audio['mime_type'] ?? $audio['mime'] ?? 'audio/ogg';
            $isVoice = (bool) ($audio['voice'] ?? false);
        } elseif ($type === 'document') {
            $url = $this->resolveMediaUrl($document);
            $mimeType = $document['mime_type'] ?? $document['mime'] ?? null;
        } elseif ($type === 'image') {
            $url = $this->resolveMediaUrl($image);
            $mimeType = $image['mime_type'] ?? $image['mime'] ?? null;
        } elseif ($type === 'sticker') {
            $url = $this->resolveMediaUrl($sticker);
            $mimeType = $sticker['mime_type'] ?? $sticker['mime'] ?? 'image/webp';
        } elseif ($type === 'video') {
            $url = $this->resolveMediaUrl($video);
            $mimeType = $video['mime_type'] ?? $video['mime'] ?? null;
        }

        return [
            'type' => $type,
            'url' => $url,
            'mime_type' => $mimeType,
            'is_voice' => $isVoice,
        ];
    }

    private function resolveMediaUrl(array $media): ?string
    {
        foreach ([
            'url',
            'link',
            'src',
            'download_url',
            'file_url',
            'preview_url',
            'media_url',
            'path',
        ] as $key) {
            $value = trim((string) ($media[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function templateSendSucceeded(array $result): bool
    {
        if (!($result['success'] ?? false)) {
            return false;
        }

        $status = strtolower((string) data_get($result, 'data.status', ''));
        if ($status === 'error' || $status === 'failed' || $status === 'failure') {
            return false;
        }

        return true;
    }

    private function templateSendError(array $result): string
    {
        return data_get($result, 'data.message')
            ?: data_get($result, 'error')
            ?: 'Failed to send template message';
    }

    private function selectedTemplateAccount(Request $request): ?MetaWabaAccount
    {
        $accountId = $request->input('account_id') ?: $request->query('account_id');

        return $accountId
            ? MetaWabaAccount::query()->find((int) $accountId)
            : MetaWabaAccount::defaultAccount();
    }

    private function syncMetaTemplatesForAccount(?MetaWabaAccount $account): array
    {
        $service = $account ? $this->metaWabaService->forAccount($account) : $this->metaWabaService;
        $result = $service->getTemplates();

        if (!($result['success'] ?? false)) {
            return $result;
        }

        $synced = 0;
        foreach (($result['data'] ?? []) as $template) {
            $template = WhatsAppTemplate::normalizeTemplatePayload(is_array($template) ? $template : []);
            $templateId = $template['id'] ?? $template['template_id'] ?? $template['name'] ?? null;

            if (!$templateId) {
                continue;
            }

            WhatsAppTemplate::updateOrCreate(
                [
                    'template_id' => (string) $templateId,
                    'meta_waba_account_id' => $account?->id,
                ],
                [
                    'provider' => 'meta_waba',
                    'meta_waba_account_id' => $account?->id,
                    'name' => $template['name'] ?? 'Untitled Template',
                    'content' => WhatsAppTemplate::extractContent($template),
                    'components' => $template['components'] ?? [],
                    'raw_payload' => $template,
                    'category' => $template['category'] ?? null,
                    'language' => $template['language'] ?? data_get($template, 'language.code') ?? 'en_US',
                    'status' => strtoupper((string) ($template['status'] ?? 'APPROVED')),
                    'rejection_reason' => data_get($template, 'rejected_reason'),
                    'is_active' => strtoupper((string) ($template['status'] ?? 'APPROVED')) === 'APPROVED',
                ]
            );
            $synced++;
        }

        return [
            'success' => true,
            'provider' => 'meta_waba',
            'synced_count' => $synced,
            'data' => $result['data'] ?? [],
        ];
    }

    private function resolveTemplateForConversation(string $templateId, ?int $accountId = null): ?WhatsAppTemplate
    {
        $query = WhatsAppTemplate::query()->where('template_id', $templateId);
        if ($accountId) {
            $query->where('meta_waba_account_id', $accountId);
        }
        $template = $query->first() ?: WhatsAppTemplate::where('template_id', $templateId)->first();

        if ($template && filled($template->content)) {
            return $template;
        }

        if ($accountId) {
            $this->syncMetaTemplatesForAccount(MetaWabaAccount::query()->find($accountId));
            $template = WhatsAppTemplate::query()
                ->where('template_id', $templateId)
                ->where('meta_waba_account_id', $accountId)
                ->first();

            if ($template) {
                return $template;
            }
        }

        $result = $this->whatsappService->getTemplate($templateId);
        if (!($result['success'] ?? false)) {
            return $template;
        }

        $payload = \App\Models\WhatsAppTemplate::normalizeTemplatePayload($result['data'] ?? []);
        $resolvedTemplateId = $payload['id'] ?? $payload['template_id'] ?? $templateId;

        $template = WhatsAppTemplate::updateOrCreate(
            ['template_id' => (string) $resolvedTemplateId],
            [
                'name' => $payload['name'] ?? ($template?->name ?: 'Untitled Template'),
                'content' => WhatsAppTemplate::extractContent($payload),
                'category' => $payload['category'] ?? $template?->category,
                'language' => $payload['language'] ?? data_get($payload, 'language.code') ?? ($template?->language ?: 'en'),
                'is_active' => (($payload['status'] ?? 'APPROVED') === 'APPROVED'),
            ]
        );

        return $template;
    }

    private function extractMessagesFromConversationPayload(array $result, WhatsAppConversation $conversation): array
    {
        $targetPhone = preg_replace('/[^0-9]/', '', $conversation->phone_number);
        $variants = array_values(array_unique(array_filter([
            $targetPhone,
            str_starts_with($targetPhone, '91') && strlen($targetPhone) === 12 ? substr($targetPhone, 2) : null,
            strlen($targetPhone) === 10 ? '91' . $targetPhone : null,
        ])));

        $payload = $result['data']['conversations'] ?? $result['data'] ?? [];
        if (!is_array($payload)) {
            return [];
        }

        foreach ($payload as $conversationPayload) {
            $candidatePhone = preg_replace('/[^0-9]/', '', (string) ($conversationPayload['phone'] ?? ''));

            if ($candidatePhone === '' || !in_array($candidatePhone, $variants, true)) {
                continue;
            }

            $messages = $conversationPayload['messages'] ?? [];
            return is_array($messages) ? $messages : [];
        }

        return [];
    }

    /**
     * Display chat interface
     */
    public function index()
    {
        $user = Auth::user();

        $conversations = $this->scopeService->conversationsFor($user)
            ->with(['messages' => function ($query) {
                $query->latest()->limit(1);
            }, 'lead.activeAssignments.assignedTo', 'assignedTo', 'user', 'metaWabaAccount'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Auto-link conversations to leads if phone matches
        foreach ($conversations as $conversation) {
            if (!$conversation->lead_id) {
                $this->autoLinkToLead($conversation);
            }
        }

        $quickReplies = WhatsAppQuickReply::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'message']);

        $wabaAccounts = MetaWabaAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'name', 'display_phone_number', 'phone_number_id', 'is_default', 'is_verified', 'is_active']);

        return view('chat.index', compact('conversations', 'quickReplies', 'wabaAccounts'));
    }

    /**
     * Auto-link conversation to lead if phone number matches
     */
    private function autoLinkToLead(WhatsAppConversation $conversation)
    {
        try {
            $phone = preg_replace('/[^0-9]/', '', (string) $conversation->phone_number);

            if ($phone === '') {
                return;
            }

            if (strlen($phone) === 10) {
                $phone = '91' . $phone;
            }

            $resolved = $this->automationService->resolveInboundConversation(
                $phone,
                $conversation->contact_name
            )['conversation'] ?? null;

            if (!$resolved instanceof WhatsAppConversation) {
                return;
            }

            if ($resolved->id !== $conversation->id) {
                $conversation->fill([
                    'user_id' => $resolved->user_id,
                    'lead_id' => $resolved->lead_id,
                    'contact_name' => $conversation->contact_name ?: $resolved->contact_name,
                ])->save();

                return;
            }

            $updates = [
                'user_id' => $resolved->user_id,
                'lead_id' => $resolved->lead_id,
            ];

            if (!$conversation->contact_name && $resolved->contact_name) {
                $updates['contact_name'] = $resolved->contact_name;
            }

            $conversation->fill($updates)->save();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp conversation auto-link repair failed', [
                'conversation_id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(Request $request)
    {
        $user = Auth::user();
        $accountId = $request->filled('account_id') && $request->query('account_id') !== 'all'
            ? (int) $request->query('account_id')
            : null;
        $contextWabaAccount = $accountId ? MetaWabaAccount::find($accountId) : null;

        $query = $this->scopeService->conversationsFor($user)
            ->with(['messages' => function ($query) use ($accountId) {
                if ($accountId) {
                    $query->where('meta_waba_account_id', $accountId);
                }
                $query->latest()->limit(1);
            }, 'lead.activeAssignments.assignedTo', 'assignedTo', 'user', 'metaWabaAccount']);

        if ($accountId) {
            $query->where(function ($builder) use ($accountId) {
                $builder->where('meta_waba_account_id', $accountId)
                    ->orWhereHas('messages', function ($messageQuery) use ($accountId) {
                        $messageQuery->where('meta_waba_account_id', $accountId);
                    });
            });
        }

        $conversations = $query->orderBy('updated_at', 'desc')
            ->get()
            ->map(function($conversation) use ($contextWabaAccount) {
                // Auto-link to lead if not linked
                if (!$conversation->lead_id) {
                    $this->autoLinkToLead($conversation);
                    $conversation->refresh();
                }

                return $this->serializeConversation($conversation, $contextWabaAccount);
            });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Create new conversation (add number)
     */
    public function getLeads(Request $request)
    {
        $user = Auth::user();
        $query = $this->scopeService->visibleLeadsFor($user)
            ->select('id', 'name', 'phone', 'status');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                   ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $leads = $query->latest()->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $leads,
        ]);
    }

    public function createConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|regex:/^[0-9+\-\s()]+$/',
            'contact_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $request->phone_number);
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        // Check if conversation already exists
        $conversation = WhatsAppConversation::where('user_id', Auth::id())
            ->where('phone_number', $phone)
            ->first();

        if ($conversation) {
            return response()->json([
                'success' => true,
                'message' => 'Conversation already exists',
                'data' => [
                    'id' => $conversation->id,
                    'phone_number' => $conversation->phone_number,
                    'contact_name' => $conversation->contact_name,
                ],
            ]);
        }

        // Create new conversation
        $conversation = WhatsAppConversation::create([
            'user_id' => Auth::id(),
            'phone_number' => $phone,
            'contact_name' => $request->contact_name,
        ]);

        // Auto-link to lead if phone matches
        $this->autoLinkToLead($conversation);
        $conversation->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Conversation created successfully',
            'data' => [
                'id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'contact_name' => $conversation->contact_name,
                'lead_id' => $conversation->lead_id,
                'lead' => $conversation->lead ? [
                    'id' => $conversation->lead->id,
                    'name' => $conversation->lead->name,
                    'status' => $conversation->lead->status,
                ] : null,
            ],
        ]);
    }

    /**
     * Get conversation with messages
     */
    private function syncMessagesFromAPI(\App\Models\WhatsAppConversation $conversation): void
    {
        try {
            $phone = preg_replace('/[^0-9]/', '', $conversation->phone_number);
            $localPhone = str_starts_with($phone, '91') && strlen($phone) === 12
                ? substr($phone, 2)
                : $phone;

            $result = $this->whatsappService->getMessages($localPhone);
            $messages = ($result['success'] ?? false) && is_array($result['data'] ?? null)
                ? $result['data']
                : [];

            if (empty($messages)) {
                $conversationResult = $this->whatsappService->getConversations($phone);
                $messages = $this->extractMessagesFromConversationPayload($conversationResult, $conversation);
            }

            if (!is_array($messages) || empty($messages)) {
                return;
            }

            foreach ($messages as $msg) {
                $media = $this->extractMediaMetaFromRawMessage(is_array($msg) ? $msg : []);
                $messageText = $msg['message']
                    ?? $msg['body']
                    ?? $msg['text']
                    ?? $msg['value']
                    ?? $msg['original_message']
                    ?? null;
                $direction = $this->normalizeDirection(
                    $msg['direction']
                    ?? (($msg['from_me'] ?? false) ? 'outgoing' : null)
                    ?? (($msg['is_message_by_contact'] ?? false) ? 'incoming' : 'outgoing')
                );
                $externalId = $msg['fb_message_id'] ?? $msg['id'] ?? $msg['message_id'] ?? null;
                $sentAt = $msg['created_at'] ?? $msg['timestamp'] ?? $msg['reply_at'] ?? null;
                $type = $msg['type'] ?? $media['type'] ?? 'text';

                if ((!$messageText && !$media['url']) || !$externalId) continue;

                \App\Models\WhatsAppMessage::updateOrCreate(
                    ['message_id' => (string)$externalId, 'conversation_id' => $conversation->id],
                    [
                        'direction' => $direction,
                        'message' => $this->buildDisplayMessage($messageText, $type, (bool) ($media['is_voice'] ?? false)),
                        'status' => $msg['status'] ?? 'delivered',
                        'api_response' => [
                            'synced_message' => $msg,
                            'message_type' => $type,
                        ],
                        'sent_at' => $sentAt ? \Carbon\Carbon::parse($sentAt) : now(),
                    ]
                );
            }

            $conversation->touch();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('WhatsApp sync failed: ' . $e->getMessage());
        }
    }

    public function getConversation($id)
    {
        $user = Auth::user();
        $accountId = request()->filled('account_id') && request()->query('account_id') !== 'all'
            ? (int) request()->query('account_id')
            : null;
        $contextWabaAccount = $accountId ? MetaWabaAccount::find($accountId) : null;

        $conversation = $this->scopeService->conversationsFor($user)
            ->with(['messages', 'user', 'assignedTo', 'lead.activeAssignments.assignedTo', 'metaWabaAccount'])
            ->where('id', $id)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or unauthorized',
            ], 404);
        }

        // Auto-link to lead if not linked
        if (!$conversation->lead_id) {
            $this->autoLinkToLead($conversation);
            $conversation->refresh();
        }

        // Mark as read
        $conversation->markAsRead();

        $this->syncMessagesFromAPI($conversation);

        $conversation->load('messages', 'user', 'assignedTo', 'lead.activeAssignments.assignedTo', 'metaWabaAccount');
        $messagesQuery = $conversation->messages()->orderBy('created_at');
        if ($accountId) {
            $messagesQuery->where('meta_waba_account_id', $accountId);
        }
        $messages = $messagesQuery->get()->map(fn ($message) => $this->formatMessage($message));

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $this->serializeConversation($conversation, $contextWabaAccount),
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Send message
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'message' => 'required|string|max:4096',
            'account_id' => 'nullable|exists:meta_waba_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $conversation = $this->scopeService->resolveConversation(Auth::user(), $request->conversation_id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        try {
            // Send message via API
            $result = $this->sendOutboundText(
                $conversation->phone_number,
                $request->message,
                $conversation,
                $request->filled('account_id') ? (int) $request->account_id : null
            );

            // Map API status to database status
            $dbStatus = 'sent'; // Default
            if ($result['success']) {
                $apiStatus = $result['data']['status'] ?? null;
                // Map API status values to database enum values
                if ($apiStatus === 'success' || $apiStatus === 'sent' || $apiStatus === 'delivered' || $apiStatus === 'read') {
                    $dbStatus = $apiStatus === 'success' ? 'sent' : $apiStatus;
                } else {
                    $dbStatus = 'sent'; // Default to sent if status is unknown
                }
            } else {
                $dbStatus = 'failed';
            }

            // Save message to database
            $externalMessageId = $result['success'] ? ($result['data']['id'] ?? $result['data']['message_id'] ?? null) : null;
            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => Auth::id(),
                'direction' => 'sent',
                'message' => $request->message,
                'message_id' => $externalMessageId,
                'provider' => $result['provider'] ?? $this->defaultSender(),
                'meta_waba_account_id' => $result['meta_waba_account_id'] ?? $conversation->meta_waba_account_id,
                'external_message_id' => $externalMessageId,
                'provider_status' => $result['data']['status'] ?? ($result['success'] ? 'sent' : 'failed'),
                'status' => $dbStatus,
                'error_message' => $result['success'] ? null : $this->friendlyMetaSendError($result['error'] ?? 'Failed to send message'),
                'api_response' => $result,
                'sent_at' => $result['success'] ? now() : null,
            ]);

            // Update conversation timestamp
            $conversation->touch();

            if (Auth::user()->isAssistantSalesManager() || Auth::user()->isSeniorManager()) {
                Log::info('Scoped WhatsApp message sent', [
                    'sender_user_id' => Auth::id(),
                    'sender_role' => Auth::user()->role?->slug,
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'message_type' => 'text',
                    'sent_at' => now()->toDateTimeString(),
                ]);
            }

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['warning'] ?? 'Message sent successfully',
                    'data' => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'message' => $message->message,
                        'status' => $message->status,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message',
                    'error' => $this->friendlyMetaSendError($result['error'] ?? 'Unknown error'),
                    'data' => [
                        'id' => $message->id,
                        'status' => $message->status,
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'template_id' => 'required|string',
            'parameters' => 'nullable|array',
            'account_id' => 'nullable|exists:meta_waba_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $conversation = $this->scopeService->resolveConversation($user, $request->conversation_id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        try {
            // Get template content
            $selectedAccountId = $request->filled('account_id') ? (int) $request->account_id : null;
            $template = $this->resolveTemplateForConversation($request->template_id, $selectedAccountId);
            $messageContent = $template?->content ?: ('Template: ' . ($template?->name ?: $request->template_id));

            // Send template message via API
            $result = $this->sendOutboundTemplate(
                $conversation->phone_number,
                $template?->name ?: $request->template_id,
                $request->parameters ?? [],
                $template?->language,
                $conversation,
                $selectedAccountId
            );

            // Map API status to database status
            $sendSucceeded = $this->templateSendSucceeded($result);
            $dbStatus = 'sent'; // Default
            if ($sendSucceeded) {
                $apiStatus = $result['data']['status'] ?? null;
                // Map API status values to database enum values
                if ($apiStatus === 'success' || $apiStatus === 'sent' || $apiStatus === 'delivered' || $apiStatus === 'read') {
                    $dbStatus = $apiStatus === 'success' ? 'sent' : $apiStatus;
                } else {
                    $dbStatus = 'sent'; // Default to sent if status is unknown
                }
            } else {
                $dbStatus = 'failed';
            }

            // Save message to database
            $externalMessageId = $sendSucceeded ? ($result['data']['id'] ?? $result['data']['message_id'] ?? null) : null;
            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => Auth::id(),
                'direction' => 'sent',
                'message' => $messageContent ?: ($template?->name ?: 'Template message'),
                'message_id' => $externalMessageId,
                'template_id' => $request->template_id,
                'provider' => $result['provider'] ?? $this->defaultSender(),
                'meta_waba_account_id' => $result['meta_waba_account_id'] ?? $conversation->meta_waba_account_id,
                'external_message_id' => $externalMessageId,
                'provider_status' => $result['data']['status'] ?? ($sendSucceeded ? 'sent' : 'failed'),
                'status' => $dbStatus,
                'error_message' => $sendSucceeded ? null : $this->friendlyMetaSendError($this->templateSendError($result)),
                'api_response' => $result,
                'sent_at' => $sendSucceeded ? now() : null,
            ]);

            // Update conversation timestamp
            $conversation->touch();

            if ($user->isAssistantSalesManager() || $user->isSeniorManager()) {
                Log::info('Scoped WhatsApp template sent', [
                    'sender_user_id' => $user->id,
                    'sender_role' => $user->role?->slug,
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'message_type' => 'template',
                    'template_id' => $request->template_id,
                    'sent_at' => now()->toDateTimeString(),
                ]);
            }

            if ($sendSucceeded) {
                return response()->json([
                    'success' => true,
                    'message' => $result['warning'] ?? 'Template message sent successfully',
                    'data' => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'message' => $message->message,
                        'template_id' => $message->template_id,
                        'status' => $message->status,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send template message',
                    'error' => $this->friendlyMetaSendError($this->templateSendError($result)),
                    'data' => [
                        'id' => $message->id,
                        'status' => $message->status,
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Template Message Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending template message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available templates
     */
    public function getTemplates(Request $request)
    {
        $account = $this->selectedTemplateAccount($request);
        $templates = WhatsAppTemplate::query()
            ->where('is_active', true)
            ->when($account, fn ($query) => $query->where('meta_waba_account_id', $account->id))
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty() || $templates->contains(fn ($template) => blank($template->content))) {
            $apiResult = $this->syncMetaTemplatesForAccount($account);
            if ($apiResult['success'] ?? false) {
                $templates = WhatsAppTemplate::query()
                    ->where('is_active', true)
                    ->when($account, fn ($query) => $query->where('meta_waba_account_id', $account->id))
                    ->orderBy('name')
                    ->get();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $templates->map(function($template) {
                return [
                    'id' => $template->id,
                    'template_id' => $template->template_id,
                    'name' => $template->name,
                    'content' => $template->content,
                    'category' => $template->category,
                    'language' => $template->language,
                    'template_name' => $template->name,
                    'meta_waba_account_id' => $template->meta_waba_account_id,
                ];
            }),
        ]);
    }

    public function getTemplate($id)
    {
        $template = $this->resolveTemplateForConversation($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $template->id,
                'template_id' => $template->template_id,
                'name' => $template->name,
                'content' => $template->content,
                'category' => $template->category,
                'language' => $template->language,
                'template_name' => $template->name,
            ],
        ]);
    }

    public function syncMessages($id)
    {
        $user = Auth::user();

        $conversation = $this->scopeService->resolveConversation($user, $id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        $accountId = request()->filled('account_id') && request()->query('account_id') !== 'all'
            ? (int) request()->query('account_id')
            : null;

        $this->syncMessagesFromAPI($conversation);
        $messagesQuery = $conversation->messages()->orderBy('created_at');
        if ($accountId) {
            $messagesQuery->where('meta_waba_account_id', $accountId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messagesQuery->get()
                    ->map(fn ($message) => $this->formatMessage($message)),
            ],
        ]);
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $conversation = $this->scopeService->resolveConversation($user, $id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        $conversation->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read',
        ]);
    }

    /**
     * Delete conversation
     */
    public function deleteConversation($id)
    {
        $user = Auth::user();

        $conversation = $this->scopeService->resolveConversation($user, $id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully',
        ]);
    }

    /**
     * Sync templates from API
     */
    public function syncTemplates(Request $request)
    {
        try {
            $account = $this->selectedTemplateAccount($request);
            $apiResult = $this->syncMetaTemplatesForAccount($account);

            if (!($apiResult['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $apiResult['error'] ?? 'Failed to sync Meta WABA templates.',
                    'error' => $apiResult['error'] ?? 'Template sync failed',
                    'details' => $apiResult,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully synced ' . (int) ($apiResult['synced_count'] ?? 0) . ' Meta template(s).',
                'synced_count' => (int) ($apiResult['synced_count'] ?? 0),
                'meta_waba_account_id' => $account?->id,
                'total_templates' => WhatsAppTemplate::query()
                    ->when($account, fn ($query) => $query->where('meta_waba_account_id', $account->id))
                    ->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp Sync Templates Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error syncing templates: ' . $e->getMessage(),
            ], 500);
        }
    }
}
