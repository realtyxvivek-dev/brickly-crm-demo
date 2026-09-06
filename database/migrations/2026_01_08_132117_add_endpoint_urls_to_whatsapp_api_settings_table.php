<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_api_settings', function (Blueprint $table) {
            // Base URL
            $table->string('base_url')->nullable()->after('api_endpoint');
            
            // Messaging Endpoints
            $table->string('send_message_endpoint')->nullable()->after('base_url');
            $table->string('send_template_endpoint')->nullable()->after('send_message_endpoint');
            
            // Conversations Endpoints
            $table->string('get_conversations_endpoint')->nullable()->after('send_template_endpoint');
            $table->string('get_messages_endpoint')->nullable()->after('get_conversations_endpoint');
            
            // Templates Endpoints
            $table->string('get_templates_endpoint')->nullable()->after('get_messages_endpoint');
            $table->string('get_template_endpoint')->nullable()->after('get_templates_endpoint');
            $table->string('create_template_endpoint')->nullable()->after('get_template_endpoint');
            $table->string('delete_template_endpoint')->nullable()->after('create_template_endpoint');
            
            // Groups Endpoints
            $table->string('get_groups_endpoint')->nullable()->after('delete_template_endpoint');
            $table->string('make_group_endpoint')->nullable()->after('get_groups_endpoint');
            $table->string('update_group_endpoint')->nullable()->after('make_group_endpoint');
            $table->string('remove_group_endpoint')->nullable()->after('update_group_endpoint');
            
            // Contacts Endpoints
            $table->string('import_contact_endpoint')->nullable()->after('remove_group_endpoint');
            $table->string('update_contact_endpoint')->nullable()->after('import_contact_endpoint');
            $table->string('remove_contact_endpoint')->nullable()->after('update_contact_endpoint');
            $table->string('add_contacts_endpoint')->nullable()->after('remove_contact_endpoint');
            
            // Media Endpoints
            $table->string('get_media_endpoint')->nullable()->after('add_contacts_endpoint');
            
            // Campaigns Endpoints
            $table->string('get_campaigns_endpoint')->nullable()->after('get_media_endpoint');
            $table->string('send_campaign_endpoint')->nullable()->after('get_campaigns_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_api_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
