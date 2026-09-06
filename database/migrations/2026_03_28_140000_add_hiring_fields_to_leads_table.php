<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_hiring_candidate')->default(false)->after('form_filled_by_manager');
            $table->enum('hiring_status', [
                'new',
                'connected',
                'interview_pending',
                'interview_complete',
                'selected',
                'rejected',
            ])->nullable()->after('is_hiring_candidate');
            $table->text('hr_remark')->nullable()->after('hiring_status');

            $table->index('is_hiring_candidate');
            $table->index('hiring_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['is_hiring_candidate']);
            $table->dropIndex(['hiring_status']);
            $table->dropColumn(['is_hiring_candidate', 'hiring_status', 'hr_remark']);
        });
    }
};
