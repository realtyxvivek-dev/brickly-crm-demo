<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmDashboardSourceDistributionTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-03-30 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_source_distribution_rolls_up_legacy_sources_and_sorts_descending(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));

        $this->createLead('facebook_lead_ads');
        $this->createLead('pabbly');
        $this->createLead('social_media');
        $this->createLead('google_sheets');
        $this->createLead('csv');
        $this->createLead('mcube');
        $this->createLead('call');
        $this->createLead('website');

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/source-distribution?date_range=all_time');

        $response->assertOk();
        $this->assertSame(3, $response->json('0.value'));
        $this->assertSame([
            ['source' => 'Meta', 'value' => 3],
            ['source' => 'Ivr', 'value' => 2],
            ['source' => 'Sheet', 'value' => 2],
            ['source' => 'Website', 'value' => 1],
        ], $this->normalizeDistribution($response->json()));
    }

    public function test_source_distribution_respects_today_filter(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));

        $this->createLead('meta', Carbon::parse('2026-03-30 09:00:00'));
        $this->createLead('mcube', Carbon::parse('2026-03-30 10:00:00'));
        $this->createLead('csv', Carbon::parse('2026-03-29 10:00:00'));

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/source-distribution?date_range=today');

        $response->assertOk();
        $this->assertSame([
            ['source' => 'Ivr', 'value' => 1],
            ['source' => 'Meta', 'value' => 1],
        ], $this->normalizeDistribution($response->json()));
    }

    public function test_source_distribution_respects_custom_date_range(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));

        $this->createLead('meta', Carbon::parse('2026-03-27 12:00:00'));
        $this->createLead('google_sheets', Carbon::parse('2026-03-28 12:00:00'));
        $this->createLead('call', Carbon::parse('2026-03-29 12:00:00'));
        $this->createLead('website', Carbon::parse('2026-03-30 12:00:00'));

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/source-distribution?date_range=custom&start_date=2026-03-28&end_date=2026-03-29');

        $response->assertOk();
        $this->assertSame([
            ['source' => 'Ivr', 'value' => 1],
            ['source' => 'Sheet', 'value' => 1],
        ], $this->normalizeDistribution($response->json()));
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
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
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role): User
    {
        static $counter = 1;

        return User::create([
            'name' => 'CRM User ' . $counter,
            'email' => 'crm-source-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(string $source, ?Carbon $createdAt = null): Lead
    {
        $timestamp = $createdAt ?? Carbon::now();

        $lead = Lead::create([
            'name' => 'Lead ' . $source . ' ' . $timestamp->format('Hisu'),
            'phone' => '9999999999',
            'source' => $source,
            'status' => 'new',
        ]);

        $lead->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();

        return $lead->fresh();
    }

    private function normalizeDistribution(array $distribution): array
    {
        usort($distribution, function (array $a, array $b) {
            $valueCompare = $b['value'] <=> $a['value'];
            if ($valueCompare !== 0) {
                return $valueCompare;
            }

            return strcmp($a['source'], $b['source']);
        });

        return array_values($distribution);
    }
}
