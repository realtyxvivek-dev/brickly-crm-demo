<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_custom_mapping_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_key', 50)->unique()->comment('Custom CRM field key for FB Lead Ads mapping');
            $table->string('label', 100)->nullable()->comment('Display label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_custom_mapping_fields');
    }
};
