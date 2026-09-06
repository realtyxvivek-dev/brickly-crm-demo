<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixNaveenManager extends Command
{
    protected $signature = 'users:fix-naveen';
    protected $description = 'Fix Naveen manager to Omkar';

    public function handle()
    {
        $naveen = User::where('name', 'Naveen')->first();
        $omkar = User::where('name', 'Omkar')->first();
        
        if (!$naveen) {
            $this->error('Naveen not found!');
            return Command::FAILURE;
        }
        
        if (!$omkar) {
            $this->error('Omkar not found!');
            return Command::FAILURE;
        }
        
        $oldManager = $naveen->manager ? $naveen->manager->name : 'None';
        $naveen->update(['manager_id' => $omkar->id]);
        
        $this->info("Updated Naveen: Manager changed from '{$oldManager}' to 'Omkar'");
        
        return Command::SUCCESS;
    }
}

