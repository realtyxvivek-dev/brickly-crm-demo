<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('preferred_location')->nullable()->after('pincode');
            $table->string('preferred_size')->nullable()->after('preferred_location');
            $table->text('use_end_use')->nullable()->after('preferred_size');
            $table->decimal('investment', 15, 2)->nullable()->after('use_end_use');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['preferred_location', 'preferred_size', 'use_end_use', 'investment']);
        });
    }
};

