<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch_id',
        'lead_id',
        'assigned_to',
        'assigned_at',
        'import_data',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'import_data' => 'array',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function lead(): BelongsTo
    {
        // Leads use SoftDeletes; imported_leads still reference the row, but default
        // belongsTo() would hide the lead and return null after soft delete.
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

