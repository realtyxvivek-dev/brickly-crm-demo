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
        Schema::table('site_visits', function (Blueprint $table) {
            // Add verification fields
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('status');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null')->after('verification_status');
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
            
            // Add form fields (same as meetings)
            $table->string('customer_name')->nullable()->after('lead_id');
            $table->string('phone', 16)->nullable()->after('customer_name');
            $table->string('employee')->nullable()->after('phone');
            $table->string('occupation')->nullable()->after('employee');
            $table->date('date_of_visit')->nullable()->after('occupation');
            $table->string('project')->nullable()->after('date_of_visit');
            $table->enum('budget_range', [
                'Under 50 Lac',
                '50 Lac – 1 Cr',
                '1 Cr – 2 Cr',
                '2 Cr – 3 Cr',
                'Above 3 Cr'
            ])->nullable()->after('project');
            $table->string('team_leader')->nullable()->after('budget_range');
            $table->enum('property_type', [
                'Plot/Villa',
                'Flat',
                'Commercial',
                'Just Exploring'
            ])->nullable()->after('team_leader');
            $table->enum('payment_mode', [
                'Self Fund',
                'Loan'
            ])->nullable()->after('property_type');
            $table->enum('tentative_period', [
                'Within 1 Month',
                'Within 3 Months',
                'Within 6 Months',
                'More than 6 Months'
            ])->nullable()->after('payment_mode');
            $table->enum('lead_type', [
                'New Visit',
                'Revisited',
                'Meeting',
                'Prospect'
            ])->nullable()->after('tentative_period');
            $table->json('photos')->nullable()->after('lead_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verified_by',
                'verified_at',
                'rejection_reason',
                'customer_name',
                'phone',
                'employee',
                'occupation',
                'date_of_visit',
                'project',
                'budget_range',
                'team_leader',
                'property_type',
                'payment_mode',
                'tentative_period',
                'lead_type',
                'photos'
            ]);
        });
    }
};
