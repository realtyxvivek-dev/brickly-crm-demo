<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Events\LeadAssigned;
use Illuminate\Support\Facades\DB;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'lead_id',
        'telecaller_id',
        'manager_id',
        'customer_name',
        'phone',
        'budget',
        'preferred_location',
        'size',
        'purpose',
        'possession',
        'assigned_manager',
        'created_by',
        'notes',
        'remark',
        'employee_remark',
        'manager_remark',
        'lead_status',
        'lead_score',
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected $casts = [
        'budget' => 'string', // Changed to string to support descriptive budget ranges like "75 Lacs-1 Cr"
        'verified_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager');
    }

    public function telecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'telecaller_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CrmAssignment::class, 'assignment_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the interested projects for this prospect.
     */
    public function interestedProjects(): BelongsToMany
    {
        return $this->belongsToMany(InterestedProjectName::class, 'prospect_project', 'prospect_id', 'project_id')
            ->withTimestamps();
    }

    /**
     * Mark prospect as verified and auto-create lead
     */
    public function verify(int $userId, ?string $managerRemark = null, ?string $leadStatus = null): void
    {
        DB::beginTransaction();
        try {
            $this->verification_status = 'verified';
            $this->verified_at = now();
            $this->verified_by = $userId;
            
            if ($managerRemark) {
                $this->manager_remark = $managerRemark;
                $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Manager Remark: " . $managerRemark;
            }
            
            if ($leadStatus) {
                $this->lead_status = $leadStatus;
            }
            
            $this->save();
            
            // Auto-create lead if not already created
            if (!$this->lead_id) {
                $lead = $this->createLeadFromProspect($userId);
                
                // Update prospect with lead_id
                $this->lead_id = $lead->id;
                $this->save();
                
                // Assign lead to the manager who verified (or telecaller's manager)
                $assignedTo = $this->manager_id ?? $this->telecaller->manager_id ?? $userId;
                
                // Deactivate existing assignments for this lead
                LeadAssignment::where('lead_id', $lead->id)->update([
                    'is_active' => false,
                    'unassigned_at' => now()
                ]);
                
                // Create new assignment
                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $assignedTo,
                    'assigned_by' => $userId,
                    'assignment_type' => 'primary',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);
                
                // Fire LeadAssigned event
                event(new LeadAssigned($lead, $assignedTo, $userId));
                
                // Update lead status to verified_prospect
                $lead->updateStatusIfAllowed('verified_prospect');
            } else {
                // If lead already exists, just update its status
                $lead = Lead::find($this->lead_id);
                if ($lead) {
                    $lead->updateStatusIfAllowed('verified_prospect');
                }
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a lead from verified prospect
     */
    private function createLeadFromProspect(int $createdBy): Lead
    {
        // Map prospect fields to lead fields
        $leadData = [
            'name' => $this->customer_name,
            'phone' => $this->phone,
            'email' => null, // Prospect doesn't have email
            'budget' => $this->budget,
            'preferred_location' => $this->preferred_location,
            'preferred_size' => $this->size,
            'use_end_use' => $this->purpose === 'end_user' ? 'End User' : ($this->purpose === 'investment' ? '2nd Investments' : null),
            'possession_status' => $this->possession,
            'source' => 'call', // All prospects come from telecaller calls
            'status' => 'verified_prospect', // Verified prospects
            'created_by' => $createdBy,
        ];
        
        // Combine remarks in notes
        $notes = [];
        if ($this->remark) {
            $notes[] = "Telecaller Remark: " . $this->remark;
        }
        if ($this->manager_remark) {
            $notes[] = "Manager Remark: " . $this->manager_remark;
        }
        if (!empty($notes)) {
            $leadData['notes'] = implode("\n\n", $notes);
        }
        
        // Add requirements if available
        if ($this->notes) {
            $leadData['requirements'] = $this->notes;
        }
        
        return Lead::create($leadData);
    }

    /**
     * Prospect verification flow removed: legacy reject actions keep the prospect verified.
     */
    public function reject(int $userId, string $reason): void
    {
        $this->verification_status = 'verified';
        $this->verified_at = now();
        $this->verified_by = $userId;
        $this->rejection_reason = null;
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Verification bypassed: " . $reason;
        $this->save();
    }

    /**
     * Check if prospect is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
