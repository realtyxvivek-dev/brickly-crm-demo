<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WhatsAppApiSettings extends Model
{
    protected $table = 'whatsapp_api_settings';
    
    protected $fillable = [
        'api_endpoint',
        'api_token',
        'is_active',
        'is_verified',
        'verified_at',
        'base_url',
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

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get or create settings
     */
    public static function getSettings()
    {
        $settings = self::first();
        
        if (!$settings) {
            $data = [
                'api_endpoint' => 'https://engage-api-eta.vercel.app/',
                'api_token' => '',
                'is_active' => false,
                'is_verified' => false,
                'base_url' => 'https://rengage.mcube.com',
                'send_message_endpoint' => '/api/wpbox/sendmessage',
                'send_template_endpoint' => '/api/wpbox/sendtemplatemessage',
                'get_conversations_endpoint' => '/api/wpbox/getConversations',
                'get_messages_endpoint' => '/api/wpbox/getMessages/{contact}',
                'get_templates_endpoint' => '/api/wpbox/getTemplates',
                'get_template_endpoint' => '/api/wpbox/get-template/{templateID}',
                'create_template_endpoint' => '/api/wpbox/createTemplate',
                'delete_template_endpoint' => '/api/wpbox/deleteTemplate',
                'get_groups_endpoint' => '/api/wpbox/getGroups',
                'make_group_endpoint' => '/api/wpbox/makeGroups',
                'update_group_endpoint' => '/api/wpbox/updateGroups/{id}',
                'remove_group_endpoint' => '/api/wpbox/removeGroups/{id}',
                'import_contact_endpoint' => '/api/wpbox/importContact',
                'update_contact_endpoint' => '/api/wpbox/updateContact/{id}',
                'remove_contact_endpoint' => '/api/wpbox/removeContact/{id}',
                'add_contacts_endpoint' => '/api/wpbox/addContacts',
                'get_media_endpoint' => '/api/wpbox/getMedia',
                'get_campaigns_endpoint' => '/api/wpbox/getCampaigns',
                'send_campaign_endpoint' => '/api/wpbox/sendwpcampaigns',
            ];

            $settings = self::create(array_filter(
                $data,
                fn ($value, $key) => Schema::hasColumn('whatsapp_api_settings', $key),
                ARRAY_FILTER_USE_BOTH
            ));
        } else {
            // Update existing settings with default endpoints if they are null
            $defaults = [
                'base_url' => 'https://rengage.mcube.com',
                'send_message_endpoint' => '/api/wpbox/sendmessage',
                'send_template_endpoint' => '/api/wpbox/sendtemplatemessage',
                'get_conversations_endpoint' => '/api/wpbox/getConversations',
                'get_messages_endpoint' => '/api/wpbox/getMessages/{contact}',
                'get_templates_endpoint' => '/api/wpbox/getTemplates',
                'get_template_endpoint' => '/api/wpbox/get-template/{templateID}',
                'create_template_endpoint' => '/api/wpbox/createTemplate',
                'delete_template_endpoint' => '/api/wpbox/deleteTemplate',
                'get_groups_endpoint' => '/api/wpbox/getGroups',
                'make_group_endpoint' => '/api/wpbox/makeGroups',
                'update_group_endpoint' => '/api/wpbox/updateGroups/{id}',
                'remove_group_endpoint' => '/api/wpbox/removeGroups/{id}',
                'import_contact_endpoint' => '/api/wpbox/importContact',
                'update_contact_endpoint' => '/api/wpbox/updateContact/{id}',
                'remove_contact_endpoint' => '/api/wpbox/removeContact/{id}',
                'add_contacts_endpoint' => '/api/wpbox/addContacts',
                'get_media_endpoint' => '/api/wpbox/getMedia',
                'get_campaigns_endpoint' => '/api/wpbox/getCampaigns',
                'send_campaign_endpoint' => '/api/wpbox/sendwpcampaigns',
            ];
            
            $needsUpdate = false;
            foreach ($defaults as $key => $value) {
                if (!Schema::hasColumn('whatsapp_api_settings', $key)) {
                    continue;
                }

                if (is_null($settings->$key)) {
                    $settings->$key = $value;
                    $needsUpdate = true;
                }
            }
            
            if ($needsUpdate) {
                $settings->save();
            }
        }
        
        return $settings;
    }

    /**
     * Update settings
     */
    public static function updateSettings(array $data)
    {
        $settings = self::getSettings();
        $settings->update($data);
        return $settings;
    }
}
