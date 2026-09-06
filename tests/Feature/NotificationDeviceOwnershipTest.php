<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationDeviceOwnershipService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationDeviceOwnershipTest extends TestCase
{
    private NotificationDeviceOwnershipService $devices;

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

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('fcm_token', 512)->unique();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('device_type')->default('web');
            $table->timestamps();
        });
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint', 500)->unique();
            $table->char('endpoint_hash', 64)->nullable()->unique();
            $table->json('keys')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('notification_device_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('channel');
            $table->char('token_hash', 64);
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('device_type')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->devices = app(NotificationDeviceOwnershipService::class);
    }

    public function test_same_fcm_token_is_transferred_to_current_user(): void
    {
        $token = 'shared-device-token';
        $this->devices->claimFcm($this->user(10), $token);
        $this->devices->claimFcm($this->user(20), $token);

        $this->assertSame(1, DB::table('fcm_tokens')->count());
        $this->assertSame(20, (int) DB::table('fcm_tokens')->value('user_id'));
        $this->assertDatabaseHas('notification_device_audits', [
            'action' => 'transferred',
            'channel' => 'fcm',
            'token_hash' => hash('sha256', $token),
            'from_user_id' => 10,
            'to_user_id' => 20,
        ]);
        $this->assertFalse(Schema::hasColumn('notification_device_audits', 'fcm_token'));
    }

    public function test_logout_removes_only_current_installation(): void
    {
        $user = $this->user(10);
        $this->devices->claimFcm($user, 'phone-one');
        $this->devices->claimFcm($user, 'phone-two');

        $this->assertTrue($this->devices->releaseFcm($user, 'phone-one'));
        $this->assertDatabaseMissing('fcm_tokens', ['fcm_token' => 'phone-one']);
        $this->assertDatabaseHas('fcm_tokens', ['fcm_token' => 'phone-two', 'user_id' => 10]);
    }

    public function test_push_endpoint_is_transferred_and_old_user_cannot_release_it(): void
    {
        $endpoint = 'https://push.example.test/device/1';
        $this->devices->claimPush($this->user(10), $endpoint, ['auth' => 'a']);
        $this->devices->claimPush($this->user(20), $endpoint, ['auth' => 'b']);

        $this->assertSame(20, (int) DB::table('push_subscriptions')->value('user_id'));
        $this->assertFalse($this->devices->releasePush($this->user(10), $endpoint));
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint, 'user_id' => 20]);
    }

    public function test_service_workers_require_recipient_user_guard(): void
    {
        foreach (['public/fcm-sw.js', 'public/firebase-messaging-sw.js', 'public/sw.js'] as $file) {
            $contents = file_get_contents(base_path($file));
            $this->assertStringContainsString('recipient_user_id', $contents, $file);
            $this->assertStringContainsString('isPayloadForActiveUser', $contents, $file);
            $this->assertStringContainsString('CLEAR_ACTIVE_USER', $contents, $file);
        }
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->exists = true;

        return $user;
    }
}
