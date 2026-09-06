<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\TelecallerTask;
use App\Models\Task;
use App\Models\CrmAssignment;
use App\Models\LeadAssignment;
use App\Models\ImportedLead;
use App\Models\Prospect;
use App\Models\SiteVisit;
use App\Models\Meeting;
use App\Models\CallLog;
use App\Models\FollowUp;
use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteDummyLeads extends Command
{
    protected $signature = 'leads:delete-dummy {--force : Force deletion without confirmation}';
    
    protected $description = 'Delete all dummy leads (leads with source=csv created by DummyLeadsSeeder)';

    public function handle()
    {
        // Find dummy leads - multiple patterns:
        // 1. Leads with source='csv' (created by DummyLeadsSeeder)
        // 2. Leads with name like 'Dummy%' (old pattern)
        // 3. Specific old dummy leads from Google Sheets (Vijay, Gopi, Shushank, Sonu, tanu, ajay, Vivek with specific phones)
        // 4. Additional dummy leads (Manish Aggarwal, Anjali Mehta, Ananya Aggarwal, Shreya Gupta, Nikhil Goyal)
        $dummyPhoneNumbers = [
            '9595959597', // Vijay
            '9595959596', // Gopi
            '9595955596', // Shushank
            '9635123549', // Sonu
            '9595959595', // tanu
            '9919944155', // ajay
            '9369205635', // Vivek
        ];
        
        $dummyNames = [
            'Manish Aggarwal',
            'Anjali Mehta',
            'Ananya Aggarwal',
            'Shreya Gupta',
            'Nikhil Goyal',
        ];
        
        // Normalize phone numbers (remove +, spaces, etc.)
        $normalizedDummyPhones = array_map(function($phone) {
            return preg_replace('/[^0-9]/', '', $phone);
        }, $dummyPhoneNumbers);
        
        // Include soft-deleted leads as well
        $dummyLeads = Lead::withTrashed()->where(function($query) use ($normalizedDummyPhones, $dummyNames) {
            $query->where('source', 'csv')
                  ->orWhere('name', 'like', 'Dummy%')
                  ->orWhereIn('name', $dummyNames) // Additional dummy leads by name
                  ->orWhere(function($q) use ($normalizedDummyPhones) {
                      // Check for specific dummy leads from Google Sheets
                      $q->where('source', 'google_sheets')
                        ->whereIn(DB::raw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '')"), $normalizedDummyPhones);
                  });
        })->get();
        
        $count = $dummyLeads->count();
        
        if ($count === 0) {
            $this->info('No dummy leads found.');
            $this->info('Checked for:');
            $this->line('  - Leads with source="csv"');
            $this->line('  - Leads with name like "Dummy%"');
            $this->line('  - Specific old dummy leads from Google Sheets');
            $this->line('  - Additional dummy leads (Manish Aggarwal, Anjali Mehta, Ananya Aggarwal, Shreya Gupta, Nikhil Goyal)');
            return 0;
        }
        
        $this->info("Found {$count} dummy leads.");
        $this->warn('This will PERMANENTLY delete:');
        $this->line("  - {$count} leads (permanent deletion, cannot be recovered)");
        $this->line('  - Related telecaller tasks');
        $this->line('  - Related tasks (Task model)');
        $this->line('  - Related CRM assignments');
        $this->line('  - Related lead assignments');
        $this->line('  - Related imported leads');
        $this->line('  - Related prospects');
        $this->line('  - Related site visits');
        $this->line('  - Related meetings');
        $this->line('  - Related call logs');
        $this->line('  - Related follow-ups');
        
        // Show list of leads to be deleted
        $this->line('');
        $this->info('Leads to be deleted:');
        foreach ($dummyLeads as $lead) {
            $this->line("  - {$lead->name} ({$lead->phone}) - Source: {$lead->source}");
        }
        $this->line('');
        
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to delete all dummy leads?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $this->info('Starting deletion process...');
        
        $deletedCount = 0;
        $leadIds = $dummyLeads->pluck('id')->toArray();
        
        DB::beginTransaction();
        
        try {
            // Delete related data first
            $this->info('Deleting related records...');
            
            // Delete imported leads
            $importedDeleted = ImportedLead::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$importedDeleted} imported lead records");
            
            // Delete prospects
            $prospectsDeleted = Prospect::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$prospectsDeleted} prospects");
            
            // Delete site visits
            $visitsDeleted = SiteVisit::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$visitsDeleted} site visits");
            
            // Delete meetings
            $meetingsDeleted = Meeting::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$meetingsDeleted} meetings");
            
            // Delete call logs
            $callLogsDeleted = CallLog::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$callLogsDeleted} call logs");
            
            // Delete follow-ups
            $followUpsDeleted = FollowUp::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$followUpsDeleted} follow-ups");
            
            // Delete telecaller tasks
            $telecallerTasksDeleted = TelecallerTask::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$telecallerTasksDeleted} telecaller tasks");
            
            // Delete Task model tasks (for sales managers/executives)
            $tasksDeleted = Task::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$tasksDeleted} tasks (Task model)");
            
            // Delete CRM assignments
            $crmDeleted = CrmAssignment::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$crmDeleted} CRM assignments");
            
            // Delete lead assignments
            $assignmentsDeleted = LeadAssignment::whereIn('lead_id', $leadIds)->delete();
            $this->line("  ✓ Deleted {$assignmentsDeleted} lead assignments");
            
            
            // Delete activity logs related to these leads
            $activityLogsDeleted = ActivityLog::where('model_type', 'Lead')
                ->whereIn('model_id', $leadIds)
                ->delete();
            $this->line("  ✓ Deleted {$activityLogsDeleted} activity log entries");
            
            // Delete the leads themselves - use forceDelete to permanently remove (bypass soft deletes)
            $this->info('Deleting dummy leads permanently...');
            $deletedCount = 0;
            foreach ($dummyLeads as $lead) {
                $lead->forceDelete(); // Permanently delete (bypass soft deletes)
                $deletedCount++;
            }
            $this->line("  ✓ Permanently deleted {$deletedCount} dummy leads");
            
            DB::commit();
            
            $this->info("");
            $this->info("✅ Successfully deleted {$deletedCount} dummy leads and all related data!");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error occurred: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}