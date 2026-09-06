<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollManualAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payroll_deduction_head_id',
        'year',
        'month',
        'label',
        'type',
        'amount',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(PayrollDeductionHead::class, 'payroll_deduction_head_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
