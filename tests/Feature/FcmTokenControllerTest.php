<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\FcmTokenController;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FcmTokenControllerTest extends TestCase
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('fcm_token');
            $table->string('device_type')->default('web');
            $table->timestamps();
        });
    }

    public function test_store_saves_web_token(): void
    {
        $user = $this->createUser();
        $controller = new FcmTokenController();
        $request = Request::create('/api/fcm-subscription', 'POST', [
            'fcm_token' => 'web-token-123',
            'device_type' => 'web',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->store($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'web-token-123',
            'device_type' => 'web',
        ]);
    }

    public function test_store_saves_android_token(): void
    {
        $user = $this->createUser();
        $controller = new FcmTokenController();
        $request = Request::create('/api/fcm-subscription', 'POST', [
            'fcm_token' => 'android-token-123',
            'device_type' => 'android',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->store($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'android-token-123',
            'device_type' => 'android',
        ]);
    }

    private function createUser(): User
    {
        static $counter = 1;

        return User::create([
            'name' => 'User ' . $counter,
            'email' => 'fcm-user' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }
}
