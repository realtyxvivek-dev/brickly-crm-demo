<?php

namespace App\Console\Commands;

use App\Events\LeadAssigned;
use App\Events\ProspectSentForVerification;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Prospect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateMahaveerNishantWithProspects extends Command
{
    protected $signature = 'crm:create-mahaveer-nishant-with-prospects';
    protected $description = 'Create Mahaveer (ASM), Nishant (Sales Executive), and 10 prospects with Indian names on localhost';

    public function handle(): int
    {
        $asmRole = Role::where('slug', Role::ASSISTANT_SALES_MANAGER)->first();
        $seRole = Role::where('slug', Role::SALES_EXECUTIVE)->first();

        if (!$asmRole || !$seRole) {
            $this->error('Roles not found. Run: php artisan db:seed --class=RoleSeeder');
            return 1;
        }

        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $admin = $adminRole ? User::where('role_id', $adminRole->id)->where('is_active', true)->first() : null;
        $createdBy = $admin ? $admin->id : 1;

        DB::beginTransaction();

        try {
            // 1. Mahaveer (Assistant Sales Manager)
            $mahaveer = User::firstOrCreate(
                ['email' => 'mahaveer@realtorcrm.com'],
                [
                    'name' => 'Mahaveer',
                    'password' => Hash::make('mahaveer123'),
                    'role_id' => $asmRole->id,
                    'manager_id' => null,
                    'is_active' => true,
                ]
            );
            $this->info("✓ Mahaveer (ASM) – ID: {$mahaveer->id}");

            // 2. Nishant (Sales Executive, under Mahaveer)
            $nishant = User::firstOrCreate(
                ['email' => 'nishant@realtorcrm.com'],
                [
                    'name' => 'Nishant',
                    'password' => Hash::make('nishant123'),
                    'role_id' => $seRole->id,
                    'manager_id' => $mahaveer->id,
                    'is_active' => true,
                ]
            );
            if ($nishant->manager_id !== $mahaveer->id) {
                $nishant->update(['manager_id' => $mahaveer->id]);
            }
            $this->info("✓ Nishant (Sales Executive) – ID: {$nishant->id}, manager: Mahaveer");

            $firstNames = ['Raj', 'Priya', 'Amit', 'Neha', 'Rahul', 'Sneha', 'Vikram', 'Anjali', 'Deepak', 'Kavita', 'Arjun', 'Swati', 'Rohit', 'Pooja', 'Sachin', 'Divya', 'Karan', 'Meera', 'Vishal', 'Tanvi'];
            $lastNames = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Reddy', 'Malik', 'Chauhan', 'Shah', 'Mehta', 'Joshi', 'Desai', 'Agarwal', 'Gandhi', 'Nair', 'Rao', 'Iyer', 'Menon'];

            $leadsCreated = 0;
            $prospectsCreated = 0;

            for ($i = 1; $i <= 10; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $customerName = $firstName . ' ' . $lastName;
                $phone = '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);

                $lead = Lead::create([
                    'name' => $customerName,
                    'phone' => $phone,
                    'source' => 'call',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $nishant->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                try {
                    event(new LeadAssigned($lead, $nishant->id, $createdBy));
                } catch (\Exception $e) {
                    Log::warning('LeadAssigned event in command: ' . $e->getMessage());
                }

                $prospect = Prospect::create([
                    'lead_id' => $lead->id,
                    'telecaller_id' => $nishant->id,
                    'manager_id' => $mahaveer->id,
                    'customer_name' => $customerName,
                    'phone' => $phone,
                    'verification_status' => 'pending_verification',
                    'created_by' => $nishant->id,
                ]);

                try {
                    event(new ProspectSentForVerification($prospect));
                } catch (\Exception $e) {
                    Log::warning('ProspectSentForVerification event in command: ' . $e->getMessage());
                }

                $this->line("  ✓ Lead + prospect: {$customerName}");
                $leadsCreated++;
                $prospectsCreated++;
            }

            DB::commit();

            $this->newLine();
            $this->info("Done. Mahaveer (ASM), Nishant (Sales Executive), {$leadsCreated} leads and {$prospectsCreated} prospects created.");
            $this->info('Login as Mahaveer or Nishant via the login page.');
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
