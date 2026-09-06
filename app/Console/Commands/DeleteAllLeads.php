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
use App\Models\LeadFormFieldValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteAllLeads extends Command
{
    protected $signature = 'leads:delete-all {--force : Force deletion without confirmation}';
    
    protected $description = 'Delete ALL leads from the entire system (leads, prospects, meetings, tasks, assignments, etc.)';

    public function handle()
    {
        $this->warn('⚠️  WARNING: This will PERMANENTLY delete ALL leads from the entire system!');
        $this->warn('⚠️  This includes: leads, prospects, meetings, tasks, assignments, and ALL related data!');
        $this->line('');
        
        // Get ALL leads (including soft-deleted)
        $allLeads = Lead::withTrashed()->get();
        $totalLeads = $allLeads->count();
        
        if ($totalLeads === 0) {
            $this->info('✅ No leads found in the system.');
            return 0;
        }
        
        $this->info("Found {$totalLeads} leads to delete (including soft-deleted).");
        $this->line('');
        
        // Get counts of related data
        $leadIds = $allLeads->pluck('id')->toArray();
        
        $prospectsCount = Prospect::whereIn('lead_id', $leadIds)->count();
        $meetingsCount = Meeting::whereIn('lead_id', $leadIds)->count();
        $siteVisitsCount = SiteVisit::whereIn('lead_id', $leadIds)->count();
        $tasksCount = Task::whereIn('lead_id', $leadIds)->count();
        $telecallerTasksCount = TelecallerTask::whereIn('lead_id', $leadIds)->count();
        $assignmentsCount = LeadAssignment::whereIn('lead_id', $leadIds)->count();
        $crmAssignmentsCount = CrmAssignment::whereIn('lead_id', $leadIds)->count();
        $callLogsCount = CallLog::whereIn('lead_id', $leadIds)->count();
        $followUpsCount = FollowUp::whereIn('lead_id', $leadIds)->count();
        $importedLeadsCount = ImportedLead::whereIn('lead_id', $leadIds)->count();
        $smartAssignmentsCount = 0;
        $activityLogsCount = ActivityLog::where('model_type', 'Lead')->whereIn('model_id', $leadIds)->count();
        
        $this->warn('This will PERMANENTLY delete:');
        $this->line("  - {$totalLeads} leads (permanent deletion, cannot be recovered)");
        $this->line("  - {$prospectsCount} prospects");
        $this->line("  - {$meetingsCount} meetings");
        $this->line("  - {$siteVisitsCount} site visits");
        $this->line("  - {$tasksCount} tasks (Task model)");
        $this->line("  - {$telecallerTasksCount} telecaller tasks");
        $this->line("  - {$assignmentsCount} lead assignments");
        $this->line("  - {$crmAssignmentsCount} CRM assignments");
        $this->line("  - {$callLogsCount} call logs");
        $this->line("  - {$followUpsCount} follow-ups");
        $this->line("  - {$importedLeadsCount} imported lead records");
        $this->line("  - {$smartAssignmentsCount} smart import assignments");
        $this->line("  - {$activityLogsCount} activity logs");
        $this->line('  - All form field values');
        $this->line('  - ALL related data from every table');
        $this->line('');
        
        // Show sample of leads to be deleted
        $this->info('Sample leads to be deleted (first 10):');
        foreach ($allLeads->take(10) as $lead) {
            $this->line("  - {$lead->name} ({$lead->phone}) - Source: {$lead->source}");
        }
        if ($totalLeads > 10) {
            $this->line("  ... and " . ($totalLeads - 10) . " more");
        }
        $this->line('');
        
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  Are you ABSOLUTELY SURE you want to delete ALL leads? This cannot be undone!')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $this->info('Starting deletion process...');
        $this->line('');
        
        DB::beginTransaction();
        
        try {
            $deletedCount = 0;
            
            // Delete related data first
            $this->line('Deleting related data...');
            
            // Delete imported leads
            $importedDeleted = ImportedLead::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$importedDeleted} imported lead records");
            
            // Delete prospects
            $prospectsDeleted = Prospect::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$prospectsDeleted} prospects");
            
            // Delete site visits
            $visitsDeleted = SiteVisit::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$visitsDeleted} site visits");
            
            // Delete meetings
            $meetingsDeleted = Meeting::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$meetingsDeleted} meetings");
            
            // Delete call logs
            $callLogsDeleted = CallLog::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$callLogsDeleted} call logs");
            
            // Delete follow-ups
            $followUpsDeleted = FollowUp::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$followUpsDeleted} follow-ups");
            
            // Delete telecaller tasks
            $telecallerTasksDeleted = TelecallerTask::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$telecallerTasksDeleted} telecaller tasks");
            
            // Delete tasks
            $tasksDeleted = Task::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$tasksDeleted} tasks");
            
            // Delete CRM assignments
            $crmDeleted = CrmAssignment::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$crmDeleted} CRM assignments");
            
            // Delete lead assignments
            $assignmentsDeleted = LeadAssignment::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$assignmentsDeleted} lead assignments");
            
            
            // Delete activity logs related to these leads
            $activityLogsDeleted = ActivityLog::where('model_type', 'Lead')
                ->whereIn('model_id', $leadIds)
                ->forceDelete();
            $this->line("  ✓ Deleted {$activityLogsDeleted} activity logs");
            
            // Delete form field values
            $formValuesDeleted = LeadFormFieldValue::whereIn('lead_id', $leadIds)->forceDelete();
            $this->line("  ✓ Deleted {$formValuesDeleted} form field values");
            
            // Delete the leads themselves - use forceDelete to permanently remove (bypass soft deletes)
            $this->line('');
            $this->info('Deleting all leads...');
            foreach ($allLeads as $lead) {
                $lead->forceDelete(); // Permanently delete (bypass soft deletes)
                $deletedCount++;
                
                if ($deletedCount % 50 === 0) {
                    $this->line("  ✓ Deleted {$deletedCount} leads so far...");
                }
            }
            
            DB::commit();
            
            $this->line('');
            $this->info("✅ Successfully deleted ALL {$deletedCount} leads and all related data from the entire system!");
            $this->info("✅ System is now clean - no leads remain.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error occurred: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
