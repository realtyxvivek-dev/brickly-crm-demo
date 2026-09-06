<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use Illuminate\Console\Command;

class AssignDummyLeadScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-dummy-lead-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assigns random dummy lead scores (1, 2, 3, 4, or 5) to all existing prospects.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to assign dummy lead scores...');
        
        $prospects = Prospect::all();
        
        if ($prospects->count() === 0) {
            $this->warn('No prospects found.');
            return;
        }
        
        $this->info(sprintf('Found %d prospects.', $prospects->count()));
        $this->info('Assigning random lead scores (1-5) to each prospect...');
        
        $progressBar = $this->output->createProgressBar($prospects->count());
        $progressBar->start();
        
        $scores = [1, 2, 3, 4, 5]; // All possible scores
        $updated = 0;
        
        foreach ($prospects as $prospect) {
            // Randomly select from available scores
            $randomScore = $scores[array_rand($scores)];
            $prospect->update(['lead_score' => $randomScore]);
            $updated++;
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info(sprintf('Successfully assigned lead scores to %d prospects!', $updated));
        
        // Show distribution
        $distribution = Prospect::selectRaw('lead_score, COUNT(*) as count')
            ->whereNotNull('lead_score')
            ->groupBy('lead_score')
            ->orderBy('lead_score')
            ->pluck('count', 'lead_score')
            ->toArray();
        
        $this->newLine();
        $this->info('Lead Score Distribution:');
        foreach ($distribution as $score => $count) {
            $this->line(sprintf('  %d stars: %d prospects', $score, $count));
        }
    }
}
