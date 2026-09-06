<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_device_audits')) {
            Schema::create('notification_device_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40);
            $table->string('channel', 20);
            $table->char('token_hash', 64);
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['channel', 'token_hash']);
            $table->index('from_user_id');
            $table->index('to_user_id');
            });
        }

        $this->cleanupDuplicates('fcm_tokens', 'fcm_token', 'fcm');
        $this->cleanupDuplicates('push_subscriptions', 'endpoint', 'web_push');

        if (!Schema::hasColumn('fcm_tokens', 'token_hash')) {
            Schema::table('fcm_tokens', function (Blueprint $table) {
                $table->char('token_hash', 64)->nullable()->after('fcm_token');
            });
        }
        if (!Schema::hasColumn('push_subscriptions', 'endpoint_hash')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->char('endpoint_hash', 64)->nullable()->after('endpoint');
            });
        }

        DB::table('fcm_tokens')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('fcm_tokens')->where('id', $row->id)->update(['token_hash' => hash('sha256', $row->fcm_token)]);
            }
        });
        DB::table('push_subscriptions')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('push_subscriptions')->where('id', $row->id)->update(['endpoint_hash' => hash('sha256', $row->endpoint)]);
            }
        });

        $this->dropIndexIfExists('fcm_tokens', 'fcm_tokens_user_id_fcm_token_unique');
        $this->dropIndexIfExists('push_subscriptions', 'push_subscriptions_user_id_endpoint_unique');
        $this->addUniqueIfMissing('fcm_tokens', 'token_hash', 'fcm_tokens_token_hash_unique');
        $this->addUniqueIfMissing('push_subscriptions', 'endpoint_hash', 'push_subscriptions_endpoint_hash_unique');
    }

    public function down(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropUnique('fcm_tokens_token_hash_unique');
            $table->unique(['user_id', 'fcm_token']);
            $table->dropColumn('token_hash');
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropUnique('push_subscriptions_endpoint_hash_unique');
            $table->unique(['user_id', 'endpoint']);
            $table->dropColumn('endpoint_hash');
        });

        Schema::dropIfExists('notification_device_audits');
    }

    private function cleanupDuplicates(string $table, string $column, string $channel): void
    {
        $duplicates = DB::table($table)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        foreach ($duplicates as $identifier) {
            $rows = DB::table($table)
                ->where($column, $identifier)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $owner = $rows->first();
            foreach ($rows->slice(1) as $stale) {
                DB::table('notification_device_audits')->insert([
                    'action' => 'migration_cleanup',
                    'channel' => $channel,
                    'token_hash' => hash('sha256', $identifier),
                    'from_user_id' => $stale->user_id,
                    'to_user_id' => $owner->user_id,
                    'device_type' => $channel === 'fcm' ? ($stale->device_type ?? null) : 'web',
                    'metadata' => json_encode(['removed_mapping_id' => $stale->id, 'retained_mapping_id' => $owner->id]),
                    'created_at' => now(),
                ]);

                DB::table($table)->where('id', $stale->id)->delete();
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function addUniqueIfMissing(string $table, string $column, string $index): void
    {
        if (!$this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$index}` (`{$column}`)");
        }
    }
};
