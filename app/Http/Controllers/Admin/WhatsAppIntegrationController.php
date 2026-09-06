<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSettings;
use App\Models\WhatsAppApiSettings;
use App\Models\Role;
use App\Models\User;
use App\Services\WhatsAppApiService;
use App\Services\WhatsAppLeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppIntegrationController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppApiService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Display WhatsApp API configuration page
     */
    public function index()
    {
        $settings = WhatsAppApiSettings::getSettings();
        $automationService = app(WhatsAppLeadAutomationService::class);
        $automationSettings = $automationService->settings();
        $eligibleUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', [
                    Role::SALES_EXECUTIVE,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                ]);
            })
            ->orderBy('name')
            ->get();

        return view('integrations.whatsapp', compact('settings', 'automationSettings', 'eligibleUsers'));
    }

    /**
     * Update WhatsApp API settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'api_endpoint' => 'nullable|url',
            'api_token' => 'nullable|string',
            'is_active' => 'boolean',
            'base_url' => 'nullable|url',
            'send_message_endpoint' => 'nullable|string',
            'send_template_endpoint' => 'nullable|string',
            'get_conversations_endpoint' => 'nullable|string',
            'get_messages_endpoint' => 'nullable|string',
            'get_templates_endpoint' => 'nullable|string',
            'get_template_endpoint' => 'nullable|string',
            'create_template_endpoint' => 'nullable|string',
            'delete_template_endpoint' => 'nullable|string',
            'get_groups_endpoint' => 'nullable|string',
            'make_group_endpoint' => 'nullable|string',
            'update_group_endpoint' => 'nullable|string',
            'remove_group_endpoint' => 'nullable|string',
            'import_contact_endpoint' => 'nullable|string',
            'update_contact_endpoint' => 'nullable|string',
            'remove_contact_endpoint' => 'nullable|string',
            'add_contacts_endpoint' => 'nullable|string',
            'get_media_endpoint' => 'nullable|string',
            'get_campaigns_endpoint' => 'nullable|string',
            'send_campaign_endpoint' => 'nullable|string',
        ]);

        try {
            $updateData = [
                'api_token' => $request->input('api_token', ''),
                'is_active' => $request->boolean('is_active'),
                'is_verified' => false, // Reset verification on update
                'verified_at' => null,
            ];

            // Update api_endpoint if provided
            if ($request->filled('api_endpoint')) {
                $updateData['api_endpoint'] = rtrim($request->api_endpoint, '/');
            }

            // Update base_url if provided
            if ($request->filled('base_url')) {
                $updateData['base_url'] = rtrim($request->base_url, '/');
            }

            // Update all endpoint paths
            $endpointFields = [
                'send_message_endpoint',
                'send_template_endpoint',
                'get_conversations_endpoint',
                'get_messages_endpoint',
                'get_templates_endpoint',
                'get_template_endpoint',
                'create_template_endpoint',
                'delete_template_endpoint',
                'get_groups_endpoint',
                'make_group_endpoint',
                'update_group_endpoint',
                'remove_group_endpoint',
                'import_contact_endpoint',
                'update_contact_endpoint',
                'remove_contact_endpoint',
                'add_contacts_endpoint',
                'get_media_endpoint',
                'get_campaigns_endpoint',
                'send_campaign_endpoint',
            ];

            foreach ($endpointFields as $field) {
                if ($request->filled($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            $settings = WhatsAppApiSettings::updateSettings($updateData);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp API settings updated successfully',
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp Settings Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify API connection
     */
    public function verifyConnection(Request $request)
    {
        try {
            $result = $this->whatsappService->verifyConnection();

            if ($result['success']) {
                WhatsAppApiSettings::updateSettings([
                    'is_verified' => true,
                    'verified_at' => now(),
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('WhatsApp Verification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send test message
     */
    public function testMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $result = $this->whatsappService->sendMessage(
                $request->phone,
                $request->message
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('WhatsApp Test Message Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateAutomation(Request $request)
    {
        $validated = $request->validate([
            'auto_assign_enabled' => 'nullable|boolean',
            'auto_assign_mode' => 'required|in:round_robin,first_available,percentage,single_user,random',
            'create_calling_task' => 'nullable|boolean',
            'notify_assigned_user' => 'nullable|boolean',
            'eligible_user_ids' => 'array',
            'eligible_user_ids.*' => 'integer|exists:users,id',
            'single_user_id' => 'nullable|integer|exists:users,id',
            'user_percentages' => 'array',
        ]);

        $eligibleUsers = User::query()
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', [
                    Role::SALES_EXECUTIVE,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                ]);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedUserIds = collect($validated['eligible_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $eligibleUsers, true))
            ->values()
            ->all();

        $singleUserId = isset($validated['single_user_id']) ? (int) $validated['single_user_id'] : null;
        if ($singleUserId && !in_array($singleUserId, $eligibleUsers, true)) {
            $singleUserId = null;
        }

        if ($validated['auto_assign_mode'] === 'single_user' && $singleUserId) {
            $selectedUserIds = [$singleUserId];
        }

        $percentages = collect($request->input('user_percentages', []))
            ->mapWithKeys(function ($percentage, $userId) use ($selectedUserIds) {
                $normalizedUserId = (int) $userId;
                if (!in_array($normalizedUserId, $selectedUserIds, true)) {
                    return [];
                }

                return [$normalizedUserId => max(0, (float) $percentage)];
            })
            ->all();

        SystemSettings::set('whatsapp_auto_assign_enabled', $request->boolean('auto_assign_enabled') ? '1' : '0');
        SystemSettings::set('whatsapp_auto_assign_mode', $validated['auto_assign_mode']);
        SystemSettings::set('whatsapp_auto_create_calling_task', $request->boolean('create_calling_task') ? '1' : '0');
        SystemSettings::set('whatsapp_auto_notify_assigned_user', $request->boolean('notify_assigned_user') ? '1' : '0');
        SystemSettings::set('whatsapp_auto_assign_user_ids', json_encode($selectedUserIds));
        SystemSettings::set('whatsapp_auto_assign_single_user_id', $singleUserId ? (string) $singleUserId : '0');
        SystemSettings::set('whatsapp_auto_assign_user_percentages', json_encode($percentages));

        if (!empty($selectedUserIds)) {
            $lastAssignedUserId = (int) SystemSettings::get('whatsapp_last_assigned_user_id', '0');
            if (!in_array($lastAssignedUserId, $selectedUserIds, true)) {
                SystemSettings::set('whatsapp_last_assigned_user_id', '0');
            }
        } else {
            SystemSettings::set('whatsapp_last_assigned_user_id', '0');
        }

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp auto distribution settings updated successfully.',
            'settings' => [
                'enabled' => $request->boolean('auto_assign_enabled'),
                'mode' => $validated['auto_assign_mode'],
                'create_calling_task' => $request->boolean('create_calling_task'),
                'notify_assigned_user' => $request->boolean('notify_assigned_user'),
                'eligible_user_ids' => $selectedUserIds,
                'single_user_id' => $singleUserId,
                'user_percentages' => $percentages,
            ],
        ]);
    }
}
