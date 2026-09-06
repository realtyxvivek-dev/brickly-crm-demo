<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteAllLeadsData extends Command
{
    protected $signature = 'leads:delete-all {--force : Force deletion without confirmation}';
    
    protected $description = 'Delete all leads, prospects, site visits, meetings and related data';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete ALL leads, prospects, site visits, meetings and related data. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Starting deletion process...');
        
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        try {
            // Delete related data first
            $this->deleteRelatedData();
            
            // Delete main tables
            $this->deleteMainTables();
            
            $this->info('All data deleted successfully!');
        } catch (\Exception $e) {
            $this->error('Error occurred: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        return 0;
    }

    private function deleteRelatedData()
    {
        $this->info('Deleting related data tables...');
        
        $tables = [
            'task_activities',
            'task_attachments',
            'tasks',
            'telecaller_tasks',
            'call_logs',
            'lead_assignments',
            'crm_assignments',
            'follow_ups',
            'imported_leads',
            'lead_form_field_values',
            'prospect_project',
            'whatsapp_conversations',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    DB::table($table)->truncate();
                    $this->info("  ✓ Deleted {$count} records from {$table}");
                } catch (\Exception $e) {
                    $this->warn("  ✗ Error deleting {$table}: " . $e->getMessage());
                }
            } else {
                $this->line("  - Table {$table} does not exist, skipping");
            }
        }
    }

    private function deleteMainTables()
    {
        $this->info('Deleting main tables...');
        
        // Order matters: delete in reverse dependency order
        // meetings -> site_visits -> prospects -> leads
        $tables = [
            'meetings',
            'site_visits',
            'prospects',
            'leads',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    DB::table($table)->truncate();
                    $this->info("  ✓ Deleted {$count} records from {$table}");
                } catch (\Exception $e) {
                    $this->warn("  ✗ Error deleting {$table}: " . $e->getMessage());
                }
            } else {
                $this->line("  - Table {$table} does not exist, skipping");
            }
        }
    }
}
