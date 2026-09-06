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
            // KYC fields for closing request
            $table->string('nominee_name')->nullable()->after('customer_name');
            $table->string('second_customer_name')->nullable()->after('nominee_name');
            $table->date('customer_dob')->nullable()->after('second_customer_name');
            $table->string('pan_card')->nullable()->after('customer_dob');
            $table->string('aadhaar_card_no')->nullable()->after('pan_card');
            $table->json('kyc_documents')->nullable()->after('aadhaar_card_no');
            
            // Closing verification fields
            $table->enum('closing_verification_status', ['pending', 'verified', 'rejected'])->nullable()->after('closer_status');
            $table->foreignId('closing_verified_by')->nullable()->after('closing_verification_status')->constrained('users')->onDelete('set null');
            $table->timestamp('closing_verified_at')->nullable()->after('closing_verified_by');
            $table->text('closing_rejection_reason')->nullable()->after('closing_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropForeign(['closing_verified_by']);
            $table->dropColumn([
                'nominee_name',
                'second_customer_name',
                'customer_dob',
                'pan_card',
                'aadhaar_card_no',
                'kyc_documents',
                'closing_verification_status',
                'closing_verified_by',
                'closing_verified_at',
                'closing_rejection_reason',
            ]);
        });
    }
};
