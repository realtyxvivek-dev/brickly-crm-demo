<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaWabaAccount;
use App\Models\Lead;
use App\Models\SystemSettings;
use App\Models\User;
use App\Models\WabaCampaign;
use App\Models\WabaCampaignRecipient;
use App\Models\WhatsAppAutomationJourney;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppConversationNote;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppQuickReply;
use App\Models\WhatsAppRoutingRule;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppAnalyticsService;
use App\Services\WhatsAppComplianceService;
use App\Services\WabaCampaignService;
use App\Services\WhatsAppAutomationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class WhatsAppControlCenterController extends Controller
{
    public function index(
        Request $request,
        WhatsAppAutomationService $automationService,
        WhatsAppAnalyticsService $analyticsService,
        WhatsAppComplianceService $complianceService
    ): View
    {
        $tab = in_array($request->query('tab'), ['overview', 'chats', 'templates', 'send-test', 'campaigns', 'numbers', 'logs', 'quick-replies', 'automation', 'routing', 'analytics', 'health'], true)
            ? $request->query('tab')
            : 'overview';

        $accounts = Schema::hasTable('meta_waba_accounts')
            ? MetaWabaAccount::query()->orderByDesc('is_default')->orderBy('name')->get()
            : collect();
        $defaultAccount = MetaWabaAccount::defaultAccount();
        $defaultSender = SystemSettings::get('whatsapp_default_sender', 'third_party');
        $selectedTemplateAccountId = $accounts->contains('id', (int) $request->query('account_id'))
            ? (int) $request->query('account_id')
            : $defaultAccount?->id;
        $templateStatusFilter = in_array($request->query('status_filter'), ['approved', 'pending', 'rejected'], true)
            ? $request->query('status_filter')
            : 'all';

        $templateQuery = WhatsAppTemplate::query()->where('provider', 'meta_waba');
        $templateListQuery = (clone $templateQuery)
            ->when($selectedTemplateAccountId, fn ($query) => $query->where('meta_waba_account_id', $selectedTemplateAccountId))
            ->where('status', '!=', 'REMOVED');
        $messageQuery = WhatsAppMessage::query()
            ->where(function ($query) {
                $query->where('provider', 'meta_waba')
                    ->orWhereNotNull('meta_waba_account_id');
            });

        $conversationQuery = WhatsAppConversation::query();
        if (Schema::hasColumn('whatsapp_conversations', 'status')) {
            $conversationQuery->where('status', '!=', 'resolved');
        }

        $stats = [
            'accounts' => $accounts->count(),
            'verified_accounts' => $accounts->where('is_verified', true)->count(),
            'templates_total' => (clone $templateQuery)->count(),
            'templates_approved' => (clone $templateQuery)->where('status', 'APPROVED')->count(),
            'templates_pending' => (clone $templateQuery)->whereIn('status', ['PENDING', 'IN_REVIEW'])->count(),
            'templates_rejected' => (clone $templateQuery)->where('status', 'REJECTED')->count(),
            'conversations' => (clone $conversationQuery)->count(),
            'unassigned_conversations' => Schema::hasColumn('whatsapp_conversations', 'assigned_to')
                ? (clone $conversationQuery)->whereNull('assigned_to')->count()
                : 0,
            'unread' => WhatsAppMessage::query()->where('direction', 'received')->where('status', '!=', 'read')->count(),
            'messages_sent' => (clone $messageQuery)->whereIn('direction', ['sent', 'outgoing'])->count(),
            'messages_failed' => (clone $messageQuery)->where('status', 'failed')->count(),
        ];

        $templateTabStats = [
            'total' => (clone $templateListQuery)->count(),
            'approved' => (clone $templateListQuery)->where('status', 'APPROVED')->count(),
            'pending' => (clone $templateListQuery)->whereIn('status', ['PENDING', 'IN_REVIEW'])->count(),
            'rejected' => (clone $templateListQuery)->where('status', 'REJECTED')->count(),
        ];

        $templates = (clone $templateListQuery)
            ->when($templateStatusFilter === 'approved', fn ($query) => $query->where('status', 'APPROVED'))
            ->when($templateStatusFilter === 'pending', fn ($query) => $query->whereIn('status', ['PENDING', 'IN_REVIEW']))
            ->when($templateStatusFilter === 'rejected', fn ($query) => $query->where('status', 'REJECTED'))
            ->with('metaWabaAccount')
            ->latest()
            ->limit(50)
            ->get();

        $approvedTemplates = (clone $templateQuery)
            ->where('status', 'APPROVED')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'language', 'content', 'meta_waba_account_id']);

        $chatFilter = $request->query('chat_filter', 'all');
        $recentConversations = WhatsAppConversation::query()
            ->with(['lead.activeAssignments.assignedTo', 'assignedTo', 'metaWabaAccount'])
            ->withCount(['messages as unread_count' => function ($query) {
                $query->where('direction', 'received')->where('status', '!=', 'read');
            }])
            ->when(Schema::hasColumn('whatsapp_conversations', 'assigned_to') && $chatFilter === 'mine', fn ($query) => $query->where('assigned_to', auth()->id()))
            ->when(Schema::hasColumn('whatsapp_conversations', 'assigned_to') && $chatFilter === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when($chatFilter === 'unread', fn ($query) => $query->whereHas('messages', fn ($messageQuery) => $messageQuery->where('direction', 'received')->where('status', '!=', 'read')))
            ->latest('updated_at')
            ->limit(25)
            ->get();

        $recentMessages = (clone $messageQuery)
            ->with(['conversation', 'metaWabaAccount'])
            ->latest()
            ->limit(15)
            ->get();
        $recentTestMessages = (clone $messageQuery)
            ->with(['conversation', 'metaWabaAccount'])
            ->whereIn('direction', ['sent', 'outgoing'])
            ->whereNotNull('template_id')
            ->latest()
            ->limit(20)
            ->get();

        $recentCampaigns = Schema::hasTable('waba_campaigns')
            ? WabaCampaign::query()->with(['template', 'metaWabaAccount'])->withCount('recipients')->latest()->limit(20)->get()
            : collect();
        $failedRecipients = Schema::hasTable('waba_campaign_recipients')
            ? WabaCampaignRecipient::query()->with(['campaign.template', 'lead'])->where('status', WabaCampaignRecipient::STATUS_FAILED)->latest()->limit(15)->get()
            : collect();

        $selectedCampaign = null;
        $campaignRecipients = collect();
        if (Schema::hasTable('waba_campaigns') && filled($request->query('campaign_id'))) {
            $selectedCampaign = WabaCampaign::query()
                ->with(['template', 'metaWabaAccount'])
                ->find($request->query('campaign_id'));
            if ($selectedCampaign) {
                $campaignRecipients = $selectedCampaign->recipients()->with('lead')->latest()->limit(100)->get();
            }
        }

        $quickReplies = Schema::hasTable('whatsapp_quick_replies')
            ? WhatsAppQuickReply::query()->with('creator')->latest()->get()
            : collect();

        $assignableUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id']);

        $automationService->syncPresets(auth()->id());
        $automationOverview = $automationService->overviewMetrics();
        $automationJourneys = Schema::hasTable('whatsapp_automation_journeys')
            ? WhatsAppAutomationJourney::query()->withCount(['rules', 'logs'])->with('template')->orderByDesc('is_active')->orderBy('name')->limit(12)->get()
            : collect();
        $automationRules = Schema::hasTable('whatsapp_automation_rules')
            ? WhatsAppAutomationRule::query()->with(['journey', 'template', 'metaWabaAccount'])->orderBy('priority')->limit(20)->get()
            : collect();
        $automationLogs = Schema::hasTable('whatsapp_automation_logs')
            ? WhatsAppAutomationLog::query()->with(['journey', 'rule', 'lead', 'metaWabaAccount'])->latest()->limit(20)->get()
            : collect();
        $routingRules = Schema::hasTable('whatsapp_routing_rules')
            ? WhatsAppRoutingRule::query()->with(['metaWabaAccount', 'creator'])->orderBy('priority')->orderBy('name')->get()
            : collect();
        $analytics = $analyticsService->dashboard($request);
        $health = $complianceService->health() + [
            'default_account_verified' => (bool) $defaultAccount?->is_verified,
            'default_account_error' => $defaultAccount?->last_error,
            'failed_messages' => $stats['messages_failed'],
            'failed_recipients' => $failedRecipients->count(),
        ];
        $canManageWhatsApp = $this->canManageWhatsApp($request);

        return view('admin.whatsapp-control-center.index', compact(
            'tab',
            'chatFilter',
            'accounts',
            'defaultAccount',
            'defaultSender',
            'stats',
            'templates',
            'templateTabStats',
            'selectedTemplateAccountId',
            'templateStatusFilter',
            'approvedTemplates',
            'recentConversations',
            'recentMessages',
            'recentTestMessages',
            'recentCampaigns',
            'failedRecipients',
            'selectedCampaign',
            'campaignRecipients',
            'quickReplies',
            'assignableUsers',
            'automationOverview',
            'automationJourneys',
            'automationRules',
            'automationLogs',
            'routingRules',
            'analytics',
            'health',
            'canManageWhatsApp'
        ));
    }

    public function storeRoutingRule(Request $request): JsonResponse
    {
        abort_unless($this->canManageWhatsApp($request), 403);

        $validated = $this->validateRoutingRule($request);
        WhatsAppRoutingRule::create($validated + [
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'WhatsApp routing rule created.']);
    }

    public function updateRoutingRule(Request $request, WhatsAppRoutingRule $routingRule): JsonResponse
    {
        abort_unless($this->canManageWhatsApp($request), 403);

        $routingRule->update($this->validateRoutingRule($request) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => 'WhatsApp routing rule updated.']);
    }

    public function deleteRoutingRule(Request $request, WhatsAppRoutingRule $routingRule): JsonResponse
    {
        abort_unless($this->canManageWhatsApp($request), 403);

        $routingRule->delete();

        return response()->json(['success' => true, 'message' => 'WhatsApp routing rule deleted.']);
    }

    public function assignConversation(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $conversation->forceFill(['assigned_to' => $validated['assigned_to'] ?? null])->save();

        return response()->json(['success' => true, 'message' => 'Conversation assignment updated.']);
    }

    public function updateConversationStatus(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,pending_reply,resolved',
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'resolved') {
            $updates['resolved_at'] = now();
            $updates['resolved_by'] = auth()->id();
        } else {
            $updates['resolved_at'] = null;
            $updates['resolved_by'] = null;
        }

        $conversation->forceFill($updates)->save();

        return response()->json(['success' => true, 'message' => 'Conversation status updated.']);
    }

    public function storeConversationNote(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        WhatsAppConversationNote::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'note' => $validated['note'],
        ]);

        return response()->json(['success' => true, 'message' => 'Note saved.']);
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'message' => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        WhatsAppQuickReply::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Quick reply created.']);
    }

    public function updateQuickReply(Request $request, WhatsAppQuickReply $quickReply): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'message' => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $quickReply->update([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => 'Quick reply updated.']);
    }

    public function deleteQuickReply(WhatsAppQuickReply $quickReply): JsonResponse
    {
        $quickReply->delete();

        return response()->json(['success' => true, 'message' => 'Quick reply deleted.']);
    }

    public function retryRecipient(WabaCampaignRecipient $recipient, WabaCampaignService $campaignService): JsonResponse
    {
        if ($recipient->status !== WabaCampaignRecipient::STATUS_FAILED) {
            return response()->json(['success' => false, 'message' => 'Only failed recipients can be retried.'], 422);
        }

        $success = $campaignService->retryRecipient($recipient);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Recipient retried successfully.' : 'Retry failed. Check latest error in logs.',
        ], $success ? 200 : 422);
    }

    public function updateLeadOptOut(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'opted_out' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $lead->forceFill([
            'whatsapp_opted_out_at' => $validated['opted_out'] ? now() : null,
            'whatsapp_opt_out_reason' => $validated['opted_out'] ? ($validated['reason'] ?: 'Manual opt-out from WhatsApp Control Center') : null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => $validated['opted_out'] ? 'Lead opted out from WhatsApp.' : 'Lead opted back in for WhatsApp.',
        ]);
    }

    private function validateRoutingRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'priority' => 'required|integer|min:1|max:999',
            'meta_waba_account_id' => 'required|integer|exists:meta_waba_accounts,id',
            'conditions' => 'nullable|array',
            'conditions.sources' => 'nullable|array',
            'conditions.sources.*' => 'nullable|string|max:100',
            'conditions.cities' => 'nullable|array',
            'conditions.cities.*' => 'nullable|string|max:100',
            'conditions.statuses' => 'nullable|array',
            'conditions.statuses.*' => 'nullable|string|max:100',
            'conditions.projects' => 'nullable|array',
            'conditions.projects.*' => 'nullable|string|max:160',
            'conditions.assigned_user_ids' => 'nullable|array',
            'conditions.assigned_user_ids.*' => 'nullable|integer|exists:users,id',
            'conditions.assigned_role_slugs' => 'nullable|array',
            'conditions.assigned_role_slugs.*' => 'nullable|string|max:100',
            'conditions.campaign_types' => 'nullable|array',
            'conditions.campaign_types.*' => ['nullable', Rule::in(['all', 'tag', 'assigned'])],
        ]);

        $validated['conditions'] = collect($validated['conditions'] ?? [])
            ->map(fn ($values) => is_array($values) ? array_values(array_filter($values, fn ($value) => filled($value))) : $values)
            ->filter(fn ($values) => is_array($values) ? count($values) > 0 : filled($values))
            ->all();

        return $validated;
    }

    private function canManageWhatsApp(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasRolePermission') && $user->hasRolePermission('whatsapp.manage');
    }
}
