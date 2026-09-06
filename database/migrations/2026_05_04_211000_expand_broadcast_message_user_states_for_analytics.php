<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_message_user_states', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('user_id');
            $table->timestamp('acknowledged_at')->nullable()->after('read_at');
            $table->timestamp('clicked_at')->nullable()->after('acknowledged_at');
            $table->string('delivery_channel')->nullable()->after('dismissed_at');
            $table->timestamp('last_popup_shown_at')->nullable()->after('delivery_channel');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_message_user_states', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_at',
                'acknowledged_at',
                'clicked_at',
                'delivery_channel',
                'last_popup_shown_at',
            ]);
        });
    }
};
