<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 80)->index();
            $table->string('report_name', 160);
            $table->json('filters_json')->nullable();
            $table->string('format', 20);
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamp('generated_at')->nullable()->index();
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
