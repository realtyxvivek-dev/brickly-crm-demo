<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prospect;
use App\Models\InterestedProjectName;
use Illuminate\Support\Facades\DB;

class AssignDummyInterestedProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-dummy-interested-projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign 4-5 dummy interested projects to all existing prospects';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to assign dummy interested projects...');

        // Get all prospects
        $prospects = Prospect::all();
        $totalProspects = $prospects->count();
        
        if ($totalProspects === 0) {
            $this->warn('No prospects found in the database.');
            return 0;
        }

        // Get all active interested project names
        $projects = InterestedProjectName::where('is_active', true)->pluck('id')->toArray();
        
        if (empty($projects)) {
            $this->warn('No active interested project names found. Please run the seeder first.');
            return 0;
        }

        $this->info("Found {$totalProspects} prospects and " . count($projects) . " active projects.");
        $this->info('Assigning 4-5 random projects to each prospect...');

        $bar = $this->output->createProgressBar($totalProspects);
        $bar->start();

        $assignedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($prospects as $prospect) {
                // Randomly select 4-5 projects
                $numberOfProjects = rand(4, 5);
                
                // Shuffle and take random projects
                shuffle($projects);
                $selectedProjects = array_slice($projects, 0, min($numberOfProjects, count($projects)));
                
                // Sync projects to prospect
                $prospect->interestedProjects()->sync($selectedProjects);
                
                $assignedCount++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();
            $this->info("Successfully assigned projects to {$assignedCount} prospects!");
            
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
