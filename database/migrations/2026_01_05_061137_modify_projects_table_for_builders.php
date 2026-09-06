<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Remove old fields
            if (Schema::hasColumn('projects', 'description')) {
                $table->dropColumn('description');
            }
            
            // Add new fields - make builder_id nullable first for existing data
            $table->foreignId('builder_id')->nullable()->after('id')->constrained('builders')->onDelete('restrict');
            $table->enum('project_type', ['residential', 'commercial', 'mixed'])->nullable()->after('name');
            $table->enum('project_status', ['prelaunch', 'under_construction', 'ready'])->nullable()->after('project_type');
            $table->enum('availability_type', ['fresh', 'resale', 'both'])->default('fresh')->after('project_status');
            $table->string('city')->nullable()->after('availability_type');
            $table->string('area')->nullable()->after('city');
            $table->decimal('land_area', 10, 2)->nullable()->after('area');
            $table->enum('land_area_unit', ['acres', 'sq_ft'])->default('sq_ft')->after('land_area');
            $table->string('rera_no')->nullable()->after('land_area_unit');
            $table->date('possession_date')->nullable()->after('rera_no');
            $table->text('project_highlights')->nullable()->after('possession_date');
            $table->json('configuration_summary')->nullable()->after('project_highlights');

            // Add indexes
            $table->index('builder_id');
            $table->index('project_type');
            $table->index('project_status');
            $table->index('city');
        });
        
        // After migration, make fields required (for new projects only)
        // Existing projects will need to be updated manually
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Remove new fields
            $table->dropForeign(['builder_id']);
            $table->dropColumn([
                'builder_id',
                'project_type',
                'project_status',
                'availability_type',
                'city',
                'area',
                'land_area',
                'land_area_unit',
                'rera_no',
                'possession_date',
                'project_highlights',
                'configuration_summary'
            ]);

            // Restore old field
            $table->text('description')->nullable();
        });
    }
};
