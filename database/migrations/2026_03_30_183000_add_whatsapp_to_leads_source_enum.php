<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('meta','ivr','sheet','whatsapp','website','google','99acres','housing','reference','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::table('leads')->where('source', 'whatsapp')->update(['source' => 'other']);

        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('meta','ivr','sheet','website','google','99acres','housing','reference','other') DEFAULT 'other'");
    }
};
