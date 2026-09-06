<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadBankImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_bank_import_session_id',
        'row_number',
        'raw_data',
        'mapped_data',
        'normalized_phone',
        'tags',
        'validation_status',
        'errors',
        'include',
        'import_action',
        'existing_lead_id',
        'created_lead_id',
        'processed_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'mapped_data' => 'array',
        'tags' => 'array',
        'errors' => 'array',
        'include' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LeadBankImportSession::class, 'lead_bank_import_session_id');
    }

    public function existingLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'existing_lead_id');
    }

    public function createdLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'created_lead_id');
    }
}
