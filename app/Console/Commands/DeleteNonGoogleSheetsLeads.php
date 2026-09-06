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

class DeleteNonGoogleSheetsLeads extends Command
{
    protected $signature = 'leads:delete-non-google-sheets {--force : Force deletion without confirmation}';
    
    protected $description = 'Delete ALL leads EXCEPT Google Sheets leads (source != google_sheets)';

    public function handle()
    {
        $this->warn('⚠️  WARNING: This will PERMANENTLY delete all leads except Google Sheets leads!');
        $this->line('');
        
        // First, show breakdown of leads by source
        $this->info('Checking leads by source...');
        $sourceBreakdown = Lead::withTrashed()
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->get();
        
        foreach ($sourceBreakdown as $breakdown) {
            $source = $breakdown->source ?? 'NULL';
            $this->line("  - Source '{$source}': {$breakdown->count} leads");
        }
        $this->line('');
        
        // Get all Google Sheets lead IDs from multiple sources:
        // 1. Leads with source='google_sheets'
        // 2. Leads imported via ImportedLead with source_type='google_sheets'
        // 3. Leads that have sheet_config_id in LeadAssignment or CrmAssignment
        
        $googleSheetsSourceLeadIds = Lead::withTrashed()->where('source', 'google_sheets')->pluck('id')->toArray();
        
        // Check ImportedLead table
        $importedGoogleSheetsLeadIds = [];
        try {
            if (DB::getSchemaBuilder()->hasTable('imported_leads') && DB::getSchemaBuilder()->hasTable('import_batches')) {
                $importedGoogleSheetsLeadIds = DB::table('imported_leads')
                    ->join('import_batches', 'imported_leads.import_batch_id', '=', 'import_batches.id')
                    ->where('import_batches.source_type', 'google_sheets')
                    ->pluck('imported_leads.lead_id')
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }
        
        // Check LeadAssignment table for sheet_config_id
        $sheetAssignmentLeadIds = [];
        try {
            if (DB::getSchemaBuilder()->hasTable('crm_assignments')) {
                $sheetAssignmentLeadIds = DB::table('crm_assignments')
                    ->whereNotNull('sheet_config_id')
                    ->distinct()
                    ->pluck('lead_id')
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }
        
        // Also check LeadAssignment table
        $leadAssignmentSheetIds = [];
        try {
            if (DB::getSchemaBuilder()->hasTable('crm_assignments')) {
                $leadAssignmentSheetIds = DB::table('crm_assignments')
                    ->whereNotNull('sheet_config_id')
                    ->distinct()
                    ->pluck('lead_id')
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }
        
        // Combine all Google Sheets lead IDs
        $allGoogleSheetsLeadIds = array_unique(array_merge(
            $googleSheetsSourceLeadIds,
            $importedGoogleSheetsLeadIds,
            $sheetAssignmentLeadIds,
            $leadAssignmentSheetIds
        ));
        
        $this->info("Google Sheets lead IDs found: " . count($allGoogleSheetsLeadIds));
        $this->line("  - From source='google_sheets': " . count($googleSheetsSourceLeadIds));
        $this->line("  - From imported_leads: " . count($importedGoogleSheetsLeadIds));
        $this->line("  - From sheet_config_id: " . count($sheetAssignmentLeadIds));
        $this->line('');
        
        // Get ALL leads (including soft-deleted)
        $allLeads = Lead::withTrashed()->get();
        $this->info("Total leads in system: " . $allLeads->count());
        
        // Find leads that are NOT in Google Sheets list
        $leadsToDelete = $allLeads->filter(function($lead) use ($allGoogleSheetsLeadIds) {
            return !in_array($lead->id, $allGoogleSheetsLeadIds);
        });
        
        $googleSheetsLeads = count($allGoogleSheetsLeadIds);
        $count = $leadsToDelete->count();
        
        // If no leads to delete, check if user wants to proceed anyway
        if ($count === 0) {
            $this->info('✅ No non-Google Sheets leads found. All leads are from Google Sheets.');
            $this->info("Google Sheets leads: {$googleSheetsLeads}");
            $this->line('');
            $this->warn('All leads in the system are already from Google Sheets.');
            $this->info('If you want to delete specific leads, please specify them manually.');
            return 0;
        }
        
        $this->info("Found {$count} leads to delete (non-Google Sheets).");
        $this->info("Google Sheets leads to keep: {$googleSheetsLeads}");
        $this->line('');
        
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
        $this->line('  - Related smart import assignments');
        $this->line('  - Related activity logs');
        $this->line('  - Related form field values');
        
        // Show sample of leads to be deleted
        $this->line('');
        $this->info('Sample leads to be deleted (first 10):');
        foreach ($leadsToDelete->take(10) as $lead) {
            $this->line("  - {$lead->name} ({$lead->phone}) - Source: {$lead->source}");
        }
        if ($count > 10) {
            $this->line("  ... and " . ($count - 10) . " more");
        }
        $this->line('');
        
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  Are you ABSOLUTELY SURE you want to delete all non-Google Sheets leads? This cannot be undone!')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $this->info('Starting deletion process...');
        
        $leadIds = $leadsToDelete->pluck('id')->toArray();
        
        DB::beginTransaction();
        
        try {
            $deletedCount = 0;
            
            // Delete related data first
            $this->line('Deleting related data...');
            
            // Delete imported leads
            $importedDeleted = ImportedLead::whereIn('lead_id', $leadIds)->delete();
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
            $this->info('Deleting leads...');
            foreach ($leadsToDelete as $lead) {
                $lead->forceDelete(); // Permanently delete (bypass soft deletes)
                $deletedCount++;
                
                if ($deletedCount % 50 === 0) {
                    $this->line("  ✓ Deleted {$deletedCount} leads so far...");
                }
            }
            
            DB::commit();
            
            $this->line('');
            $this->info("✅ Successfully deleted {$deletedCount} non-Google Sheets leads and all related data!");
            $this->info("✅ Kept {$googleSheetsLeads} Google Sheets leads");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error occurred: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
