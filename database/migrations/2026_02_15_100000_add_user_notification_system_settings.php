<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'send_welcome_email_to_new_user',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'notify_admin_on_new_user',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'send_welcome_email_to_new_user',
            'notify_admin_on_new_user',
        ])->delete();
    }
};
