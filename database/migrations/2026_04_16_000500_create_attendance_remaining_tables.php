<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->boolean('overtime_enabled')->default(false)->after('late_penalty_threshold');
            $table->unsignedInteger('overtime_after_minutes')->default(480)->after('overtime_enabled');
            $table->unsignedInteger('overtime_min_minutes')->default(30)->after('overtime_after_minutes');
        });

        Schema::create('attendance_overtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status')->default('pending');
            $table->string('source')->default('auto');
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_overtimes');

        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn(['overtime_enabled', 'overtime_after_minutes', 'overtime_min_minutes']);
        });
    }
};
