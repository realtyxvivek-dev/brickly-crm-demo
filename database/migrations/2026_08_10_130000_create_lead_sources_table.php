<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` VARCHAR(100) DEFAULT 'other'");
        }

        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('type', 50)->default('other');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('type');
        });

        DB::table('lead_sources')->insert([
            ['name' => 'Meta', 'key' => 'meta', 'type' => 'social', 'is_active' => true, 'is_system' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Meta Awareness', 'key' => 'meta_awareness', 'type' => 'social', 'is_active' => true, 'is_system' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'IVR', 'key' => 'ivr', 'type' => 'call', 'is_active' => true, 'is_system' => true, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sheet', 'key' => 'sheet', 'type' => 'import', 'is_active' => true, 'is_system' => true, 'sort_order' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'WhatsApp', 'key' => 'whatsapp', 'type' => 'social', 'is_active' => true, 'is_system' => true, 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Website', 'key' => 'website', 'type' => 'digital', 'is_active' => true, 'is_system' => true, 'sort_order' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Organic', 'key' => 'organic', 'type' => 'digital', 'is_active' => true, 'is_system' => true, 'sort_order' => 70, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Google', 'key' => 'google', 'type' => 'digital', 'is_active' => true, 'is_system' => true, 'sort_order' => 80, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '99acres', 'key' => '99acres', 'type' => 'portal', 'is_active' => true, 'is_system' => true, 'sort_order' => 90, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Housing', 'key' => 'housing', 'type' => 'portal', 'is_active' => true, 'is_system' => true, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Reference', 'key' => 'reference', 'type' => 'referral', 'is_active' => true, 'is_system' => true, 'sort_order' => 110, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'key' => 'other', 'type' => 'other', 'is_active' => true, 'is_system' => true, 'sort_order' => 120, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');

        if (Schema::hasTable('leads') && DB::getDriverName() === 'mysql') {
            $allowed = ['meta', 'meta_awareness', 'ivr', 'sheet', 'whatsapp', 'website', 'organic', 'google', '99acres', 'housing', 'reference', 'other'];
            DB::table('leads')->whereNotIn('source', $allowed)->update(['source' => 'other']);
            DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('meta','meta_awareness','ivr','sheet','whatsapp','website','organic','google','99acres','housing','reference','other') DEFAULT 'other'");
        }
    }
};
