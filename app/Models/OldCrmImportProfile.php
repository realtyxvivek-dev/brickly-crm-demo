<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OldCrmImportProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'header_signature',
        'headers',
        'mapping_config',
        'stage_mapping',
    ];

    protected $casts = [
        'headers' => 'array',
        'mapping_config' => 'array',
        'stage_mapping' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'import_profile_id');
    }
}
