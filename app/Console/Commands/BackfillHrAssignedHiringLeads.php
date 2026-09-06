<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Role;
use Illuminate\Console\Command;

class BackfillHrAssignedHiringLeads extends Command
{
    protected $signature = 'hiring:backfill-hr-assigned-leads {--dry-run : Show affected count without updating}';

    protected $description = 'Mark active HR-assigned leads as hiring candidates so they appear in HR Hiring.';

    public function handle(): int
    {
        $query = $this->eligibleLeadsQuery();
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("HR-assigned non-hiring leads found: {$count}");

            return self::SUCCESS;
        }

        $updated = 0;

        $query->chunkById(100, function ($leads) use (&$updated) {
            foreach ($leads as $lead) {
                $updates = [
                    'is_hiring_candidate' => true,
                ];

                if (blank($lead->hiring_status)) {
                    $updates['hiring_status'] = 'new';
                }

                $lead->forceFill($updates)->save();
                $updated++;
            }
        });

        $this->info("HR-assigned hiring backfill complete. Updated {$updated} of {$count} leads.");

        return self::SUCCESS;
    }

    private function eligibleLeadsQuery()
    {
        return Lead::query()
            ->where(function ($query) {
                $query->where('is_hiring_candidate', false)
                    ->orWhereNull('is_hiring_candidate');
            })
            ->whereHas('activeAssignments.assignedTo.role', function ($query) {
                $query->whereIn('slug', [Role::HR_MANAGER, Role::JUNIOR_HR]);
            });
    }
}
