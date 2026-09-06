<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::table('fb_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_pages', 'facebook_portfolio_id')) {
                $table->foreignId('facebook_portfolio_id')
                    ->nullable()
                    ->after('token_reference')
                    ->constrained('facebook_portfolios')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_pages', function (Blueprint $table) {
            if (Schema::hasColumn('fb_pages', 'facebook_portfolio_id')) {
                $table->dropConstrainedForeignId('facebook_portfolio_id');
            }
        });

        Schema::dropIfExists('facebook_portfolios');
    }
};
