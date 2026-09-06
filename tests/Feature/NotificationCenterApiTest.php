<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BroadcastMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterApiTest extends TestCase
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

    public function test_index_returns_notifications_and_announcement_counts(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $user = $this->createUser($adminRole, ['name' => 'Vivek']);

        AppNotification::create([
            'user_id' => $user->id,
            'type' => AppNotification::TYPE_NEW_LEAD,
            'title' => 'New Lead Assigned',
            'message' => 'Lead assigned to you',
            'action_type' => AppNotification::ACTION_LEAD,
            'action_url' => 'https://example.test/leads/10',
        ]);

        BroadcastMessage::create([
            'sender_id' => $user->id,
            'title' => 'Office Closed',
            'message' => 'Tomorrow office will open at 11 AM.',
            'priority' => 'important',
            'banner_enabled' => true,
            'requires_acknowledge' => true,
            'target_type' => 'all_users',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('counts.notifications_unread', 1)
            ->assertJsonPath('counts.announcements_unread', 1)
            ->assertJsonPath('counts.total_unread', 2)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.priority', 'important')
            ->assertJsonPath('announcements.0.requires_acknowledge', true);
    }

    public function test_specific_user_announcement_is_not_visible_to_other_users(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $target = $this->createUser($adminRole, ['email' => 'target@example.test']);
        $other = $this->createUser($adminRole, ['email' => 'other@example.test']);

        BroadcastMessage::create([
            'sender_id' => $target->id,
            'title' => 'Private',
            'message' => 'Only one user should receive this.',
            'priority' => 'normal',
            'banner_enabled' => false,
            'requires_acknowledge' => false,
            'target_type' => 'specific_users',
            'target_user_ids' => [$target->id],
            'status' => 'active',
        ]);

        Sanctum::actingAs($target);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'announcements');

        Sanctum::actingAs($other);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'announcements');
    }

    public function test_acknowledge_endpoint_updates_announcement_state(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $user = $this->createUser($adminRole);

        $announcement = BroadcastMessage::create([
            'sender_id' => $user->id,
            'title' => 'Policy Update',
            'message' => 'Please acknowledge.',
            'priority' => 'important',
            'banner_enabled' => true,
            'requires_acknowledge' => true,
            'target_type' => 'all_users',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/notifications/announcements/{$announcement->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('broadcast_message_user_states', [
            'broadcast_message_id' => $announcement->id,
            'user_id' => $user->id,
        ]);

        $payload = $this->getJson('/api/notifications')->json();
        $this->assertNotEmpty($payload['announcements'][0]['acknowledged_at']);
        $this->assertSame(0, $payload['counts']['announcements_unread']);
    }

    protected function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->string('title');
            $table->text('message');
            $table->string('priority')->default('normal');
            $table->boolean('banner_enabled')->default(false);
            $table->boolean('requires_acknowledge')->default(false);
            $table->string('action_label')->nullable();
            $table->text('action_url')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('target_type')->default('all_users');
            $table->json('target_roles')->nullable();
            $table->json('target_user_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('active');
            $table->json('read_by')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcast_message_user_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_message_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->string('delivery_channel')->nullable();
            $table->dateTime('last_popup_shown_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
        ]);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'user' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
