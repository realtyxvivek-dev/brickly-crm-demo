<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_key',
        'report_name',
        'filters_json',
        'format',
        'generated_by',
        'generated_at',
        'record_count',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'generated_at' => 'datetime',
        'record_count' => 'integer',
    ];

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
