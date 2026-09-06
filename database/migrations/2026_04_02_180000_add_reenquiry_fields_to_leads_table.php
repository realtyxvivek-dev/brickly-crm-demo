<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_reenquiry')->default(false)->after('status_auto_update_enabled');
            $table->unsignedInteger('reenquiry_count')->default(0)->after('is_reenquiry');
            $table->timestamp('last_reenquiry_at')->nullable()->after('reenquiry_count');
            $table->string('last_reenquiry_source')->nullable()->after('last_reenquiry_at');
            $table->foreignId('last_reenquiry_fb_form_id')->nullable()->after('last_reenquiry_source')->constrained('fb_forms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_reenquiry_fb_form_id');
            $table->dropColumn([
                'is_reenquiry',
                'reenquiry_count',
                'last_reenquiry_at',
                'last_reenquiry_source',
            ]);
        });
    }
};
