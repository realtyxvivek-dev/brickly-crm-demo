<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectUrlImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'import_token',
        'source_key',
        'normalized_url',
        'parser_version',
        'progress_stage',
        'progress_percent',
        'extracted_field_count',
        'failed_selectors',
        'warnings',
        'status',
        'duration_ms',
    ];

    protected $casts = [
        'failed_selectors' => 'array',
        'warnings' => 'array',
        'progress_percent' => 'integer',
        'extracted_field_count' => 'integer',
        'duration_ms' => 'integer',
    ];
}
