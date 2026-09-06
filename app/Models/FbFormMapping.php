<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbFormMapping extends Model
{
    protected $table = 'fb_form_mappings';

    protected $fillable = [
        'fb_form_id',
        'mapping_json',
        'created_by',
    ];

    protected $casts = [
        'mapping_json' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
