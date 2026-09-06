<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->tinyInteger('meeting_sequence')->default(1)->after('status')->comment('1=Fresh, 2=2nd, 3=3rd meeting');
            $table->enum('meeting_mode', ['online', 'offline'])->default('offline')->after('meeting_sequence');
            $table->string('meeting_link', 500)->nullable()->after('meeting_mode');
            $table->string('location')->nullable()->after('meeting_link');
            $table->boolean('reminder_enabled')->default(false)->after('location');
            $table->integer('reminder_minutes')->default(5)->after('reminder_enabled');
            $table->unsignedBigInteger('pre_meeting_call_task_id')->nullable()->after('reminder_minutes');
            $table->enum('customer_confirmation_status', ['pending', 'confirmed', 'cancelled'])->default('pending')->after('pre_meeting_call_task_id');
            $table->unsignedBigInteger('original_meeting_id')->nullable()->after('customer_confirmation_status')->comment('Reference to old meeting if rescheduled');
            
            $table->foreign('pre_meeting_call_task_id')->references('id')->on('telecaller_tasks')->onDelete('set null');
            $table->foreign('original_meeting_id')->references('id')->on('meetings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['pre_meeting_call_task_id']);
            $table->dropForeign(['original_meeting_id']);
            $table->dropColumn([
                'meeting_sequence',
                'meeting_mode',
                'meeting_link',
                'location',
                'reminder_enabled',
                'reminder_minutes',
                'pre_meeting_call_task_id',
                'customer_confirmation_status',
                'original_meeting_id'
            ]);
        });
    }
};
