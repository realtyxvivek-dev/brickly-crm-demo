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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('cascade');
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            
            // Customer Information (from form)
            $table->string('customer_name');
            $table->string('phone', 16);
            $table->string('employee')->nullable();
            $table->string('occupation')->nullable();
            $table->date('date_of_visit');
            $table->string('project')->nullable();
            $table->enum('budget_range', [
                'Under 50 Lac',
                '50 Lac – 1 Cr',
                '1 Cr – 2 Cr',
                '2 Cr – 3 Cr',
                'Above 3 Cr'
            ]);
            $table->string('team_leader')->nullable(); // Select TL dropdown
            $table->enum('property_type', [
                'Plot/Villa',
                'Flat',
                'Commercial',
                'Just Exploring'
            ]);
            $table->enum('payment_mode', [
                'Self Fund',
                'Loan'
            ]);
            $table->enum('tentative_period', [
                'Within 1 Month',
                'Within 3 Months',
                'Within 6 Months',
                'More than 6 Months'
            ]);
            $table->enum('lead_type', [
                'New Visit',
                'Revisited',
                'Meeting',
                'Prospect'
            ]);
            $table->json('photos')->nullable(); // Multiple photo paths
            
            // Scheduling & Status
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            
            // Verification
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Notes & Feedback
            $table->text('meeting_notes')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
