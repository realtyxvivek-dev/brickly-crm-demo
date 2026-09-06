<?php

namespace Tests\Unit;

use App\Http\Controllers\LeadController;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class LeadIndexOptimizationTest extends TestCase
{
    public function test_stats_cache_key_is_user_and_filter_aware(): void
    {
        $method = (new ReflectionClass(LeadController::class))->getMethod('leadStatsCacheKey');
        $controller = app(LeadController::class);
        $firstUser = (new User())->forceFill(['id' => 10, 'role_id' => 2]);
        $secondUser = (new User())->forceFill(['id' => 11, 'role_id' => 2]);

        $first = $method->invoke($controller, Request::create('/leads', 'GET', [
            'source' => 'organic',
            'status' => ['new', 'connected'],
        ]), $firstUser);
        $sameFilters = $method->invoke($controller, Request::create('/leads', 'GET', [
            'status' => ['new', 'connected'],
            'source' => 'organic',
            'page' => 3,
            'per_page' => 500,
        ]), $firstUser);
        $otherFilter = $method->invoke($controller, Request::create('/leads', 'GET', [
            'source' => 'event',
            'status' => ['new', 'connected'],
        ]), $firstUser);
        $otherUser = $method->invoke($controller, Request::create('/leads', 'GET', [
            'source' => 'organic',
            'status' => ['new', 'connected'],
        ]), $secondUser);

        $this->assertSame($first, $sameFilters);
        $this->assertNotSame($first, $otherFilter);
        $this->assertNotSame($first, $otherUser);
    }

    public function test_company_settings_are_loaded_once_per_request(): void
    {
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
        });
        DB::table('company_settings')->insert([
            ['setting_key' => 'primary_color', 'setting_value' => '#123456', 'setting_type' => 'string'],
            ['setting_key' => 'company_name', 'setting_value' => 'Brickly', 'setting_type' => 'string'],
        ]);

        $property = (new ReflectionClass(CompanySetting::class))->getProperty('requestValues');
        $property->setValue(null, null);
        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'company_settings')) {
                $queries++;
            }
        });

        $this->assertSame('#123456', CompanySetting::get('primary_color'));
        $this->assertSame('#123456', CompanySetting::get('primary_color'));
        $this->assertSame('Brickly', CompanySetting::get('company_name'));
        $this->assertSame(1, $queries);
    }
}
