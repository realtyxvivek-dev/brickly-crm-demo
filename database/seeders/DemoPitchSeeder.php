<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoPitchSeeder extends Seeder
{
    public function run(): void
    {
        $demoDatabase = env('DEMO_SEED_DATABASE');

        if (!$demoDatabase || DB::connection()->getDatabaseName() !== $demoDatabase || DB::table('users')->exists()) {
            throw new \RuntimeException('Demo seeding requires the empty dedicated demo database.');
        }
        DB::transaction(function (): void {
            $now = now();
            $roleIds = DB::table('roles')->pluck('id', 'slug');

            $users = [
                ['name' => 'Brickly Demo Admin', 'email' => 'demo@bihtech.in', 'role' => 'admin', 'manager' => null],
                ['name' => 'Demo CRM Manager', 'email' => 'crm.demo@bihtech.in', 'role' => 'crm', 'manager' => null],
                ['name' => 'Demo Sales Manager', 'email' => 'manager.demo@bihtech.in', 'role' => 'sales_manager', 'manager' => null],
                ['name' => 'Demo Sales Executive 1', 'email' => 'sales1.demo@bihtech.in', 'role' => 'sales_executive', 'manager' => 'manager.demo@bihtech.in'],
                ['name' => 'Demo Sales Executive 2', 'email' => 'sales2.demo@bihtech.in', 'role' => 'sales_executive', 'manager' => 'manager.demo@bihtech.in'],
            ];

            $userIds = [];
            foreach ($users as $user) {
                $userIds[$user['email']] = DB::table('users')->insertGetId([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'email_verified_at' => $now,
                    'password' => Hash::make('BricklyDemo@2026!'),
                    'role_id' => $roleIds[$user['role']],
                    'manager_id' => $user['manager'] ? $userIds[$user['manager']] : null,
                    'is_active' => true,
                    'two_factor_mode' => 'off',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $adminId = $userIds['demo@bihtech.in'];
            $salesIds = [$userIds['sales1.demo@bihtech.in'], $userIds['sales2.demo@bihtech.in']];
            $sources = ['meta', 'website', '99acres', 'housing', 'organic', 'google'];
            $statuses = ['new', 'connected', 'verified_prospect', 'meeting_scheduled', 'visit_scheduled', 'visit_done', 'not_interested', 'on_hold', 'closed', 'fresh_transfer', 'visit_done', 'connected'];
            $projects = ['Demo Heights', 'Demo Greens', 'Demo Business Park'];
            $leadIds = [];

            foreach ($statuses as $index => $status) {
                $createdAt = $now->copy()->subDays(24 - ($index * 2));
                $leadIds[$index] = DB::table('leads')->insertGetId([
                    'name' => sprintf('Demo Customer %02d', $index + 1),
                    'email' => sprintf('customer%02d@example.invalid', $index + 1),
                    'phone' => sprintf('000000%04d', $index + 101),
                    'city' => 'Lucknow',
                    'state' => 'Uttar Pradesh',
                    'source' => $sources[$index % count($sources)],
                    'status' => $status,
                    'property_type' => $index % 3 === 0 ? 'Commercial' : 'Apartment',
                    'budget' => $index % 3 === 0 ? '₹75 Lakh – ₹1 Crore' : '₹45 – ₹70 Lakh',
                    'requirements' => 'Dummy pitch-deck record for product demonstration only.',
                    'preferred_location' => ['Gomti Nagar', 'Shaheed Path', 'Sultanpur Road'][$index % 3],
                    'preferred_projects' => $projects[$index % count($projects)],
                    'created_by' => $adminId,
                    'last_contacted_at' => $index > 0 ? $createdAt->copy()->addDay() : null,
                    'next_followup_at' => in_array($status, ['connected', 'visit_done', 'on_hold'], true) ? $now->copy()->addDays(($index % 4) + 1) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $assignedTo = $salesIds[$index % 2];
                DB::table('lead_assignments')->insert([
                    'lead_id' => $leadIds[$index],
                    'assigned_to' => $assignedTo,
                    'assigned_by' => $adminId,
                    'assignment_type' => 'primary',
                    'assignment_method' => $index % 2 === 0 ? 'round_robin' : 'manual',
                    'notes' => 'Dummy assignment for demo environment.',
                    'assigned_at' => $createdAt,
                    'is_active' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                if (in_array($status, ['connected', 'visit_done', 'on_hold'], true)) {
                    DB::table('follow_ups')->insert([
                        'lead_id' => $leadIds[$index],
                        'created_by' => $assignedTo,
                        'type' => 'call',
                        'notes' => 'Discuss project shortlist and next action (dummy).',
                        'scheduled_at' => $now->copy()->addDays(($index % 4) + 1),
                        'status' => 'scheduled',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach ([4, 5, 10] as $position => $index) {
                $completed = in_array($index, [5, 10], true);
                $scheduledAt = $now->copy()->subDays(8 - ($position * 3));
                DB::table('site_visits')->insert([
                    'lead_id' => $leadIds[$index],
                    'customer_name' => sprintf('Demo Customer %02d', $index + 1),
                    'phone' => sprintf('000000%04d', $index + 101),
                    'created_by' => $adminId,
                    'assigned_to' => $salesIds[$index % 2],
                    'project' => $projects[$index % count($projects)],
                    'visited_projects' => $projects[$index % count($projects)],
                    'property_name' => $projects[$index % count($projects)],
                    'property_address' => 'Demo location, Lucknow',
                    'scheduled_at' => $scheduledAt,
                    'completed_at' => $completed ? $scheduledAt->copy()->addHours(2) : null,
                    'status' => $completed ? 'completed' : 'scheduled',
                    'verification_status' => $completed ? 'verified' : 'pending',
                    'verified_by' => $completed ? $adminId : null,
                    'verified_at' => $completed ? $scheduledAt->copy()->addHours(3) : null,
                    'visit_notes' => 'Dummy site visit created for the investor demo.',
                    'visit_type' => $position % 2 === 0 ? 'with_family' : 'without_family',
                    'visit_sequence' => $position === 2 ? '2nd_visit' : 'fresh_visit',
                    'lead_status' => $completed ? 'hot' : 'warm',
                    'created_at' => $scheduledAt,
                    'updated_at' => $scheduledAt,
                ]);
            }
        });
    }
}
