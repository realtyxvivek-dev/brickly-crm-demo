<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('pre_transfer_status')->nullable()->after('status');
            $table->unsignedBigInteger('transferred_from_user_id')->nullable()->after('pre_transfer_status');
            $table->unsignedBigInteger('transferred_to_user_id')->nullable()->after('transferred_from_user_id');
            $table->timestamp('transferred_at')->nullable()->after('transferred_to_user_id');
            $table->text('transfer_note')->nullable()->after('transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'pre_transfer_status',
                'transferred_from_user_id',
                'transferred_to_user_id',
                'transferred_at',
                'transfer_note',
            ]);
        });
    }
};
