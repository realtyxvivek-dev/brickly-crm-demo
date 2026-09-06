<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prospect;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Events\LeadAssigned;
use Illuminate\Support\Facades\DB;

class ConvertVerifiedProspectsToLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prospects:convert-to-leads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all verified prospects that do not have leads yet into leads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting conversion of verified prospects to leads...');

        // Get all verified prospects without leads
        $prospects = Prospect::whereIn('verification_status', ['verified', 'approved'])
            ->whereNull('lead_id')
            ->with(['telecaller', 'manager', 'verifiedBy'])
            ->get();

        if ($prospects->isEmpty()) {
            $this->info('No verified prospects found without leads.');
            return 0;
        }

        $this->info("Found {$prospects->count()} verified prospects to convert.");

        $bar = $this->output->createProgressBar($prospects->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($prospects as $prospect) {
            try {
                DB::beginTransaction();

                // Create lead from prospect
                $lead = $this->createLeadFromProspect($prospect);

                // Update prospect with lead_id
                $prospect->lead_id = $lead->id;
                $prospect->save();

                // Assign lead to the manager who verified (or telecaller's manager)
                $assignedTo = $prospect->manager_id ?? $prospect->telecaller->manager_id ?? $prospect->verified_by ?? $prospect->created_by;

                if ($assignedTo) {
                    // Deactivate existing assignments for this lead
                    LeadAssignment::where('lead_id', $lead->id)->update([
                        'is_active' => false,
                        'unassigned_at' => now()
                    ]);

                    // Create new assignment
                    LeadAssignment::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'assigned_by' => $prospect->verified_by ?? $prospect->created_by,
                        'assignment_type' => 'primary',
                        'assigned_at' => $prospect->verified_at ?? now(),
                        'is_active' => true,
                    ]);

                    // Fire LeadAssigned event
                    event(new LeadAssigned($lead, $assignedTo, $prospect->verified_by ?? $prospect->created_by));
                }

                DB::commit();
                $success++;
            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $this->error("\nFailed to convert prospect {$prospect->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Conversion completed!");
        $this->info("Successfully converted: {$success} prospects");
        if ($failed > 0) {
            $this->warn("Failed: {$failed} prospects");
        }

        return 0;
    }

    /**
     * Create a lead from verified prospect
     */
    private function createLeadFromProspect(Prospect $prospect): Lead
    {
        // Map prospect fields to lead fields
        $leadData = [
            'name' => $prospect->customer_name,
            'phone' => $prospect->phone,
            'email' => null,
            'budget' => $prospect->budget,
            'preferred_location' => $prospect->preferred_location,
            'preferred_size' => $prospect->size,
            'use_end_use' => $prospect->purpose === 'end_user' ? 'End User' : ($prospect->purpose === 'investment' ? '2nd Investments' : null),
            'possession_status' => $prospect->possession,
            'source' => 'call',
            'status' => 'verified_prospect',
            'created_by' => $prospect->verified_by ?? $prospect->created_by,
        ];

        // Combine remarks in notes
        $notes = [];
        if ($prospect->remark) {
            $notes[] = "Telecaller Remark: " . $prospect->remark;
        }
        if ($prospect->manager_remark) {
            $notes[] = "Manager Remark: " . $prospect->manager_remark;
        }
        if (!empty($notes)) {
            $leadData['notes'] = implode("\n\n", $notes);
        }

        // Add requirements if available
        if ($prospect->notes) {
            $leadData['requirements'] = $prospect->notes;
        }

        return Lead::create($leadData);
    }
}
