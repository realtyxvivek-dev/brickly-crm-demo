<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class TaskService
{
    /**
     * Create a phone call task for a lead assignment
     */
    public function createPhoneCallTask(Lead $lead, int $assignedToUserId, int $createdBy): Task
    {
        try {
            $task = Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedToUserId,
                'type' => 'phone_call',
                'title' => "Call lead: {$lead->name}",
                'description' => "Phone call task for lead: {$lead->name} ({$lead->phone})",
                'status' => 'pending',
                'scheduled_at' => now(),
                'created_by' => $createdBy,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error("Failed to create phone call task for lead {$lead->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark task as completed
     */
    public function completeTask(Task $task): Task
    {
        $task->markAsCompleted();
        return $task->fresh();
    }

    /**
     * Get lead data for popup form after call completion
     */
    public function getLeadDataForForm(Task $task): array
    {
        $lead = $task->lead;
        
        return [
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'address' => $lead->address,
            'city' => $lead->city,
            'state' => $lead->state,
            'pincode' => $lead->pincode,
            'preferred_location' => $lead->preferred_location,
            'preferred_size' => $lead->preferred_size,
            'property_type' => $lead->property_type,
            'budget_min' => $lead->budget_min,
            'budget_max' => $lead->budget_max,
            'investment' => $lead->investment ?? null,
            'source' => $lead->source,
            'use_end_use' => $lead->use_end_use,
            'requirements' => $lead->requirements,
            'notes' => $lead->notes,
        ];
    }

    /**
     * Create a phone call task for manager verification
     * Scheduled 10 minutes after prospect is sent for verification
     */
    public function createManagerVerificationCallTask($prospect, int $assignedToUserId, int $createdBy): Task
    {
        try {
            $customerName = $prospect->customer_name ?? 'Prospect';
            $phone = $prospect->phone ?? '';
            $leadId = $prospect->lead_id;
            
            // Cancel existing pending/in_progress tasks for this lead and user
            // This ensures only one active task per lead at any time
            Task::where('lead_id', $leadId)
                ->where('assigned_to', $assignedToUserId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->get()
                ->each(function (Task $task): void {
                    $reason = 'Cancellation reason: Cancelled due to manager verification task refresh.';
                    $notes = trim(($task->notes ?? '') . PHP_EOL . $reason);

                    $task->update([
                        'status' => 'cancelled',
                        'completed_at' => now(),
                        'notes' => $notes !== '' ? $notes : null,
                    ]);
                });
            
            // Create Task in tasks table for manager verification
            $task = Task::create([
                'lead_id' => $leadId,
                'assigned_to' => $assignedToUserId,
                'type' => 'phone_call',
                'title' => "Call for prospect verification: {$customerName}",
                'description' => "Phone call task for prospect verification: {$customerName} ({$phone}). Prospect sent for verification and requires follow-up call.",
                'status' => 'pending',
                'scheduled_at' => now()->addMinutes(10),
                'created_by' => $createdBy,
            ]);

            Log::info("Created manager verification call task", [
                'task_id' => $task->id,
                'prospect_id' => $prospect->id,
                'assigned_to' => $assignedToUserId,
                'scheduled_at' => $task->scheduled_at,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error("Failed to create manager verification call task for prospect {$prospect->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a calling task for manager/executive when prospect is assigned
     * Scheduled 10 minutes from now - will become overdue after 10 minutes
     */
    public function createManagerProspectCallTask($prospect, int $assignedToUserId, int $createdBy): Task
    {
        try {
            $customerName = $prospect->customer_name ?? 'Prospect';
            $phone = $prospect->phone ?? '';
            $leadId = $prospect->lead_id ?? null;
            
            // Create Task in tasks table with scheduled_at set to 10 minutes from now
            $task = Task::create([
                'lead_id' => $leadId,
                'assigned_to' => $assignedToUserId,
                'type' => 'phone_call',
                'title' => "Call for prospect verification: {$customerName}",
                'description' => "Prospect verification call task for: {$customerName} ({$phone})",
                'status' => 'pending',
                'scheduled_at' => now()->addMinutes(10), // Critical: 10 minutes from now
                'created_by' => $createdBy,
            ]);

            Log::info("Created manager prospect call task", [
                'task_id' => $task->id,
                'prospect_id' => $prospect->id,
                'assigned_to' => $assignedToUserId,
                'scheduled_at' => $task->scheduled_at,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error("Failed to create manager prospect call task for prospect {$prospect->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
