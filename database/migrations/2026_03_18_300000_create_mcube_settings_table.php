<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcube_settings', function (Blueprint $table) {
            $table->id();
            $table->string('token')->nullable();           // Webhook validation token
            $table->boolean('is_enabled')->default(false); // Toggle on/off
            $table->timestamps();
        });

        // Insert default row
        DB::table('mcube_settings')->insert([
            'token'      => null,
            'is_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mcube_settings');
    }
};
