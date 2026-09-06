<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaBulkRecoveryScan extends Model
{
    protected $fillable = [
        'status',
        'scope_type',
        'scope_id',
        'date_from',
        'date_to',
        'per_form_limit',
        'total_limit',
        'forms_total',
        'forms_scanned',
        'fetched_count',
        'importable_count',
        'already_present_count',
        'failed_count',
        'started_by',
        'started_at',
        'finished_at',
        'error',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function forms(): HasMany
    {
        return $this->hasMany(MetaBulkRecoveryScanForm::class, 'scan_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(MetaBulkRecoveryScanLead::class, 'scan_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
