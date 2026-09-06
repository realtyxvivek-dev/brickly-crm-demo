<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadMergeAudit extends Model
{
    protected $fillable = [
        'master_lead_id',
        'duplicate_lead_id',
        'normalized_phone',
        'status',
        'master_snapshot',
        'duplicate_snapshot',
        'moved_record_counts',
        'remark',
        'failure_details',
        'actor_id',
        'merged_at',
    ];

    protected $casts = [
        'master_snapshot' => 'array',
        'duplicate_snapshot' => 'array',
        'moved_record_counts' => 'array',
        'merged_at' => 'datetime',
    ];
}
