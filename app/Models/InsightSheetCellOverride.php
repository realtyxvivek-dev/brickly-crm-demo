<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsightSheetCellOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'sheet_key',
        'row_key',
        'column_key',
        'value',
        'edited_by',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
