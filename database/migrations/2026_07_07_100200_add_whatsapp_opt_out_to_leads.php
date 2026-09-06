<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'whatsapp_opted_out_at')) {
                $table->timestamp('whatsapp_opted_out_at')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('leads', 'whatsapp_opt_out_reason')) {
                $table->string('whatsapp_opt_out_reason')->nullable()->after('whatsapp_opted_out_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (['whatsapp_opt_out_reason', 'whatsapp_opted_out_at'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
