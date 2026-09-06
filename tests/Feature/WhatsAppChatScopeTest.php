<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppChatScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_asm_only_sees_team_scoped_conversations(): void
    {
        [$admin, $seniorManager, $asm, $teamExecutive, $outsideExecutive] = $this->createHierarchy();
        [$teamConversation, $outsideConversation] = $this->createConversations($teamExecutive, $outsideExecutive);

        $response = $this->actingAs($asm)->getJson('/chat/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $teamConversation->id);
        $this->assertSame([$teamConversation->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_senior_manager_sees_nested_team_conversations(): void
    {
        [$admin, $seniorManager, $asm, $teamExecutive, $outsideExecutive] = $this->createHierarchy();
        [$teamConversation, $outsideConversation] = $this->createConversations($teamExecutive, $outsideExecutive);

        $response = $this->actingAs($seniorManager)->getJson('/chat/conversations');

        $response->assertOk();
        $this->assertSame([$teamConversation->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_admin_still_sees_all_conversations(): void
    {
        [$admin, $seniorManager, $asm, $teamExecutive, $outsideExecutive] = $this->createHierarchy();
        [$teamConversation, $outsideConversation] = $this->createConversations($teamExecutive, $outsideExecutive);

        $response = $this->actingAs($admin)->getJson('/chat/conversations');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$teamConversation->id, $outsideConversation->id],
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    public function test_asm_cannot_send_message_on_out_of_scope_conversation(): void
    {
        [$admin, $seniorManager, $asm, $teamExecutive, $outsideExecutive] = $this->createHierarchy();
        [, $outsideConversation] = $this->createConversations($teamExecutive, $outsideExecutive);

        $response = $this->actingAs($asm)->postJson('/chat/messages', [
            'conversation_id' => $outsideConversation->id,
            'message' => 'Hello',
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('message', 'Conversation not found');
    }

    public function test_sales_manager_layout_includes_chat_link_for_asm_dashboard(): void
    {
        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);
        $asm = $this->createUser($asmRole, ['name' => 'Asm User']);

        $response = $this->actingAs($asm)->get(route('sales-manager.dashboard'));

        $response->assertOk();
        $response->assertSee(route('chat.index'), false);
        $response->assertSeeText('WhatsApp Chat');
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->text('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_manager_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->json('preferences')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->string('assignment_method')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('phone_number');
            $table->string('contact_name')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('direction')->nullable();
            $table->text('message')->nullable();
            $table->string('message_id')->nullable();
            $table->string('template_id')->nullable();
            $table->string('status')->nullable();
            $table->text('error_message')->nullable();
            $table->text('api_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_id')->nullable();
            $table->string('name')->nullable();
            $table->text('content')->nullable();
            $table->string('category')->nullable();
            $table->string('language')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_endpoint')->nullable();
            $table->text('api_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('base_url')->nullable();
            $table->string('send_message_endpoint')->nullable();
            $table->string('send_template_endpoint')->nullable();
            $table->string('get_conversations_endpoint')->nullable();
            $table->string('get_messages_endpoint')->nullable();
            $table->string('get_templates_endpoint')->nullable();
            $table->string('get_template_endpoint')->nullable();
            $table->string('create_template_endpoint')->nullable();
            $table->string('delete_template_endpoint')->nullable();
            $table->string('get_groups_endpoint')->nullable();
            $table->string('make_group_endpoint')->nullable();
            $table->string('update_group_endpoint')->nullable();
            $table->string('remove_group_endpoint')->nullable();
            $table->string('import_contact_endpoint')->nullable();
            $table->string('update_contact_endpoint')->nullable();
            $table->string('remove_contact_endpoint')->nullable();
            $table->string('add_contacts_endpoint')->nullable();
            $table->string('get_media_endpoint')->nullable();
            $table->string('get_campaigns_endpoint')->nullable();
            $table->string('send_campaign_endpoint')->nullable();
            $table->timestamps();
        });
    }

    private function createHierarchy(): array
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $seniorManagerRole = $this->createRole(Role::SENIOR_MANAGER);
        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $admin = $this->createUser($adminRole, ['name' => 'Admin']);
        $seniorManager = $this->createUser($seniorManagerRole, ['name' => 'Senior Manager']);
        $asm = $this->createUser($asmRole, [
            'name' => 'ASM User',
            'manager_id' => $seniorManager->id,
        ]);
        $teamExecutive = $this->createUser($salesExecutiveRole, [
            'name' => 'Team Executive',
            'manager_id' => $asm->id,
        ]);
        $outsideExecutive = $this->createUser($salesExecutiveRole, [
            'name' => 'Outside Executive',
        ]);

        return [$admin, $seniorManager, $asm, $teamExecutive, $outsideExecutive];
    }

    private function createConversations(User $teamExecutive, User $outsideExecutive): array
    {
        $teamLead = Lead::create([
            'name' => 'Team Lead',
            'phone' => '9999911111',
            'source' => 'meta',
            'status' => 'new',
        ]);

        $outsideLead = Lead::create([
            'name' => 'Outside Lead',
            'phone' => '9999922222',
            'source' => 'meta',
            'status' => 'new',
        ]);

        DB::table('lead_assignments')->insert([
            [
                'lead_id' => $teamLead->id,
                'assigned_to' => $teamExecutive->id,
                'assigned_by' => $teamExecutive->manager_id,
                'is_active' => true,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $outsideLead->id,
                'assigned_to' => $outsideExecutive->id,
                'assigned_by' => null,
                'is_active' => true,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $teamConversation = WhatsAppConversation::create([
            'user_id' => $teamExecutive->id,
            'phone_number' => '919999911111',
            'contact_name' => 'Team Lead',
            'lead_id' => $teamLead->id,
        ]);

        $outsideConversation = WhatsAppConversation::create([
            'user_id' => $outsideExecutive->id,
            'phone_number' => '919999922222',
            'contact_name' => 'Outside Lead',
            'lead_id' => $outsideLead->id,
        ]);

        return [$teamConversation, $outsideConversation];
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'whatsapp-scope-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
