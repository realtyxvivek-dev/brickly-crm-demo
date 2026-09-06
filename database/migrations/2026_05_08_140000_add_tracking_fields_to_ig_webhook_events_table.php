<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ig_webhook_events', function (Blueprint $table) {
            $table->string('external_event_id')->nullable()->after('id');
            $table->string('object_type')->nullable()->after('event_type');
            $table->string('field')->nullable()->after('object_type');
            $table->timestamp('received_at')->nullable()->after('field');

            $table->index('external_event_id');
            $table->index(['event_type', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('ig_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['external_event_id']);
            $table->dropIndex(['event_type', 'status']);
            $table->dropIndex(['received_at']);
            $table->dropColumn(['external_event_id', 'object_type', 'field', 'received_at']);
        });
    }
};
